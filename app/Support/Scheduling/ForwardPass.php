<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;

/**
 * Lo más pronto que puede pasar cada cosa.
 *
 * Se recorre en orden topológico, así que al llegar a una tarea sus
 * predecesoras ya están resueltas y nunca hay que volver atrás.
 *
 * Las cuatro dependencias se reducen a dos preguntas: **de qué extremo de la
 * predecesora se mide** y **sobre qué extremo de la sucesora cae**. Con eso, FS,
 * SS, FF y SF son el mismo cálculo con dos interruptores, en vez de cuatro
 * ramas que hay que mantener sincronizadas.
 */
final class ForwardPass
{
    /**
     * @param  list<string>  $order
     * @param  array<string, WorkingCalendar>  $calendars  Por clave; 'default' es el del proyecto.
     * @return array{starts: array<string, DateTimeImmutable>, finishes: array<string, DateTimeImmutable>}
     */
    public function run(
        ScheduleNetwork $network,
        array $order,
        DateTimeImmutable $projectStart,
        array $calendars,
    ): array {
        $starts = [];
        $finishes = [];

        foreach ($order as $id) {
            $task = $network->task($id);
            $calendar = $this->calendarFor($task, $calendars);

            $earliestStart = null;
            $earliestFinish = null;

            foreach ($network->incomingOf($id) as $link) {
                $anchor = $link->type->readsPredecessorFinish()
                    ? $finishes[$link->predecessorId]
                    : $starts[$link->predecessorId];

                // El lag se cuenta en tiempo de trabajo, no en horas de reloj:
                // "+2 días" después de un viernes cae en martes, no en domingo.
                $driven = $calendar->addWorkingMinutes($anchor, $link->lagMinutes);

                if ($link->type->drivesSuccessorStart()) {
                    $earliestStart = $this->later($earliestStart, $driven);
                } else {
                    $earliestFinish = $this->later($earliestFinish, $driven);
                }
            }

            // Un fin exigido se traduce a inicio restando la duración, y así
            // todo el resto del cálculo trabaja con un solo número.
            if ($earliestFinish !== null) {
                $earliestStart = $this->later(
                    $earliestStart,
                    $calendar->subtractWorkingMinutes($earliestFinish, $task->durationMinutes),
                );
            }

            $start = $earliestStart ?? $projectStart;

            // Nada empieza antes que el proyecto. Un adelanto mayor que la
            // duración de la predecesora, o una liga de inicio a fin, pueden
            // producir una fecha anterior al arranque; dejarla pasar haría que
            // el proyecto apareciera empezando antes de existir.
            $start = $this->later($start, $projectStart);

            // Una fecha elegida por la persona funciona como "no antes de":
            // respeta su decisión sin romper una dependencia que obligue a
            // comenzar después.
            if ($task->requestedStart !== null) {
                $start = $this->later($start, $task->requestedStart);
            }

            $start = $this->applyConstraint($task, $start, $calendar);

            // Las tareas con trabajo real no empiezan fuera de la jornada; los
            // hitos se quedan donde los pusieron (convenio de WorkingCalendar).
            if ($task->durationMinutes > 0 || $earliestStart === null) {
                $start = $calendar->nextWorkingInstant($start);
            }

            $starts[$id] = $start;
            $finishes[$id] = $calendar->addWorkingMinutes($start, $task->durationMinutes);
        }

        return ['starts' => $starts, 'finishes' => $finishes];
    }

    /**
     * Solo las restricciones que empujan hacia adelante. Las que aprietan por
     * detrás son del recorrido hacia atrás, y ahí es donde aparece la holgura
     * negativa que delata el conflicto.
     */
    private function applyConstraint(TaskNode $task, DateTimeImmutable $start, WorkingCalendar $calendar): DateTimeImmutable
    {
        $constraint = $task->constraint();

        if (! $constraint->type->affectsForwardPass() || $constraint->date === null) {
            return $start;
        }

        return match ($constraint->type) {
            // "No antes de" empuja, nunca adelanta: si la tarea ya iba después,
            // la restricción no tiene nada que decir.
            ConstraintType::StartNoEarlierThan => $this->later($start, $constraint->date),
            ConstraintType::FinishNoEarlierThan => $this->later(
                $start,
                $calendar->subtractWorkingMinutes($constraint->date, $task->durationMinutes),
            ),
            // Las rígidas ganan sobre las predecesoras. El conflicto que eso
            // pueda causar se ve después, en la holgura.
            ConstraintType::MustStartOn => $constraint->date,
            ConstraintType::MustFinishOn => $calendar->subtractWorkingMinutes($constraint->date, $task->durationMinutes),
            default => $start,
        };
    }

    /**
     * @param  array<string, WorkingCalendar>  $calendars
     */
    private function calendarFor(TaskNode $task, array $calendars): WorkingCalendar
    {
        return $calendars[$task->calendarKey ?? 'default'] ?? $calendars['default'];
    }

    private function later(?DateTimeImmutable $current, DateTimeImmutable $candidate): DateTimeImmutable
    {
        return $current === null || $candidate > $current ? $candidate : $current;
    }
}
