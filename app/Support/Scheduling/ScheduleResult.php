<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Lo que devuelve el motor. Un objeto de lectura: si algo cambia, se recalcula.
 */
final readonly class ScheduleResult
{
    /**
     * @param  array<string, ScheduledTask>  $tasks
     * @param  list<string>  $criticalTaskIds
     * @param  list<list<string>>  $criticalPaths
     */
    public function __construct(
        public array $tasks,
        public DateTimeImmutable $projectStart,
        public DateTimeImmutable $projectFinish,
        public array $criticalTaskIds = [],
        public array $criticalPaths = [],
        /** Cuánto tardó el cálculo. Se registra en `schedule_runs`. */
        public float $elapsedMilliseconds = 0.0,
    ) {}

    public function task(string $id): ScheduledTask
    {
        return $this->tasks[$id] ?? throw new InvalidArgumentException("La tarea {$id} no está en el resultado.");
    }

    public function isCritical(string $id): bool
    {
        return isset($this->tasks[$id]) && $this->tasks[$id]->isCritical;
    }

    /** Duración total en minutos de trabajo del calendario del proyecto. */
    public function totalWorkingMinutes(WorkingCalendar $calendar): int
    {
        return $calendar->workingMinutesBetween($this->projectStart, $this->projectFinish);
    }
}
