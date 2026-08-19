<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Attachment;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Services\Scheduling\ProjectScheduler;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Reporting\TaskTimeline;
use App\Support\Scheduling\DependencyExpression;
use App\Support\Scheduling\DurationParser;
use App\Support\Scheduling\ProjectDurations;
use App\Support\Scheduling\TaskFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * La vista Lista: la pantalla donde de verdad se construye un plan.
 *
 * Cada operación que cambia el plan **recalcula al terminar**. Un plan con
 * fechas viejas junto a fechas nuevas es peor que uno sin fechas: parece
 * confiable y no lo es.
 */
final class TaskController extends Controller
{
    /** Renglones que se dibujan de una vez en la vista Lista. */
    private const MAX_ROWS = 300;

    public function __construct(
        private readonly TaskOutliner $outliner,
        private readonly ProjectScheduler $scheduler,
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $outline = $this->outliner->outline($project);
        $durations = ProjectDurations::for($project);

        $filter = TaskFilter::fromRequest($request);
        $visible = $filter->apply($outline, auth()->id());

        // Tope de renglones dibujados. Mil renglones de formulario en una sola
        // pagina son varios megabytes de HTML y un navegador que se arrastra;
        // el motor aguanta 2,000 tareas en 220 ms, pero el DOM no.
        $capped = $visible->count() > self::MAX_ROWS;

        return view('tasks.index', [
            'project' => $project,
            'tasks' => $capped ? $visible->take(self::MAX_ROWS) : $visible,
            'filter' => $filter,
            'visibleCount' => $visible->count(),
            'totalCount' => $outline->count(),
            'capped' => $capped,
            'maxRows' => self::MAX_ROWS,
            'durations' => $durations,
            'predecessorText' => $this->predecessorTextFor($project, $outline, $durations),
            'members' => $project->members()->orderBy('name')->get(),
            'lastRun' => $project->scheduleRuns()->first(),
        ]);
    }

    /**
     * El detalle de una tarea: todo lo suyo en un lugar, incluida su historia.
     *
     * La bitácora de auditoría ya existía desde la Etapa 1 y nunca se había
     * mostrado donde importa. «¿Quién cambió esta fecha y cuándo?» es la
     * pregunta que se hace frente a la tarea, no en una pantalla de sistema — y
     * desde la Etapa 9 va mezclada con los comentarios, porque «¿qué pasó aquí?»
     * se contesta con las dos cosas juntas y con ninguna de las dos sola.
     */
    public function show(Project $project, Task $task): View
    {
        $this->authorize('view', $project);
        $this->outliner->assertBelongs($project, $task);

        $task->loadMissing(['owner', 'parent', 'children']);

        return view('tasks.show', [
            'project' => $project,
            'task' => $task,
            'durations' => ProjectDurations::for($project),
            'members' => $project->members()->orderBy('name')->get(),
            // Los calendarios del proyecto. El motor programa cada tarea con el
            // suyo desde la Etapa 3 --`calendarsFor()` lee `tasks.calendar_id`--
            // y no habia forma de escogerlo: un turno de noche o un contratista
            // con jornada distinta no se podian modelar.
            'calendars' => $project->calendars()->orderByDesc('is_default')->orderBy('name')->get(),
            'resources' => Resource::query()->where('project_id', $project->id)->orderBy('name')->get(),
            'assignments' => TaskAssignment::query()->with('resource')->where('task_id', $task->id)->get(),
            'attachments' => Attachment::query()->where('task_id', $task->id)->with('uploader')->latest('id')->get(),
            'predecessors' => $task->predecessorLinks()->with('predecessor')->get(),
            'successors' => $task->successorLinks()->with('successor')->get(),
            // Lo que la gente dijo y lo que el sistema registró, en un hilo.
            'timeline' => app(TaskTimeline::class)->for($task),

            // Con quién se puede ligar: cualquier tarea del proyecto menos ella
            // misma. Los resúmenes sí entran — un paquete puede depender de
            // otro, y es de las formas más limpias de ordenar un plan grande.
            'candidateTasks' => Task::query()
                ->where('project_id', $project->id)
                ->whereKeyNot($task->id)
                ->orderBy('sort_order')
                ->get(),

            // La jornada de este proyecto, para poder decir la espera en días en
            // vez de en minutos de trabajo.
            'dayMinutes' => ProjectDurations::for($project)->toMinutes('1d'),
        ]);
    }

    public function store(StoreTaskRequest $request, Project $project): RedirectResponse
    {
        $task = new Task;
        $task->fill([
            'project_id' => $project->id,
            'name' => $request->string('name')->value(),
            'duration_minutes' => $request->durationMinutes(),
            'constraint_type' => $request->input('constraint_type'),
            'constraint_date' => $request->input('constraint_date'),
            'requested_start' => $request->input('requested_start'),
            'deadline' => $request->input('deadline'),
            'calendar_id' => $request->input('calendar_id'),
            'owner_id' => $request->input('owner_id'),
            'parent_id' => $request->input('parent_id'),
            'sort_order' => (int) Task::query()
                ->where('project_id', $project->id)
                ->where('parent_id', $request->input('parent_id'))
                ->max('sort_order') + 1,
        ]);
        $task->save();

        return $this->afterChange($project, __('tasks.created'));
    }

