<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;

/**
 * Las dos holguras, que no son lo mismo y se confunden todo el tiempo.
 *
 * - **Total:** cuánto puede retrasarse la tarea sin mover el fin del proyecto.
 * - **Libre:** cuánto puede retrasarse sin mover a **ninguna sucesora**.
 *
 * La libre nunca es mayor que la total, y suele ser bastante menor. La
 * distinción es práctica, no académica: con holgura total de dos semanas y
 * holgura libre de cero, retrasar un día no mueve la entrega pero sí obliga a
 * avisarle a alguien hoy.
 *
 * La holgura total negativa no se recorta a cero. Significa que la tarea ya va
 * tarde contra una fecha comprometida, y esconderlo detrás de un cero es
 * exactamente cómo un proyecto llega tarde sin que nadie lo viera venir.
 */
final class FloatCalculator
{
    /**
     * @param  list<string>  $order
     * @param  array<string, DateTimeImmutable>  $earlyStarts
     * @param  array<string, DateTimeImmutable>  $earlyFinishes
     * @param  array<string, DateTimeImmutable>  $lateStarts
     * @param  array<string, DateTimeImmutable>  $lateFinishes
     * @param  array<string, WorkingCalendar>  $calendars
     * @return array{total: array<string, int>, free: array<string, int>}
     */
    public function run(
        ScheduleNetwork $network,
        array $order,
        array $earlyStarts,
        array $earlyFinishes,
        array $lateStarts,
        array $lateFinishes,
        array $calendars,
    ): array {
        $total = [];
        $free = [];

        foreach ($order as $id) {
            $task = $network->task($id);
            $calendar = $calendars[$task->calendarKey ?? 'default'] ?? $calendars['default'];

            $total[$id] = $calendar->workingMinutesBetween($earlyStarts[$id], $lateStarts[$id]);

            $successors = $network->outgoingOf($id);

            if ($successors === []) {
                // Sin sucesoras no hay a quién estorbar: lo único que la limita
                // es el fin del proyecto, que es justo la holgura total.
                $free[$id] = $total[$id];

                continue;
            }

            // Se arranca en el infinito práctico y se va bajando: la lista de
            // sucesoras no está vacía, así que siempre queda un valor real.
            $slack = PHP_INT_MAX;

            foreach ($successors as $link) {
                // Desde qué extremo propio se mide, y contra qué extremo de la
                // sucesora. Mover la tarea desplaza sus dos extremos por igual,
                // así que el resultado se lee como "cuánto puedo recorrerme".
                $mine = $link->type->readsPredecessorFinish() ? $earlyFinishes[$id] : $earlyStarts[$id];
                $theirs = $link->type->drivesSuccessorStart()
                    ? $earlyStarts[$link->successorId]
                    : $earlyFinishes[$link->successorId];

                $available = $calendar->workingMinutesBetween(
                    $calendar->addWorkingMinutes($mine, $link->lagMinutes),
                    $theirs,
                );

                $slack = min($slack, $available);
            }

            $free[$id] = $slack;
        }

        return ['total' => $total, 'free' => $free];
    }
}
