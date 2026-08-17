<?php

declare(strict_types=1);

namespace App\Services\Advisor;

use App\Models\Project;
use App\Models\ProjectFinding;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Support\Scheduling\DurationParser;
use App\Support\Scheduling\WorkingCalendar;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lo que el sistema nota sin que nadie se lo pregunte.
 *
 * Cada regla **detecta y explica la causa**. Ninguna propone la acción a tomar
 * (D-017): "este recurso está al 180 % la semana del 3" es aritmética que se
 * puede auditar; "muévelo al martes" es un juicio, y una sugerencia mala en un
 * proyecto real cuesta credibilidad — que sale más caro que no haber sugerido
 * nada.
 *
 * Las reglas son deliberadamente pocas, explicables y verificables a mano. Una
 * regla que nadie puede comprobar es una regla que la gente aprende a ignorar.
 */
final class ProjectAdvisor
{
    /** Encima de esto, alguien está prometiendo más horas de las que tiene. */
    private const OVERALLOCATION_THRESHOLD = 100;

    /**
     * Recalcula todos los hallazgos del proyecto y los guarda.
     *
     * @return Collection<int, ProjectFinding>
     */
    public function analyze(Project $project): Collection
    {
        $project->loadMissing(['tasks.owner']);

        $findings = [
            ...$this->overallocatedResources($project),
            ...$this->duplicateResources($project),
            ...$this->criticalWithoutOwner($project),
            ...$this->negativeFloat($project),
            ...$this->overdueWithoutProgress($project),
            ...$this->milestonesWithoutPredecessors($project),
        ];

        return DB::transaction(function () use ($project, $findings): Collection {
            // Se reemplazan enteros y no se acumulan: un hallazgo que ya se
            // corrigió y sigue en pantalla enseña a la gente a ignorar el panel.
            ProjectFinding::query()->where('project_id', $project->id)->delete();

            $saved = new Collection;

            foreach ($findings as $finding) {
                $record = new ProjectFinding;
                $record->fill(['project_id' => $project->id, 'detected_at' => now(), ...$finding]);
                $record->save();

                $saved->push($record);
            }

            return $saved;
        });
    }

    /**
     * Verde, ámbar o rojo para el proyecto entero.
     *
     * @param  Collection<int, ProjectFinding>  $findings
     */
    public function light(Collection $findings): string
    {
        if ($findings->contains(fn (ProjectFinding $f): bool => $f->isCritical())) {
            return 'red';
        }

        return $findings->isEmpty() ? 'green' : 'amber';
    }

    /**
     * Regla 1 — **recurso sobreasignado**. Dos tareas al 60 % que se traslapan
     * son 120 % de la jornada de alguien, y eso no existe.
     *
     * @return list<array<string, mixed>>
     */
    private function overallocatedResources(Project $project): array
    {
        $assignments = TaskAssignment::query()
            ->whereHas('task', fn ($query) => $query->where('project_id', $project->id))
            ->with(['task', 'resource'])
            ->get()
            ->filter(fn (TaskAssignment $a): bool => $a->task?->early_start !== null && $a->resource !== null);

        $findings = [];

        foreach ($assignments->groupBy('resource_id') as $resourceId => $forResource) {
            $resource = $forResource->first()?->resource;

            if ($resource === null) {
                continue;
            }

            // Se comparan pares que se traslapan en el tiempo. Es cuadrático,
            // pero sobre las asignaciones de una sola persona — que son pocas.
            $peak = 0;
            $culprits = [];

            foreach ($forResource as $assignment) {
                $concurrent = $forResource->filter(
                    fn (TaskAssignment $other): bool => $this->overlaps($assignment->task, $other->task),
                );

                $load = (int) $concurrent->sum('units_percent');

                if ($load > $peak) {
                    $peak = $load;
                    $culprits = $concurrent->map(fn (TaskAssignment $a): string => (string) $a->task?->name)->all();
                }
            }

            if ($peak > max(self::OVERALLOCATION_THRESHOLD, (int) $resource->capacity_percent)) {
                $findings[] = [
                    'rule' => 'resource.overallocated',
                    'severity' => ProjectFinding::SEVERITY_CRITICAL,
                    'resource_id' => (int) $resourceId,
                    'message' => __('advisor.overallocated', [
                        'name' => $resource->name,
                        'percent' => $peak,
                        'tasks' => implode(', ', array_filter($culprits)),
                    ]),
                    'why' => __('advisor.overallocated_why', ['capacity' => $resource->capacity_percent]),
                ];
            }
        }

        return $findings;
    }

