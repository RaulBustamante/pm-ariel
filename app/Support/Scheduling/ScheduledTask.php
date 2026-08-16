<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;

/**
 * El resultado del cálculo para una tarea. Inmutable: lo que sale del motor no
 * se retoca, se vuelve a calcular.
 */
final readonly class ScheduledTask
{
    public function __construct(
        public string $id,
        public DateTimeImmutable $earlyStart,
        public DateTimeImmutable $earlyFinish,
        public DateTimeImmutable $lateStart,
        public DateTimeImmutable $lateFinish,
        /** Cuánto puede retrasarse sin mover el fin del proyecto. Negativa = ya va tarde. */
        public int $totalFloatMinutes = 0,
        /** Cuánto puede retrasarse sin mover a ninguna sucesora. */
        public int $freeFloatMinutes = 0,
        public bool $isCritical = false,
        public bool $isSummary = false,
        public ?string $wbsCode = null,
        public float $cost = 0.0,
        public float $percentComplete = 0.0,
    ) {}

    public function with(
        ?int $totalFloatMinutes = null,
        ?int $freeFloatMinutes = null,
        ?bool $isCritical = null,
        ?string $wbsCode = null,
    ): self {
        return new self(
            $this->id,
            $this->earlyStart,
            $this->earlyFinish,
            $this->lateStart,
            $this->lateFinish,
            $totalFloatMinutes ?? $this->totalFloatMinutes,
            $freeFloatMinutes ?? $this->freeFloatMinutes,
            $isCritical ?? $this->isCritical,
            $this->isSummary,
            $wbsCode ?? $this->wbsCode,
            $this->cost,
            $this->percentComplete,
        );
    }
}
