<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Task;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Scheduling\TaskFilter;
use App\Support\Scheduling\WorkingCalendar;
use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * El mes, con las tareas puestas sobre los días que ocupan.
 *
 * Responde la pregunta que ni la Lista ni el Gantt contestan de un vistazo:
 * «¿qué cae esta semana?». Un Gantt de seis meses es excelente para ver la
 * forma del proyecto y pésimo para planear el martes.
 */
final class CalendarViewController extends Controller
{
    public function __construct(
        private readonly TaskOutliner $outliner,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $calendar = $this->workingCalendarFor($project);

        $month = $this->monthFrom((string) $request->query('month', ''), $project);

        $first = $month->modify('first day of this month')->setTime(0, 0);
        $last = $month->modify('last day of this month')->setTime(23, 59);

        // La rejilla siempre arranca en lunes y trae seis semanas: si cambiara de
        // alto según el mes, la pantalla brincaría al navegar entre meses.
        $gridStart = $first->modify('monday this week');

        $tasks = $this->outliner->outline($project)
            ->reject(fn (Task $task): bool => (bool) $task->is_summary)
            ->filter(fn (Task $task): bool => $task->early_start !== null && $task->early_finish !== null)
            ->values();

        $filter = TaskFilter::fromRequest($request);
        $tasks = $filter->apply($tasks, auth()->id());

        return view('calendar.show', [
            'project' => $project,
            'month' => $first,
            'previousMonth' => $first->modify('-1 month')->format('Y-m'),
            'nextMonth' => $first->modify('+1 month')->format('Y-m'),
            'weeks' => $this->weeks($gridStart, $first, $tasks, $calendar),
            'tasks' => $tasks,
            'filter' => $filter,
            'visibleCount' => $tasks->count(),
        ]);
    }

    /**
     * Seis semanas de siete días, cada uno con lo que le toca.
     *
     * @param  Collection<int, Task>  $tasks
     * @return list<list<array{date: DateTimeImmutable, inMonth: bool, working: bool, today: bool, tasks: list<array{task: Task, starts: bool, ends: bool}>}>>
     */
    private function weeks(
        DateTimeImmutable $gridStart,
        DateTimeImmutable $month,
        Collection $tasks,
        WorkingCalendar $calendar,
    ): array {
        $today = new DateTimeImmutable('today');
        $weeks = [];
        $cursor = $gridStart;

        for ($week = 0; $week < 6; $week++) {
            $days = [];

            for ($day = 0; $day < 7; $day++) {
                $days[] = [
                    'date' => $cursor,
                    'inMonth' => $cursor->format('Y-m') === $month->format('Y-m'),
                    'working' => $calendar->isWorkingDay($cursor),
                    'today' => $cursor->format('Y-m-d') === $today->format('Y-m-d'),
                    'tasks' => $this->tasksOn($cursor, $tasks),
                ];

                $cursor = $cursor->modify('+1 day');
            }

            $weeks[] = $days;
        }

        return $weeks;
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return list<array{task: Task, starts: bool, ends: bool}>
     */
    private function tasksOn(DateTimeImmutable $day, Collection $tasks): array
    {
        $key = $day->format('Y-m-d');
        $onThisDay = [];

        foreach ($tasks as $task) {
            $start = $task->early_start?->format('Y-m-d');
            $finish = $task->early_finish?->format('Y-m-d');

            if ($start === null || $finish === null || $key < $start || $key > $finish) {
                continue;
            }

            $onThisDay[] = [
                'task' => $task,
                'starts' => $key === $start,
                'ends' => $key === $finish,
            ];
        }

        return $onThisDay;
    }

    /**
     * El mes que se pidió; si no se pidió ninguno, aquel donde el proyecto tiene
     * trabajo — no el actual, que en un proyecto que arranca en marzo mostraría
     * una rejilla vacía y parecería que no hay nada capturado.
     */
    private function monthFrom(string $requested, Project $project): DateTimeImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $requested) === 1) {
            return new DateTimeImmutable($requested.'-01');
        }

        $anchor = $project->tasks()->whereNotNull('early_start')->min('early_start');

        return $anchor === null
            ? new DateTimeImmutable('first day of this month')
            : new DateTimeImmutable((string) $anchor);
    }

    private function workingCalendarFor(Project $project): WorkingCalendar
    {
        $calendar = $project->calendars()->where('is_default', true)->first()
            ?? $project->calendars()->first();

        return $calendar instanceof Calendar
            ? $calendar->toWorkingCalendar()
            : WorkingCalendar::standard();
    }
}