    /**
     * Regla 2 — **persona repetida**. Dos recursos con el mismo nombre o el
     * mismo correo son casi siempre la misma persona dada de alta dos veces, y
     * entonces la carga de trabajo que muestra el sistema está partida a la
     * mitad y nadie se entera.
     *
     * @return list<array<string, mixed>>
     */
    private function duplicateResources(Project $project): array
    {
        $resources = Resource::query()->where('project_id', $project->id)->get();
        $findings = [];

        $byName = $resources->groupBy(fn (Resource $r): string => mb_strtolower(trim($r->name)));

        foreach ($byName as $name => $group) {
            if ($group->count() > 1) {
                $findings[] = [
                    'rule' => 'resource.duplicated',
                    'severity' => ProjectFinding::SEVERITY_WARNING,
                    'resource_id' => (int) $group->first()?->id,
                    'message' => __('advisor.duplicated', ['name' => $group->first()?->name, 'count' => $group->count()]),
                    'why' => __('advisor.duplicated_why'),
                ];
            }
        }

        $byEmail = $resources->filter(fn (Resource $r): bool => filled($r->email))
            ->groupBy(fn (Resource $r): string => mb_strtolower(trim((string) $r->email)));

        foreach ($byEmail as $email => $group) {
            if ($group->count() > 1 && mb_strtolower(trim((string) $group->first()?->name)) !== mb_strtolower(trim((string) $group->last()?->name))) {
                $findings[] = [
                    'rule' => 'resource.duplicated_email',
                    'severity' => ProjectFinding::SEVERITY_WARNING,
                    'resource_id' => (int) $group->first()?->id,
                    'message' => __('advisor.duplicated_email', ['email' => $email]),
                    'why' => __('advisor.duplicated_why'),
                ];
            }
        }

        return $findings;
    }

    /**
     * Regla 3 — **tarea crítica sin responsable**. En la ruta crítica no hay
     * margen: una tarea sin nombre encima es una que nadie está empujando.
     *
     * @return list<array<string, mixed>>
     */
    private function criticalWithoutOwner(Project $project): array
    {
        $findings = [];

        $orphans = $project->tasks
            ->where('is_critical', true)
            ->where('is_summary', false)
            ->filter(fn (Task $task): bool => $task->owner_id === null);

        foreach ($orphans as $task) {
            $findings[] = [
                'rule' => 'task.critical_without_owner',
                'severity' => ProjectFinding::SEVERITY_WARNING,
                'task_id' => (int) $task->id,
                'message' => __('advisor.critical_without_owner', ['task' => $task->name]),
                'why' => __('advisor.critical_without_owner_why'),
            ];
        }

        return $findings;
    }

    /**
     * Regla 4 — **holgura negativa**. La tarea ya va tarde contra una fecha
     * comprometida. Es el aviso más importante del panel, y el que más se
     * esconde en las herramientas que recortan la holgura a cero.
     *
     * @return list<array<string, mixed>>
     */
    private function negativeFloat(Project $project): array
    {
        $durations = new DurationParser;
        $findings = [];

        foreach ($project->tasks->where('is_summary', false) as $task) {
            if (($task->total_float_minutes ?? 0) >= 0) {
                continue;
            }

            $findings[] = [
                'rule' => 'task.negative_float',
                'severity' => ProjectFinding::SEVERITY_CRITICAL,
                'task_id' => (int) $task->id,
                'message' => __('advisor.negative_float', [
                    'task' => $task->name,
                    'amount' => $durations->toHuman(abs((int) $task->total_float_minutes)),
                ]),
                'why' => __('advisor.negative_float_why'),
            ];
        }

        return $findings;
    }

