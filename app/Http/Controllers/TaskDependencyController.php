<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Services\Scheduling\ProjectScheduler;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Scheduling\DependencyType;
use App\Support\Scheduling\ProjectDurations;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «Depende de», sin códigos.
 *
 * La sintaxis `12FS+2d` sigue existiendo en la vista Lista y no se va: es la
 * forma más rápida que hay de capturar una red, y quien viene de otra
 * herramienta la espera. Pero **exigirla para poder ligar dos tareas** deja
 * fuera a todo el que no la conoce, que es casi todo el mundo.
 *
 * Aquí se escoge la tarea de una lista y la relación en español. En Modo Simple
 * ni siquiera hay relación que escoger: todas las dependencias reales son «esta
 * empieza cuando aquella termina», y las otras tres se ofrecen solo a quien pidió
 * el Modo Experto.
 *
 * **Un círculo se rechaza y se deshace en el momento.** Sin esto, ligar dos
 * tareas al revés dejaría el proyecto entero sin poder calcularse, con un
 * mensaje que no dice qué hacer, y quien lo provocó no tendría forma de saber
 * cuál de las dependencias fue.
 */
final class TaskDependencyController extends Controller
{
    public function __construct(
        private readonly TaskOutliner $outliner,
        private readonly ProjectScheduler $scheduler,
    ) {}

    public function store(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        $data = $request->validate([
            'predecessor_id' => [
                'required', 'integer',
                Rule::exists('tasks', 'id')->where('project_id', $project->id)->withoutTrashed(),
                // Una tarea que depende de sí misma es un círculo de uno. Se
                // atrapa aquí para poder decirlo con esas palabras.
                Rule::notIn([$task->id]),
            ],
            'type' => ['required', Rule::in(array_column(DependencyType::cases(), 'value'))],
            'lag_days' => ['nullable', 'numeric', 'between:-365,365'],
        ]);

        // La espera se traduce con la **jornada de este proyecto**, por el mismo
        // traductor que usa todo lo demás: dos días en un proyecto de nueve
        // horas no son los mismos minutos que en uno de ocho, y calcularlo aquí
        // a mano sería la cuarta copia del cálculo que ya se extrajo una vez.
        // El signo va aparte porque el traductor lee duraciones, no diferencias.
        $days = (float) ($data['lag_days'] ?? 0);
        $lag = $days === 0.0
            ? 0
            : (int) round(ProjectDurations::for($project)->toMinutes(abs($days).'d') * ($days < 0 ? -1 : 1));

        $existing = TaskDependency::query()
            ->where('successor_id', $task->id)
            ->where('predecessor_id', (int) $data['predecessor_id'])
            ->first();

        $link = DB::transaction(function () use ($project, $task, $data, $lag, $existing): TaskDependency {
            // Ligar dos tareas que ya estaban ligadas cambia la relación en vez
            // de crear una segunda: dos dependencias entre el mismo par se
            // contradirían y el motor solo podría obedecer a una.
            if ($existing !== null) {
                $existing->update(['type' => $data['type'], 'lag_minutes' => $lag]);

                return $existing;
            }

            return TaskDependency::query()->create([
                'project_id' => $project->id,
                'predecessor_id' => (int) $data['predecessor_id'],
                'successor_id' => $task->id,
                'type' => $data['type'],
                'lag_minutes' => $lag,
            ]);
        });

        // Se prueba el plan **con la liga puesta**. Si no se puede calcular, la
        // culpable es esta: se quita y se dice, en vez de dejar el proyecto sin
        // fechas hasta que alguien adivine cuál fue.
        if ($this->scheduler->reschedule($project->refresh()) === null) {
            $link->forceDelete();
            $this->scheduler->reschedule($project->refresh());

            return back()->with('error', __('tasks.dependency_would_loop'));
        }

        return back()->with('status', __('tasks.dependency_added'));
    }

    public function destroy(Project $project, Task $task, TaskDependency $dependency): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        // Solo las que llegan a esta tarea. Borrar desde aquí una que sale de
        // ella tocaría el plan de otra tarea sin que su pantalla lo enseñe.
        abort_unless($dependency->successor_id === $task->id, 404);

        $dependency->forceDelete();

        $this->scheduler->reschedule($project->refresh());

        return back()->with('status', __('tasks.dependency_removed'));
    }
}
