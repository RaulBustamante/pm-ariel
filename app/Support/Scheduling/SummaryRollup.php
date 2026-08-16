<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;

/**
 * Las fechas, el costo y el avance de una tarea resumen salen de sus hijas.
 *
 * Nunca al revés, y nunca capturados a mano. Un resumen con fechas propias
 * termina diciendo que el paquete cierra el 10 mientras una de sus tareas
 * termina el 14, y a partir de ahí nadie vuelve a creerle al plan.
 *
 * El avance se pondera por duración, no por número de tareas: dos hijas, una de
 * un día terminada y otra de un mes sin empezar, no es 50 % de avance.
 */
final class SummaryRollup
{
    /**
     * @param  array<string, ScheduledTask>  $scheduled  Solo hojas al entrar.
     * @return array<string, ScheduledTask> Hojas y resúmenes.
     */
    public function run(ScheduleNetwork $network, array $scheduled): array
    {
        foreach ($network->roots() as $rootId) {
            $this->resolve($network, $rootId, $scheduled);
        }

        return $scheduled;
    }

    /**
     * Se resuelve de abajo hacia arriba: un resumen no puede calcularse hasta
     * que sus hijas —que pueden ser resúmenes también— ya están resueltas. De
     * ahí que el anidamiento a tres niveles o a diez dé lo mismo.
     *
     * @param  array<string, ScheduledTask>  $scheduled
     */
    private function resolve(ScheduleNetwork $network, string $id, array &$scheduled): ?ScheduledTask
    {
        if (! $network->isSummary($id)) {
            return $scheduled[$id] ?? null;
        }

        $children = [];

        foreach ($network->childrenOf($id) as $childId) {
            $child = $this->resolve($network, $childId, $scheduled);

            if ($child !== null) {
                $children[] = $child;
            }
        }

        if ($children === []) {
            return null;
        }

        $earlyStart = $this->min(array_map(fn (ScheduledTask $t): DateTimeImmutable => $t->earlyStart, $children));
        $earlyFinish = $this->max(array_map(fn (ScheduledTask $t): DateTimeImmutable => $t->earlyFinish, $children));
        $lateStart = $this->min(array_map(fn (ScheduledTask $t): DateTimeImmutable => $t->lateStart, $children));
        $lateFinish = $this->max(array_map(fn (ScheduledTask $t): DateTimeImmutable => $t->lateFinish, $children));

        $cost = array_sum(array_map(fn (ScheduledTask $t): float => $t->cost, $children));

        // Un resumen es crítico si alguna de sus hijas lo es: basta con que una
        // no pueda retrasarse para que el paquete tampoco pueda.
        $isCritical = false;
        $totalFloat = $children[0]->totalFloatMinutes;

        foreach ($children as $child) {
            $isCritical = $isCritical || $child->isCritical;
            $totalFloat = min($totalFloat, $child->totalFloatMinutes);
        }

        $scheduled[$id] = new ScheduledTask(
            id: $id,
            earlyStart: $earlyStart,
            earlyFinish: $earlyFinish,
            lateStart: $lateStart,
            lateFinish: $lateFinish,
            totalFloatMinutes: $totalFloat,
            freeFloatMinutes: 0,
            isCritical: $isCritical,
            isSummary: true,
            wbsCode: null,
            cost: $cost,
            percentComplete: $this->weightedProgress($network, $children),
        );

        return $scheduled[$id];
    }

    /**
     * @param  list<ScheduledTask>  $children
     */
    private function weightedProgress(ScheduleNetwork $network, array $children): float
    {
        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($children as $child) {
            $weight = (float) ($network->has($child->id) && ! $child->isSummary
                ? $network->task($child->id)->durationMinutes
                : $this->spanOf($network, $child));

            $weightedSum += $child->percentComplete * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal <= 0.0) {
            // Todo son hitos: no hay duración con la que ponderar, así que el
            // promedio simple es lo único honesto.
            return count($children) === 0
                ? 0.0
                : round(array_sum(array_map(fn (ScheduledTask $t): float => $t->percentComplete, $children)) / count($children), 2);
        }

        return round($weightedSum / $weightTotal, 2);
    }

    private function spanOf(ScheduleNetwork $network, ScheduledTask $task): int
    {
        $total = 0;

        foreach ($network->childrenOf($task->id) as $childId) {
            $total += $network->isSummary($childId) ? 0 : $network->task($childId)->durationMinutes;
        }

        return $total;
    }

    /**
     * @param  list<DateTimeImmutable>  $dates
     */
    private function min(array $dates): DateTimeImmutable
    {
        $lowest = $dates[0];

        foreach ($dates as $date) {
            if ($date < $lowest) {
                $lowest = $date;
            }
        }

        return $lowest;
    }

    /**
     * @param  list<DateTimeImmutable>  $dates
     */
    private function max(array $dates): DateTimeImmutable
    {
        $highest = $dates[0];

        foreach ($dates as $date) {
            if ($date > $highest) {
                $highest = $date;
            }
        }

        return $highest;
    }
}
