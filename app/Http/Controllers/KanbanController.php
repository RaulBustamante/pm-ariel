<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\Scheduling\TaskOutliner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * El tablero.
 *
 * **Las columnas se derivan del avance de la tarea; no hay tarjetas.** Es la
 * decisión CL-004: una tarjeta que puede desincronizarse de su tarea es
 * exactamente el bug que hace que la gente deje de creerle al tablero — el
 * Gantt dice una cosa, el tablero otra, y nadie sabe cuál mirar.
 *
 * Mover una tarjeta cambia el avance de la tarea, que es el mismo dato que ve
 * la vista Lista. Las dos pantallas no pueden discrepar porque miran lo mismo.
 */
final class KanbanController extends Controller
{
    /** Las tres columnas, con el avance que representa cada una. */
    private const COLUMNS = [
        'todo' => 0,
        'doing' => 50,
        'done' => 100,
    ];

    public function __construct(
        private readonly TaskOutliner $outliner,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        // Los resúmenes no van al tablero: no son trabajo que alguien haga, son
        // el encabezado del trabajo. Sí sirven como carriles.
        $tasks = $this->outliner->outline($project)
            ->reject(fn (Task $task): bool => (bool) $task->is_summary)
            ->values();

        $swimlane = (string) $request->query('lane', 'none');

        return view('kanban.show', [
            'project' => $project,
            'columns' => $this->group($tasks),
            'lanes' => $swimlane === 'package' ? $this->byPackage($project, $tasks) : null,
            'swimlane' => $swimlane,
            'total' => $tasks->count(),
        ]);
    }

    /**
     * Mover una tarjeta es capturar avance. No hay un estado aparte que
     * mantener sincronizado.
     */
    public function move(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        $column = (string) $request->input('column');

        abort_unless(array_key_exists($column, self::COLUMNS), 404);

        // De «en curso» a «en curso» no se toca el número: alguien pudo haber
        // capturado 30 % a mano y moverla no debería redondearlo a 50.
        $current = (float) $task->percent_complete;
        $target = self::COLUMNS[$column];

        if ($column === 'doing' && $current > 0 && $current < 100) {
            return back();
        }

        $task->update(['percent_complete' => $target]);

        return back()->with('status', __('kanban.moved', ['task' => $task->name]));
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return array<string, Collection<int, Task>>
     */
    private function group(Collection $tasks): array
    {
        return [
            'todo' => $tasks->filter(fn (Task $t): bool => (float) $t->percent_complete <= 0)->values(),
            'doing' => $tasks->filter(fn (Task $t): bool => (float) $t->percent_complete > 0 && (float) $t->percent_complete < 100)->values(),
            'done' => $tasks->filter(fn (Task $t): bool => (float) $t->percent_complete >= 100)->values(),
        ];
    }

    /**
     * Carriles por paquete: el tablero de un proyecto de sesenta tareas es
     * ilegible sin agrupar.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<array{name: string, columns: array<string, Collection<int, Task>>}>
     */
    private function byPackage(Project $project, Collection $tasks): array
    {
        $names = Task::query()
            ->where('project_id', $project->id)
            ->pluck('name', 'id');

        $lanes = [];

        foreach ($tasks->groupBy(fn (Task $task): int => (int) $task->parent_id) as $parentId => $group) {
            $lanes[] = [
                'name' => $parentId === 0 ? __('kanban.no_package') : (string) ($names[$parentId] ?? '—'),
                'columns' => $this->group($group),
            ];
        }

        return $lanes;
    }
}
