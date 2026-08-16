<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * El método de la ruta crítica, de principio a fin.
 *
 * Ordenar → adelante → atrás → holguras → crítica → resumen → WBS. Cada paso es
 * una clase propia con su prueba; esta solo los encadena, y no sabe cómo
 * funciona ninguno.
 *
 * No toca la base de datos. Recibe datos puros y devuelve datos puros, que es lo
 * que permite calcular un escenario hipotético — "¿y si esta tarea dura el
 * doble?" — sin escribir nada ni ensuciar el plan real.
 */
final class Scheduler
{
    public function __construct(
        private readonly TopologicalSorter $sorter = new TopologicalSorter,
        private readonly ForwardPass $forward = new ForwardPass,
        private readonly BackwardPass $backward = new BackwardPass,
        private readonly FloatCalculator $floats = new FloatCalculator,
        private readonly CriticalPathResolver $critical = new CriticalPathResolver,
        private readonly SummaryRollup $rollup = new SummaryRollup,
        private readonly WbsNumberer $wbs = new WbsNumberer,
    ) {}

    /**
     * @param  array<string, WorkingCalendar>  $extraCalendars  Calendarios por clave, para tareas con calendario propio.
     *
     * @throws CircularDependencyException
     */
    public function schedule(
        ScheduleNetwork $network,
        DateTimeImmutable $projectStart,
        WorkingCalendar $calendar,
        array $extraCalendars = [],
    ): ScheduleResult {
        $startedAt = hrtime(as_number: true);

        $calendars = ['default' => $calendar, ...$extraCalendars];

        $order = $this->sorter->sort($network);

        if ($order === []) {
            throw new InvalidArgumentException('No hay tareas que programar.');
        }

        $early = $this->forward->run($network, $order, $projectStart, $calendars);

        $projectFinish = $this->latest($early['finishes']);

        $late = $this->backward->run(
            $network,
            $order,
            $early['starts'],
            $early['finishes'],
            $projectFinish,
            $calendars,
        );

        $floats = $this->floats->run(
            $network,
            $order,
            $early['starts'],
            $early['finishes'],
            $late['starts'],
            $late['finishes'],
            $calendars,
        );

        $criticalIds = $this->critical->criticalTasks($order, $floats['total']);
        $isCritical = array_flip($criticalIds);

        $scheduled = [];

        foreach ($order as $id) {
            $task = $network->task($id);

            // Una tarea "lo más tarde posible" se corre a sus fechas tardías. No
            // es un caso aparte del cálculo: es el mismo resultado, leído por el
            // otro extremo.
            $useLate = $task->constraint()->type === ConstraintType::AsLateAsPossible;

            $scheduled[$id] = new ScheduledTask(
                id: $id,
                earlyStart: $useLate ? $late['starts'][$id] : $early['starts'][$id],
                earlyFinish: $useLate ? $late['finishes'][$id] : $early['finishes'][$id],
                lateStart: $late['starts'][$id],
                lateFinish: $late['finishes'][$id],
                totalFloatMinutes: $floats['total'][$id],
                freeFloatMinutes: $floats['free'][$id],
                isCritical: isset($isCritical[$id]),
                isSummary: false,
                wbsCode: null,
                cost: $task->cost,
                percentComplete: $task->percentComplete,
            );
        }

        $scheduled = $this->rollup->run($network, $scheduled);

        foreach ($this->wbs->number($network) as $id => $code) {
            if (isset($scheduled[$id])) {
                $scheduled[$id] = $scheduled[$id]->with(wbsCode: $code);
            }
        }

        return new ScheduleResult(
            tasks: $scheduled,
            projectStart: $this->earliest(array_map(
                fn (ScheduledTask $t): DateTimeImmutable => $t->earlyStart,
                $scheduled,
            )),
            projectFinish: $projectFinish,
            criticalTaskIds: $criticalIds,
            criticalPaths: $this->critical->criticalPaths($network, $order, $floats['total']),
            elapsedMilliseconds: round((hrtime(as_number: true) - $startedAt) / 1_000_000, 3),
        );
    }

    /**
     * @param  array<string, DateTimeImmutable>  $dates
     */
    private function latest(array $dates): DateTimeImmutable
    {
        $highest = null;

        foreach ($dates as $date) {
            if ($highest === null || $date > $highest) {
                $highest = $date;
            }
        }

        return $highest ?? throw new InvalidArgumentException('No hay fechas que comparar.');
    }

    /**
     * @param  array<string, DateTimeImmutable>  $dates
     */
    private function earliest(array $dates): DateTimeImmutable
    {
        $lowest = null;

        foreach ($dates as $date) {
            if ($lowest === null || $date < $lowest) {
                $lowest = $date;
            }
        }

        return $lowest ?? throw new InvalidArgumentException('No hay fechas que comparar.');
    }
}
