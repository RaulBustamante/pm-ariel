<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class TaskConstraint
{
    public function __construct(
        public ConstraintType $type,
        public ?DateTimeImmutable $date = null,
    ) {
        if ($type->needsDate() && $date === null) {
            throw new InvalidArgumentException("La restricción {$type->value} no significa nada sin una fecha.");
        }
    }

    public static function asSoonAsPossible(): self
    {
        return new self(ConstraintType::AsSoonAsPossible);
    }

    public static function asLateAsPossible(): self
    {
        return new self(ConstraintType::AsLateAsPossible);
    }

    public static function of(string $type, ?DateTimeImmutable $date = null): self
    {
        return new self(ConstraintType::from($type), $date);
    }
}
