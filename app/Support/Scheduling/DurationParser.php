<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use InvalidArgumentException;

/**
 * Traduce entre lo que la gente escribe y lo que el motor entiende.
 *
 * El motor trabaja en minutos de trabajo, pero nadie captura "2160 minutos":
 * captura "4d". La conversión necesita saber de cuántas horas es la jornada, y
 * por eso vive aquí y no en el motor — el mismo "4d" son 1920 minutos con
 * jornada de 8 h y 960 con jornada de 4 h.
 *
 * Se acepta lo que la gente ya escribe sin pensarlo: `3d`, `3 d`, `3 días`,
 * `4h`, `2s`, `30m`, `1.5d`. Rechazar un formato razonable porque no está en la
 * lista solo enseña a la gente a desconfiar del campo.
 */
final class DurationParser
{
    /** Jornada por omisión cuando no se pasa calendario: 8 horas. */
    private const DEFAULT_DAY_MINUTES = 480;

    public function __construct(
        private readonly int $minutesPerDay = self::DEFAULT_DAY_MINUTES,
        private readonly int $daysPerWeek = 5,
    ) {}

    public static function forCalendar(WorkingCalendar $calendar, \DateTimeImmutable $reference): self
    {
        $day = $calendar->minutesOnDay($calendar->nextWorkingInstant($reference));

        return new self($day > 0 ? $day : self::DEFAULT_DAY_MINUTES);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function toMinutes(string $input): int
    {
        $normalized = mb_strtolower(trim($input));

        if ($normalized === '' || $normalized === '0') {
            return 0;
        }

        // Número y unidad, con o sin espacio entre ellos. Se acepta coma decimal
        // porque en español es lo que la gente teclea.
        if (preg_match('/^(\d+(?:[.,]\d+)?)\s*([a-záéíóú]*)$/u', $normalized, $parts) !== 1) {
            throw new InvalidArgumentException("No entiendo la duración «{$input}». Prueba con 3d, 4h o 30m.");
        }

        $amount = (float) str_replace(',', '.', $parts[1]);
        $unit = $parts[2];

        $minutes = match (true) {
            $unit === '' , str_starts_with($unit, 'd') => $amount * $this->minutesPerDay,
            str_starts_with($unit, 'h') => $amount * 60,
            str_starts_with($unit, 'm') => $amount,
            str_starts_with($unit, 's'), str_starts_with($unit, 'w') => $amount * $this->minutesPerDay * $this->daysPerWeek,
            default => throw new InvalidArgumentException("No conozco la unidad «{$unit}». Usa d, h, m o s."),
        };

        return (int) round($minutes);
    }

    /**
     * De vuelta a algo legible. Se elige la unidad que da el número más limpio:
     * "2d" se lee mejor que "960m", y "3h" mejor que "0.375d".
     */
    public function toHuman(int $minutes): string
    {
        if ($minutes === 0) {
            return '0';
        }

        if ($minutes % $this->minutesPerDay === 0) {
            return ($minutes / $this->minutesPerDay).'d';
        }

        if ($minutes % 60 === 0) {
            return ($minutes / 60).'h';
        }

        // Fracciones de jornada con un decimal, antes de rendirse a los minutos.
        $days = round($minutes / $this->minutesPerDay, 1);

        if (abs($days * $this->minutesPerDay - $minutes) < 1) {
            return $days.'d';
        }

        return $minutes.'m';
    }
}
