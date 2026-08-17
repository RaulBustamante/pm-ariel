<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Project;
use App\Models\Task;
use App\Support\Scheduling\WorkingCalendar;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Los números del proyecto, calculados en un solo lugar.
 *
 * Cada indicador viene con **de dónde salió**. Un tablero que muestra «62 %» sin
 * decir de qué es 62 % obliga a creerle o a desconfiar, y la gente acaba
 * desconfiando — sobre todo cuando el número no cuadra con su intuición.
 */
final class ProjectDashboard
{
    /**
     * @return array{
     *     progress: float, task_count: int, done: int, doing: int, todo: int,
     *     critical: int, overdue: int, cost: float, cost_done: float,
     *     start: ?DateTimeImmutable, finish: ?DateTimeImmutable, elapsed_percent: float
     * }
     */
    public function kpis(Project $project): array
    {
        $leaves = $this->leaves($project);
        $today = new DateTimeImmutable('today');

        $start = $project->planned_start === null
            ? null
            : DateTimeImmutable::createFromInterface($project->planned_start);

        $finishRaw = $leaves->max('early_finish');
        $finish = $finishRaw === null ? null : DateTimeImmutable::createFromInterface($finishRaw);

        // Avance ponderado por duración. Contar tareas terminadas sobre el total
        // trata igual a una de un día y a una de un mes, y da un número que
        // suena bien y no significa nada.
        $totalMinutes = (float) $leaves->sum('duration_minutes');
        $doneMinutes = $leaves->sum(
            fn (Task $task): float => (float) $task->duration_minutes * ((float) $task->percent_complete / 100),
        );

        return [
            'progress' => $totalMinutes > 0 ? round($doneMinutes / $totalMinutes * 100, 1) : 0.0,
            'task_count' => $leaves->count(),
            'done' => $leaves->filter(fn (Task $t): bool => (float) $t->percent_complete >= 100)->count(),
            'doing' => $leaves->filter(fn (Task $t): bool => (float) $t->percent_complete > 0 && (float) $t->percent_complete < 100)->count(),
            'todo' => $leaves->filter(fn (Task $t): bool => (float) $t->percent_complete <= 0)->count(),
            'critical' => $leaves->filter(fn (Task $t): bool => (bool) $t->is_critical)->count(),
            'overdue' => $leaves->filter(
                fn (Task $t): bool => $t->early_finish !== null
                    && $t->early_finish->lt($today)
                    && (float) $t->percent_complete < 100,
            )->count(),
            'cost' => (float) $leaves->sum('cost'),
            'cost_done' => (float) $leaves->sum(
                fn (Task $task): float => (float) $task->cost * ((float) $task->percent_complete / 100),
            ),
            'start' => $start,
            'finish' => $finish,
            'elapsed_percent' => $this->elapsedPercent($start, $finish, $today),
        ];
    }

    /**
     * La curva S: cuánto trabajo debería estar hecho a cada corte, contra cuánto
     * lo está de verdad.
     *
     * Se acumula por semana. La curva planeada sale de las fechas del motor; la
     * real, del avance capturado. Donde se separan es exactamente donde el
     * proyecto se está atrasando, y verlo en una línea ahorra la discusión.
     *
     * @return array{labels: list<string>, planned: list<float>, actual: list<float>, max: float}
     */
    public function sCurve(Project $project, ?WorkingCalendar $calendar = null): array
    {
        $leaves = $this->leaves($project)
            ->filter(fn (Task $t): bool => $t->early_start !== null && $t->early_finish !== null);

        if ($leaves->isEmpty()) {
            return ['labels' => [], 'planned' => [], 'actual' => [], 'max' => 0.0];
        }

        $first = DateTimeImmutable::createFromInterface($leaves->min('early_start'))->modify('monday this week');
        $last = DateTimeImmutable::createFromInterface($leaves->max('early_finish'));
        $today = new DateTimeImmutable('today');

        $labels = [];
        $planned = [];
        $actual = [];

        $cumulativePlanned = 0.0;
        $cumulativeActual = 0.0;
        $cursor = $first;

        for ($week = 0; $week < 260 && $cursor <= $last; $week++) {
            $cutoff = $cursor->modify('+6 days')->setTime(23, 59);

            foreach ($leaves as $task) {
                $finish = DateTimeImmutable::createFromInterface($task->early_finish);

                // Planeado: el trabajo cae completo en la semana en que la tarea
                // termina. Repartirlo día a día daría una curva más suave y una
                // precisión que el dato no tiene.
                if ($finish > $cursor->setTime(0, 0)->modify('-1 second') && $finish <= $cutoff) {
                    $cumulativePlanned += (float) $task->duration_minutes;
                    $cumulativeActual += (float) $task->duration_minutes * ((float) $task->percent_complete / 100);
                }
            }

            $labels[] = $cursor->format('d/m');
            $planned[] = round($cumulativePlanned, 1);
            // La curva real se detiene en hoy: dibujarla hacia el futuro sería
            // afirmar un avance que todavía no ocurre.
            $actual[] = $cursor <= $today ? round($cumulativeActual, 1) : null;

            $cursor = $cursor->modify('+7 days');
        }

        return [
            'labels' => $labels,
            'planned' => $planned,
            'actual' => array_values(array_filter($actual, fn (?float $v): bool => $v !== null)),
            'max' => $cumulativePlanned > 0 ? $cumulativePlanned : 1.0,
        ];
    }

    /**
     * @return Collection<int, Task>
     */
    private function leaves(Project $project): Collection
    {
        return $project->tasks()->where('is_summary', false)->get();
    }

    private function elapsedPercent(?DateTimeImmutable $start, ?DateTimeImmutable $finish, DateTimeImmutable $today): float
    {
        if ($start === null || $finish === null || $finish <= $start) {
            return 0.0;
        }

        if ($today <= $start) {
            return 0.0;
        }

        if ($today >= $finish) {
            return 100.0;
        }

        $total = $finish->getTimestamp() - $start->getTimestamp();
        $elapsed = $today->getTimestamp() - $start->getTimestamp();

        return round($elapsed / $total * 100, 1);
    }
}
