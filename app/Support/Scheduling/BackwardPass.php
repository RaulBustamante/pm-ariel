<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;

/**
 * Lo más tarde que puede pasar cada cosa sin mover el fin del proyecto.
 *
 * Es el espejo del recorrido hacia adelante: mismo orden al revés, `min` donde
 * allá había `max`, y restar donde allá se sumaba. Cuando algo aquí sale antes
 * que su fecha temprana, la holgura se vuelve negativa — y eso no es un error
 * del cálculo, es el conflicto real que alguien metió con una fecha rígida.
 */
final class BackwardPass
{
    /**
     * @param  list<string>  $order  El mismo del recorrido hacia adelante.
     * @param  array<string, DateTimeImmutable>  $earlyStarts
     * @param  array<string, DateTimeImmutable>  $earlyFinishes
     * @param  array<string, WorkingCalendar>  $calendars
     * @return array{starts: array<string, DateTimeImmutable>, finishes: array<string, DateTimeImmutable>}
     */
    public function run(
        ScheduleNetwork $network,
        array $order,
        array $earlyStarts,
        array $earlyFinishes,
        DateTimeImmutable $projectFinish,
        array $calendars,
    ): array {
        $lateStarts = [];
        $lateFinishes = [];

        foreach (array_reverse($order) as $id) {
            $task = $network->task($id);
            $calendar = $this->calendarFor($task, $calendars);

            $latestFinish = null;
            $latestStart = null;

            foreach ($network->outgoingOf($id) as $link) {
                // Del lado de la sucesora se lee el extremo que la liga gobierna.
                $anchor = $link->type->drivesSuccessorStart()
                    ? $lateStarts[$link->successorId]
                    : $lateFinishes[$link->successorId];

                $driven = $calendar->subtractWorkingMinutes($anchor, $link->lagMinutes);

                // Y del lado de la predecesora, el extremo desde el que se mide.
                if ($link->type->readsPredecessorFinish()) {
                    $latestFinish = $this->earlier($latestFinish, $driven);
                } else {
                    $latestStart = $this->earlier($latestStart, $driven);
                }
            }

            if ($latestStart !== null) {
                $latestFinish = $this->earlier(
                    $latestFinish,
                    $calendar->addWorkingMinutes($latestStart, $task->durationMinutes),
                );
            }

            $finish = $latestFinish ?? $projectFinish;
            $finish = $this->applyConstraint($task, $finish, $calendar);

            $lateFinishes[$id] = $finish;
            $lateStarts[$id] = $calendar->subtractWorkingMinutes($finish, $task->durationMinutes);
        }

        return ['starts' => $lateStarts, 'finishes' => $lateFinishes];
    }

    private function applyConstraint(TaskNode $task, DateTimeImmutable $finish, WorkingCalendar $calendar): DateTimeImmutable
    {
        $constraint = $task->constraint();

        if (! $constraint->type->affectsBackwardPass() || $constraint->date === null) {
            return $finish;
        }

        return match ($constraint->type) {
            ConstraintType::StartNoLaterThan => $this->earlier(
                $finish,
                $calendar->addWorkingMinutes($constraint->date, $task->durationMinutes),
            ),
            ConstraintType::FinishNoLaterThan => $this->earlier($finish, $constraint->date),
            ConstraintType::MustStartOn => $calendar->addWorkingMinutes($constraint->date, $task->durationMinutes),
            ConstraintType::MustFinishOn => $constraint->date,
            default => $finish,
        };
    }

    /**
     * @param  array<string, WorkingCalendar>  $calendars
     */
    private function calendarFor(TaskNode $task, array $calendars): WorkingCalendar
    {
        return $calendars[$task->calendarKey ?? 'default'] ?? $calendars['default'];
    }

    private function earlier(?DateTimeImmutable $current, DateTimeImmutable $candidate): DateTimeImmutable
    {
        return $current === null || $candidate < $current ? $candidate : $current;
    }
}
