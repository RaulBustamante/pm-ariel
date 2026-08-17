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
     *     now: CarbonImmutable, from: CarbonImmutable, to: CarbonImmutable, next_to: CarbonImmutable,
     *     closed: Collection<int, Task>, late: Collection<int, Task>,
     *     doing: Collection<int, Task>, next: Collection<int, Task>,
     *     kpis: array<string, mixed>, slip_days: ?int, baseline_finish: ?CarbonImmutable
     * }
     */
    public function for(Project $project, ?CarbonImmutable $asOf = null): array
    {
        /*
        | El corte se mide contra el **instante** en que se genera, no contra el
        | inicio del día.
        |
        | Importa porque este reporte se manda al cierre del viernes. A esa hora,
        | una tarea que vencía hoy y no está terminada va atrasada, y comparando
        | contra el inicio del día quedaba fuera de la lista: el documento salía
        | diciendo que no hay nada tarde justo el día en que se acaba de acumular.
        |
        | Medido contra el instante real funciona en los dos sentidos sin que haya
        | que elegir un día de emisión: un lunes a las nueve, una tarea que
        | termina ese mismo lunes a las seis todavía no está tarde, y no se
        | reporta como tal.
        */
        $now = $asOf ?? CarbonImmutable::now();
        $today = $now->startOfDay();

        // La semana corre de lunes a domingo, no «los últimos siete días»: tiene
        // que ser una semana que quien recibe el reporte reconozca, no una
        // ventana que se mueve sola según el día en que alguien oprima el botón.
        $from = $today->startOfWeek();
        $to = $today->endOfWeek();
        $nextTo = $to->addWeek();

        // `owner` se trae de una vez: la lista de atrasadas lo pinta en cada
        // renglón, y sin esto el guardia de carga perezosa detiene el reporte.
        $leaves = $project->tasks()
            ->with('owner')
            ->where('is_summary', false)
            ->orderBy('early_start')
            ->get();

        $closed = $leaves->filter(
            fn (Task $t): bool => $t->actual_finish !== null
                && $t->actual_finish->betweenIncluded($from, $to),
        )->values();

        $stillOpen = $leaves->filter(fn (Task $t): bool => (float) $t->percent_complete < 100);

        $late = $stillOpen->filter(
            fn (Task $t): bool => $t->early_finish !== null && $t->early_finish->lt($now),
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
            'now' => $now,
            'from' => $from,
            'to' => $to,
            'next_to' => $nextTo,
            'closed' => $closed,
            'late' => $late,
            'doing' => $doing,
            'next' => $next,
            'focus' => $this->focus($closed, $late, $doing, $next),
            'kpis' => $this->dashboard->kpis($project),
            ...$this->againstBaseline($project),
        ];
    }

    /**
     * Las tareas que salen en el diagrama del corte.
     *
     * No es el proyecto entero: es lo que está en juego ahora mismo. Un Gantt de
     * cincuenta y cuatro renglones dentro de un reporte semanal obliga a buscar,
     * y buscar es justo lo que un resumen tiene que evitar.
     *
     * El orden es el de la urgencia y no el cronológico —lo atrasado arriba—
     * porque es el orden en que se van a tomar decisiones sobre ellas.
     *
     * @param  Collection<int, Task>  $closed
     * @param  Collection<int, Task>  $late
     * @param  Collection<int, Task>  $doing
     * @param  Collection<int, Task>  $next
     * @return Collection<int, Task>
     */
    private function focus(Collection $closed, Collection $late, Collection $doing, Collection $next): Collection
    {
        return $late
            ->concat($doing)
            ->concat($next)
            ->concat($closed)
            ->unique('id')
            // Doce renglones es lo que cabe sin empujar el reporte a una segunda
            // hoja. Pasado eso el diagrama deja de caber y con él la promesa de
            // que esto se lee de un vistazo.
            ->take(12)
            ->values();
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
