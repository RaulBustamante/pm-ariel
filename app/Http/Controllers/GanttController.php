<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\Scheduling\ProjectScheduler;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Scheduling\ConstraintType;
use App\Support\Scheduling\GanttLayout;
use App\Support\Scheduling\TaskFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El Gantt.
 *
 * Se dibuja en SVG generado en el servidor, sin biblioteca de terceros y sin
 * JavaScript para el trazo. Tres razones: se imprime bien —que es la mitad del
 * valor de un Gantt en una junta—, funciona con lector de pantalla si se le
 * ponen los rótulos, y no ata el proyecto a una dependencia de terceros que
 * habría que mantener durante años.
 */
final class GanttController extends Controller
{
    public function __construct(
        private readonly TaskOutliner $outliner,
    ) {}

    /**
     * Mover una barra fija la tarea con una restricción de «no empezar antes
     * del…», que es lo que de verdad significa arrastrarla.
     *
     * Es importante que quede como restricción y no como una fecha escrita a
     * mano: así el motor la respeta al recalcular, la holgura muestra el
     * conflicto si lo hay, y la pantalla de detalle dice de dónde salió esa
     * fecha. Una fecha suelta la pisaría el siguiente cálculo sin dejar rastro.
     */
    public function move(Request $request, Project $project, TaskOutliner $outliner, ProjectScheduler $scheduler): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'task' => ['required', 'integer'],
            'days' => ['required', 'integer', 'between:-365,365'],
        ]);

        $task = Task::query()->findOrFail($data['task']);
        $outliner->assertBelongs($project, $task);

        if ($task->is_summary) {
            return back()->with('warning', __('gantt.cannot_move_summary'));
        }

        $start = $task->early_start;

        if ($start === null) {
            return back();
        }

        $moved = $start->copy()->addWeekdays($data['days']);

        $task->update([
            'constraint_type' => ConstraintType::StartNoEarlierThan->value,
            'constraint_date' => $moved,
        ]);

        $scheduler->reschedule($project->refresh());

        return redirect()
            ->route('projects.gantt', ['project' => $project, ...TaskFilter::fromRequest($request)->toQuery()])
            ->with('status', __('gantt.moved', ['task' => $task->name, 'date' => $moved->format('d/m/Y')]));
    }

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->outliner->outline($project)
            ->filter(fn (Task $task): bool => $task->early_start !== null && $task->early_finish !== null)
            ->values();

        $filter = TaskFilter::fromRequest($request);
        $tasks = $filter->apply($tasks, auth()->id());

        $zoom = (string) $request->query('zoom', GanttLayout::ZOOM_WEEK);

        $layout = new GanttLayout($tasks, $zoom);

        // La línea base vigente, indexada por tarea. Dibujarla debajo de la barra
        // real es lo que convierte el Gantt en un reporte de avance: sin ella
        // muestra el plan de hoy y nadie recuerda cuál era el de hace un mes.
        $baseline = $project->baselines()->where('is_active', true)->first();

        return view('gantt.show', [
            'project' => $project,
            'tasks' => $tasks,
            'layout' => $layout,
            'zoom' => $layout->zoom,
            'filter' => $filter,
            'visibleCount' => $tasks->count(),
            'dependencies' => $project->taskDependencies()->get(),
            'baseline' => $baseline,
            'baselineByTask' => $baseline === null
                ? []
                : $baseline->tasks()->get()->keyBy('task_id')->all(),
        ]);
    }
}
