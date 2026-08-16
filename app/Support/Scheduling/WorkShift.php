<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use InvalidArgumentException;

/**
 * Un tramo de jornada dentro de un día, en minutos desde la medianoche.
 *
 * Media abierta: `[inicio, fin)`. Una jornada de 09:00 a 13:00 y otra de 14:00 a
 * 18:00 es una jornada partida, y la hora de comida simplemente no existe para
 * el cálculo.
 */
final readonly class WorkShift
{
    public function __construct(
        public int $startMinute,
        public int $endMinute,
    ) {
        if ($startMinute < 0 || $endMinute > 1440) {
            throw new InvalidArgumentException('Un turno vive dentro de un día: entre 0 y 1440 minutos.');
        }

        if ($endMinute <= $startMinute) {
            throw new InvalidArgumentException('Un turno tiene que terminar después de empezar.');
        }
    }

    /** "09:00" y "18:00" — como lo escribiría quien configura el calendario. */
    public static function fromTimes(string $start, string $end): self
    {
        return new self(self::toMinutes($start), self::toMinutes($end));
    }

    public function minutes(): int
    {
        return $this->endMinute - $this->startMinute;
    }

    public function contains(int $minuteOfDay): bool
    {
        return $minuteOfDay >= $this->startMinute && $minuteOfDay < $this->endMinute;
    }

    private static function toMinutes(string $time): int
    {
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $parts) !== 1) {
            throw new InvalidArgumentException("Hora inválida: {$time}. Se espera HH:MM.");
        }

        return ((int) $parts[1] * 60) + (int) $parts[2];
    }
}
