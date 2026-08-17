<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Baseline;
use App\Models\BaselineTask;
use App\Models\Project;
use App\Models\Task;
use App\Support\Costing\TaskCost;
use App\Support\Scheduling\WorkingCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Capturar el compromiso y comparar contra él.
 *
 * La varianza se mide **en minutos de trabajo**, no en días de calendario. Que
 * una tarea se recorra "tres días" no dice nada si dos de ellos eran fin de
 * semana: el trabajo no se retrasó, la fecha sí. Medir en tiempo laborable es lo
 * que hace que el número signifique algo al explicarlo en una junta.
 */
final class BaselineManager
{
    /**
     * Congela el plan actual. Varias por proyecto: la original y las que se
     * aprueben después; solo una es la vigente para comparar.
     */
    public function capture(Project $project, string $name, ?string $notes = null, bool $makeActive = true): Baseline
    {
        return DB::transaction(function () use ($project, $name, $notes, $makeActive): Baseline {
            // Las asignaciones vienen cargadas: el costo de cada tarea se
            // calcula sobre ellas, y sin esto el guardia de carga perezosa
            // detiene la captura —o, peor, en produccion haria una consulta por
            // tarea justo cuando se congela un plan de doscientas.
            $tasks = Task::query()
                ->with(['assignments.resource'])
                ->where('project_id', $project->id)
                ->get();

            $baseline = Baseline::query()->create([
                'project_id' => $project->id,
                'name' => $name,
                'notes' => $notes,
                'captured_at' => now(),
                'captured_by' => Auth::id(),
                'project_start' => $tasks->min('early_start'),
                'project_finish' => $tasks->max('early_finish'),
                'total_cost' => $tasks->where('is_summary', false)->sum(
                    fn (Task $task): float => TaskCost::of($task)['total'],
                ),
                'is_active' => false,
            ]);

            foreach ($tasks as $task) {
                BaselineTask::query()->create([
                    'baseline_id' => $baseline->id,
                    'task_id' => $task->id,
                    'wbs_code' => $task->wbs_code,
                    'name' => $task->name,
                    'start' => $task->early_start,
                    'finish' => $task->early_finish,
                    'duration_minutes' => $task->duration_minutes,
                    // El costo **completo**, no solo el capturado a mano.
                    //
                    // Hasta la Etapa 6 el costo de una tarea era la columna que
                    // alguien tecleaba, y congelar eso bastaba. Ahora la mayor
                    // parte sale de los recursos asignados, asi que una linea
                    // base con solo el costo fijo compararia dos cosas distintas
                    // y la varianza saldria casi siempre en cero.
                    'cost' => TaskCost::of($task)['total'],
                ]);
            }

            if ($makeActive) {
                $this->activate($baseline);
            }

            return $baseline->refresh();
        });
    }

    /** Solo una vigente por proyecto: si hubiera dos, ¿contra cuál se compara? */
    public function activate(Baseline $baseline): void
    {
        DB::transaction(function () use ($baseline): void {
            Baseline::query()
                ->where('project_id', $baseline->project_id)
                ->where('id', '!=', $baseline->id)
                ->update(['is_active' => false]);

            $baseline->update(['is_active' => true]);
        });
    }

    /**
     * Varianza por tarea y del proyecto, contra la línea base vigente.
     *
     * @return array{
     *     tasks: list<array{task_id: int, name: string, start_variance_minutes: int, finish_variance_minutes: int, cost_variance: float, is_new: bool}>,
     *     finish_variance_minutes: int,
     *     cost_variance: float,
     *     removed: list<array{task_id: int, name: string}>
     * }
     */
    public function compare(Project $project, Baseline $baseline, WorkingCalendar $calendar): array
    {
        /** @var Collection<int, BaselineTask> $snapshot */
        $snapshot = $baseline->tasks()->get()->keyBy('task_id');

        $current = Task::query()->where('project_id', $project->id)->get();

        $rows = [];
        $seen = [];

        foreach ($current as $task) {
            $before = $snapshot->get($task->id);
            $seen[$task->id] = true;

            if ($before === null) {
                // Una tarea que no existía cuando se congeló el plan. No tiene
                // varianza: tiene la novedad completa, y hay que poder verla.
                $rows[] = [
                    'task_id' => (int) $task->id,
                    'name' => (string) $task->name,
                    'start_variance_minutes' => 0,
                    'finish_variance_minutes' => 0,
                    'cost_variance' => (float) $task->cost,
                    'is_new' => true,
                ];

                continue;
            }

            $rows[] = [
                'task_id' => (int) $task->id,
                'name' => (string) $task->name,
                'start_variance_minutes' => $this->variance($calendar, $before->start, $task->early_start),
                'finish_variance_minutes' => $this->variance($calendar, $before->finish, $task->early_finish),
                'cost_variance' => round((float) $task->cost - (float) $before->cost, 2),
                'is_new' => false,
            ];
        }

        // Lo que se comprometió y ya no está en el plan. Desaparecer del reporte
        // sería la forma más cómoda de cumplir una línea base: borrando lo que
        // no se alcanzó a hacer.
        $removed = [];

        foreach ($snapshot as $taskId => $before) {
            if (! isset($seen[$taskId])) {
                $removed[] = ['task_id' => (int) $taskId, 'name' => (string) $before->name];
            }
        }

        return [
            'tasks' => $rows,
            'finish_variance_minutes' => $this->variance(
                $calendar,
                $baseline->project_finish,
                $current->max('early_finish'),
            ),
            'cost_variance' => round(
                (float) $current->where('is_summary', false)->sum('cost') - (float) $baseline->total_cost,
                2,
            ),
            'removed' => $removed,
        ];
    }

    private function variance(WorkingCalendar $calendar, mixed $baseline, mixed $current): int
    {
        if ($baseline === null || $current === null) {
            return 0;
        }

        return $calendar->workingMinutesBetween(
            \DateTimeImmutable::createFromInterface($baseline),
            \DateTimeImmutable::createFromInterface($current),
        );
    }
}
