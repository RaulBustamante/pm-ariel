<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * El corte de la semana: qué se cerró, qué se atoró, qué sigue.
 *
 * Es un documento distinto de la ficha del proyecto, no una versión corta. La
 * ficha responde «de qué se trata esto» y se manda una vez; esto responde «cómo
 * vamos» y se manda cada lunes a alguien que ya sabe de qué se trata. Mezclarlos
 * daba un documento que no servía para ninguna de las dos cosas: seis páginas de
 * acta constitutiva para preguntar si vamos bien.
 *
 * Las cuatro listas están definidas de forma que **no se traslapen**. Una tarea
 * aparece en una sola: si se cerró, se cerró; si no y ya venció, va en atrasadas;
 * si no y está corriendo, va en curso. Que un renglón salga dos veces obliga a
 * quien lee a compararlas, y entonces ya no es un resumen.
 */
final class WeeklyReport
{
    public function __construct(
        private readonly ProjectDashboard $dashboard,
    ) {}

    /**
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable, next_to: CarbonImmutable,
     *     closed: Collection<int, Task>, late: Collection<int, Task>,
     *     doing: Collection<int, Task>, next: Collection<int, Task>,
     *     kpis: array<string, mixed>, slip_days: ?int, baseline_finish: ?CarbonImmutable
     * }
     */
    public function for(Project $project, ?CarbonImmutable $asOf = null): array
    {
        $today = ($asOf ?? CarbonImmutable::now())->startOfDay();

        // La semana corre de lunes a domingo, no «los últimos siete días»: el
        // reporte se manda el lunes y tiene que hablar de una semana que quien
        // lo recibe reconozca, no de una ventana que se mueve sola.
        $from = $today->startOfWeek();
        $to = $today->endOfWeek();
        $nextTo = $to->addWeek();

        $leaves = $project->tasks()
            ->where('is_summary', false)
            ->orderBy('early_start')
            ->get();

        $closed = $leaves->filter(
            fn (Task $t): bool => $t->actual_finish !== null
                && $t->actual_finish->betweenIncluded($from, $to),
        )->values();

        $stillOpen = $leaves->filter(fn (Task $t): bool => (float) $t->percent_complete < 100);

        $late = $stillOpen->filter(
            fn (Task $t): bool => $t->early_finish !== null && $t->early_finish->lt($today),
        )->sortBy('early_finish')->values();

        $doing = $stillOpen
            ->reject(fn (Task $t): bool => $late->contains('id', $t->id))
            ->filter(fn (Task $t): bool => $t->early_start !== null && $t->early_start->lte($to))
            ->values();

        $next = $stillOpen
            ->reject(fn (Task $t): bool => $late->contains('id', $t->id) || $doing->contains('id', $t->id))
            ->filter(
                fn (Task $t): bool => $t->early_start !== null
                    && $t->early_start->betweenIncluded($to, $nextTo),
            )
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'next_to' => $nextTo,
            'closed' => $closed,
            'late' => $late,
            'doing' => $doing,
            'next' => $next,
            'kpis' => $this->dashboard->kpis($project),
            ...$this->againstBaseline($project),
        ];
    }

    /**
     * Cuánto se ha corrido la entrega contra el plan que se aprobó.
     *
     * Es la cifra que de verdad contesta «cómo vamos»: un avance de 62 % no dice
     * nada si nadie recuerda que en la línea base ese 62 % tocaba hace tres
     * semanas. Sin línea base capturada no se inventa un número — se dice que no
     * hay contra qué comparar.
     *
     * @return array{slip_days: ?int, baseline_finish: ?CarbonImmutable}
     */
    private function againstBaseline(Project $project): array
    {
        $baseline = $project->baselines()->oldest('captured_at')->first();

        if ($baseline === null) {
            return ['slip_days' => null, 'baseline_finish' => null];
        }

        $planned = $baseline->tasks()->max('finish');
        $current = $project->tasks()->where('is_summary', false)->max('early_finish');

        if ($planned === null || $current === null) {
            return ['slip_days' => null, 'baseline_finish' => null];
        }

        $plannedAt = CarbonImmutable::parse((string) $planned)->startOfDay();
        $currentAt = CarbonImmutable::parse((string) $current)->startOfDay();

        return [
            // Con signo: un número negativo significa que se adelantó, y eso
            // también hay que poder decirlo.
            'slip_days' => (int) $plannedAt->diffInDays($currentAt, false),
            'baseline_finish' => $plannedAt,
        ];
    }
}
