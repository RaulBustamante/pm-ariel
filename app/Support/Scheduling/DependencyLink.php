<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use InvalidArgumentException;

final readonly class DependencyLink
{
    public function __construct(
        public string $predecessorId,
        public string $successorId,
        public DependencyType $type = DependencyType::FinishToStart,
        /**
         * Minutos de trabajo de retraso. Negativo es adelanto (lead): la
         * sucesora puede empezar antes de que la predecesora termine.
         */
        public int $lagMinutes = 0,
    ) {
        if ($predecessorId === $successorId) {
            throw new InvalidArgumentException("La tarea {$predecessorId} no puede depender de sí misma.");
        }
    }

    public static function finishToStart(string $predecessor, string $successor, int $lagMinutes = 0): self
    {
        return new self($predecessor, $successor, DependencyType::FinishToStart, $lagMinutes);
    }

    public static function of(string $predecessor, string $successor, string $type, int $lagMinutes = 0): self
    {
        return new self($predecessor, $successor, DependencyType::from($type), $lagMinutes);
    }
}
