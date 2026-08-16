<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ScheduleRun;
use App\Models\Task;
use App\Support\Scheduling\CircularDependencyException;
use App\Support\Scheduling\ScheduleNetwork;
use App\Support\Scheduling\Scheduler;
use App\Support\Scheduling\ScheduleResult;
use App\Support\Scheduling\WorkingCalendar;
use App\Support\Scheduling\WorkShift;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * El puente entre el motor y la base de datos.
 *
 * El motor no sabe que existe Eloquent y así debe seguir. Esta clase lee el
 * proyecto, lo convierte en datos puros, llama al cálculo, escribe el resultado
 * y deja constancia en `schedule_runs`.
 *
 * **Todo en una transacción.** Un cálculo a medias deja un plan donde unas
 * tareas tienen fechas nuevas y otras viejas — y eso no se nota hasta que
 * alguien se guía por él.
 */
final class ProjectScheduler
{
    public function __construct(
        private readonly Scheduler $scheduler = new Scheduler,
    ) {}

    /**
     * Recalcula y guarda. Devuelve el resultado, o null si el plan tiene un
     * ciclo — en cuyo caso el `schedule_run` guarda cuál es.
     */
    public function reschedule(Project $project): ?ScheduleResult
    {
        $tasks = Task::query()->where('project_id', $project->id)->orderBy('sort_order')->get();

        if ($tasks->isEmpty()) {
            return null;
        }

        [$calendar, $extraCalendars, $keyByTaskId] = $this->calendarsFor($project);

        $network = new ScheduleNetwork(
            $tasks->map(fn (Task $task) => $task->toNode($keyByTaskId[$task->id] ?? null))->all(),
            $project->taskDependencies()->get()->map->toLink()->all(),
        );

        $projectStart = $this->startOf($project, $calendar);

        try {
            $result = $this->scheduler->schedule($network, $projectStart, $calendar, $extraCalendars);
        } catch (CircularDependencyException $exception) {
            ScheduleRun::query()->create([
                'project_id' => $project->id,
                'project_start' => $projectStart,
                'task_count' => $tasks->count(),
                'status' => ScheduleRun::STATUS_FAILED,
                'failure_reason' => $exception->getMessage(),
                'failure_cycle' => $exception->cycle(),
                'triggered_by' => Auth::id(),
            ]);

            return null;
        }

        DB::transaction(function () use ($project, $result, $projectStart, $tasks): void {
            foreach ($result->tasks as $id => $scheduled) {
                // `update` sobre el constructor de consultas y no sobre el
                // modelo: son campos derivados, no los toca el usuario, y no
                // tiene sentido llenar la bitácora de auditoría con cada
                // recálculo — serían miles de renglones sin información.
                Task::query()->whereKey((int) $id)->update([
                    'wbs_code' => $scheduled->wbsCode,
                    'early_start' => $scheduled->earlyStart,
                    'early_finish' => $scheduled->earlyFinish,
                    'late_start' => $scheduled->lateStart,
                    'late_finish' => $scheduled->lateFinish,
                    'total_float_minutes' => $scheduled->totalFloatMinutes,
                    'free_float_minutes' => $scheduled->freeFloatMinutes,
                    'is_critical' => $scheduled->isCritical,
                    'is_summary' => $scheduled->isSummary,
                ]);
            }

            ScheduleRun::query()->create([
                'project_id' => $project->id,
                'project_start' => $projectStart,
                'project_finish' => $result->projectFinish,
                'task_count' => $tasks->count(),
                'critical_task_count' => count($result->criticalTaskIds),
                'elapsed_ms' => $result->elapsedMilliseconds,
                'status' => ScheduleRun::STATUS_OK,
                'triggered_by' => Auth::id(),
            ]);
        });

        return $result;
    }

    /**
     * El calendario del proyecto, los de las tareas con calendario propio, y de
     * qué clave usa cada tarea.
     *
     * @return array{0: WorkingCalendar, 1: array<string, WorkingCalendar>, 2: array<int, string>}
     */
    private function calendarsFor(Project $project): array
    {
        $calendars = Calendar::query()->where('project_id', $project->id)->get();

        $default = $calendars->firstWhere('is_default', true) ?? $calendars->first();

        $projectCalendar = $default?->toWorkingCalendar() ?? WorkingCalendar::standard(
            [WorkShift::fromTimes('09:00', '18:00')],
            new DateTimeZone((string) config('app.timezone', 'UTC')),
        );

        $extra = [];
        $keyByTaskId = [];

        foreach ($calendars as $calendar) {
            if ($default !== null && $calendar->id === $default->id) {
                continue;
            }

            $extra[$calendar->key] = $calendar->toWorkingCalendar();
        }

        foreach (Task::query()->where('project_id', $project->id)->whereNotNull('calendar_id')->get() as $task) {
            $calendar = $calendars->firstWhere('id', $task->calendar_id);

            if ($calendar !== null && ($default === null || $calendar->id !== $default->id)) {
                $keyByTaskId[$task->id] = $calendar->key;
            }
        }

        return [$projectCalendar, $extra, $keyByTaskId];
    }

    private function startOf(Project $project, WorkingCalendar $calendar): DateTimeImmutable
    {
        $configured = $project->planned_start;

        // Sin fecha de arranque capturada habría que suponer "hoy", y entonces
        // el mismo plan daría fechas distintas cada día que alguien lo abriera.
        // Se usa como respaldo, pero es un dato que el proyecto debería tener.
        $start = $configured instanceof DateTimeInterface
            ? DateTimeImmutable::createFromInterface($configured)
            : new DateTimeImmutable('today 09:00', $calendar->timezone());

        return $calendar->nextWorkingInstant($start);
    }
}