    /**
     * Regla 5 — **vencida sin avance**. Debió haber terminado y sigue en cero.
     * No es una opinión: son dos fechas y un número.
     *
     * @return list<array<string, mixed>>
     */
    private function overdueWithoutProgress(Project $project): array
    {
        $findings = [];
        $today = now();

        foreach ($project->tasks->where('is_summary', false) as $task) {
            if ($task->early_finish === null || $task->early_finish->gte($today)) {
                continue;
            }

            if ((float) $task->percent_complete > 0) {
                continue;
            }

            $findings[] = [
                'rule' => 'task.overdue_without_progress',
                'severity' => $task->is_critical
                    ? ProjectFinding::SEVERITY_CRITICAL
                    : ProjectFinding::SEVERITY_WARNING,
                'task_id' => (int) $task->id,
                'message' => __('advisor.overdue', [
                    'task' => $task->name,
                    'date' => $task->early_finish->format('d/m/Y'),
                ]),
                'why' => __('advisor.overdue_why'),
            ];
        }

        return $findings;
    }

    /**
     * Regla 6 — **hito sin predecesoras**. Un hito marca que algo terminó; si
     * nada lo alimenta, se queda pegado al arranque del proyecto y dice que la
     * entrega ocurre el primer día. Es de los errores más comunes al capturar y
     * de los más difíciles de ver en un Gantt largo.
     *
     * @return list<array<string, mixed>>
     */
    private function milestonesWithoutPredecessors(Project $project): array
    {
        $findings = [];

        $milestones = $project->tasks
            ->where('is_summary', false)
            ->filter(fn (Task $task): bool => (int) $task->duration_minutes === 0);

        if ($milestones->isEmpty()) {
            return [];
        }

        $withPredecessor = DB::table('task_dependencies')
            ->whereIn('successor_id', $milestones->pluck('id'))
            ->whereNull('deleted_at')
            ->pluck('successor_id')
            ->all();

        foreach ($milestones as $milestone) {
            if (in_array((int) $milestone->id, array_map(intval(...), $withPredecessor), strict: true)) {
                continue;
            }

            $findings[] = [
                'rule' => 'milestone.without_predecessors',
                'severity' => ProjectFinding::SEVERITY_WARNING,
                'task_id' => (int) $milestone->id,
                'message' => __('advisor.milestone_orphan', ['task' => $milestone->name]),
                'why' => __('advisor.milestone_orphan_why'),
            ];
        }

        return $findings;
    }

    private function overlaps(?Task $a, ?Task $b): bool
    {
        if ($a === null || $b === null || $a->early_start === null || $b->early_start === null) {
            return false;
        }

        // Media abierta: dos tareas donde una termina justo cuando la otra
        // empieza no compiten por la misma hora de nadie.
        return $a->early_start < $b->early_finish && $b->early_start < $a->early_finish;
    }

    /**
     * La carga de cada recurso, para la vista de carga (S-10).
     *
     * @return list<array{resource: resource, peak: int, tasks: int}>
     */
    public function workload(Project $project, ?WorkingCalendar $calendar = null): array
    {
        $rows = [];

        $assignments = TaskAssignment::query()
            ->whereHas('task', fn ($query) => $query->where('project_id', $project->id))
            ->with(['task', 'resource'])
            ->get();

        foreach (Resource::query()->where('project_id', $project->id)->orderBy('name')->get() as $resource) {
            $forResource = $assignments->where('resource_id', $resource->id);

            $peak = 0;

            foreach ($forResource as $assignment) {
                $load = (int) $forResource
                    ->filter(fn (TaskAssignment $other): bool => $this->overlaps($assignment->task, $other->task))
                    ->sum('units_percent');

                $peak = max($peak, $load);
            }

            $rows[] = ['resource' => $resource, 'peak' => $peak, 'tasks' => $forResource->count()];
        }

        return $rows;
    }
}