    public function update(StoreTaskRequest $request, Project $project, Task $task): RedirectResponse
    {
        $this->outliner->assertBelongs($project, $task);

        $task->update([
            'name' => $request->string('name')->value(),
            'duration_minutes' => $request->durationMinutes(),
            'constraint_type' => $request->input('constraint_type'),
            'constraint_date' => $request->input('constraint_date'),
            'requested_start' => $request->has('requested_start')
                ? $request->input('requested_start')
                : $task->requested_start,
            'deadline' => $request->has('deadline')
                ? $request->input('deadline')
                : $task->deadline,
            'owner_id' => $request->input('owner_id'),
            // Como el costo real: `has` y no `??`, para que vaciar las notas
            // de verdad las vacie. La Lista no manda este campo, y con `??`
            // cualquier guardado desde ahi las conservaria por accidente --
            // que es lo correcto, y por eso se distingue una cosa de la otra.
            'description' => $request->has('description')
                ? $request->input('description')
                : $task->description,
            'cost' => $request->input('cost') ?? $task->cost,
            // Se usa `has` y no `??`: sin eso, borrar el campo para decir
            // <<todavia no lo se>> no lo borraria nunca.
            'actual_cost' => $request->has('actual_cost')
                ? $request->input('actual_cost')
                : $task->actual_cost,
            'percent_complete' => $request->input('percent_complete') ?? $task->percent_complete,
        ]);

        if ($request->has('predecessors')) {
            try {
                $this->syncDependencies($project, $task, (string) $request->input('predecessors'));
            } catch (InvalidArgumentException $exception) {
                return back()->withInput()->withErrors(['predecessors' => $exception->getMessage()]);
            }
        }

        return $this->afterChange($project, __('tasks.updated'));
    }

    public function destroy(Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        // Las hijas se van con el padre: dejarlas huérfanas las sacaría del
        // árbol y de la pantalla, sin que nadie las hubiera borrado.
        $task->delete();

        return $this->afterChange($project, __('tasks.deleted'));
    }

    /** indent · outdent · up · down */
    public function outline(Project $project, Task $task, string $action): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        $done = match ($action) {
            'indent' => $this->outliner->indent($task),
            'outdent' => $this->outliner->outdent($task),
            'up' => $this->outliner->move($task, -1),
            'down' => $this->outliner->move($task, 1),
            default => abort(404),
        };

        if (! $done) {
            // No es un error: es que ahí no cabe el movimiento. Decirlo con un
            // aviso evita que el usuario crea que la pantalla se trabó.
            return back()->with('warning', __("tasks.cannot_{$action}"));
        }

        return $this->afterChange($project, __('tasks.moved'));
    }

    public function recalculate(Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        return $this->afterChange($project, __('tasks.recalculated'));
    }

    /**
     * Reescribe las dependencias de una tarea a partir del texto capturado.
     *
     * @throws InvalidArgumentException
     */
    private function syncDependencies(Project $project, Task $task, string $expression): void
    {
        $outline = $this->outliner->outline($project);
        $index = $this->outliner->referenceIndex($outline);

        $parsed = (new DependencyExpression(ProjectDurations::for($project)))->parse($expression, $index);

        foreach ($parsed as $link) {
            if ($link['predecessor_id'] === $task->id) {
                throw new InvalidArgumentException(__('tasks.dependency_on_itself'));
            }
        }

        DB::transaction(function () use ($project, $task, $parsed): void {
            TaskDependency::query()->where('successor_id', $task->id)->delete();

            foreach ($parsed as $link) {
                TaskDependency::query()->create([
                    'project_id' => $project->id,
                    'predecessor_id' => $link['predecessor_id'],
                    'successor_id' => $task->id,
                    'type' => $link['type'],
                    'lag_minutes' => $link['lag_minutes'],
                ]);
            }
        });
    }

    /**
     * El texto de predecesoras de cada tarea, para volver a mostrarlo tal como
     * se escribió.
     *
     * @param  Collection<int, Task>  $outline
     * @return array<int, string>
     */
    private function predecessorTextFor(Project $project, $outline, DurationParser $durations): array
    {
        $referenceById = [];

        foreach ($outline as $position => $task) {
            $referenceById[(int) $task->id] = (string) ($task->wbs_code ?: $position + 1);
        }

        $expression = new DependencyExpression($durations);
        $byTask = [];

        foreach ($project->taskDependencies()->get() as $dependency) {
            $byTask[(int) $dependency->successor_id][] = [
                'reference' => $referenceById[(int) $dependency->predecessor_id] ?? '?',
                'type' => (string) $dependency->type,
                'lag_minutes' => (int) $dependency->lag_minutes,
            ];
        }

        return array_map(
            fn (array $links): string => $expression->format($links),
            $byTask,
        );
    }

    /**
     * Todo cambio recalcula. Si el plan tiene un ciclo, el recálculo devuelve
     * null y se avisa con el ciclo señalado, en vez de dejar fechas viejas
     * conviviendo con nuevas.
     */
    private function afterChange(Project $project, string $status): RedirectResponse
    {
        $result = $this->scheduler->reschedule($project->refresh());

        if ($result === null && $project->tasks()->exists()) {
            $reason = $project->scheduleRuns()->first()?->failure_reason;

            return redirect()
                ->route('projects.tasks.index', $project)
                ->with('error', $reason ?: __('tasks.could_not_calculate'));
        }

        return redirect()->route('projects.tasks.index', $project)->with('status', $status);
    }
}
