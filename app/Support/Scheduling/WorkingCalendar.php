<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;

/**
 * Qué cuenta como tiempo de trabajo y qué no.
 *
 * Todo el motor de programación pregunta aquí. Nada más en el sistema sabe qué
 * es un fin de semana, un feriado o una jornada partida: si estuviera repetido,
 * el Gantt y el cálculo empezarían a discrepar sobre cuándo termina una tarea.
 *
 * **Toda la aritmética es en minutos de trabajo.** Sumar "3 días" no significa
 * nada hasta saber de cuántas horas es la jornada; sumar 1440 minutos de trabajo
 * sí significa lo mismo siempre. La conversión a días para mostrar en pantalla
 * es asunto de la interfaz, no del cálculo.
 *
 * Dos convenios que hay que tener presentes al leer el código:
 *
 * 1. **Los inicios se adelantan al siguiente instante hábil; los fines no.** Una
 *    tarea que termina exactamente a las 18:00 termina a las 18:00, no a las
 *    09:00 del día siguiente. Si los fines también se adelantaran, cada tarea
 *    aparentaría terminar un día después de lo que dura.
 * 2. **Duración cero no se mueve.** Un hito colocado a las 18:00 se queda a las
 *    18:00. Empujarlo a la mañana siguiente lo separaría visualmente del trabajo
 *    que lo produjo.
 */
final class WorkingCalendar
{
    /** Tope de días a recorrer buscando el siguiente instante hábil. */
    private const MAX_SEARCH_DAYS = 3650;

    /**
     * @var array<int, list<WorkShift>> 1 = lunes … 7 = domingo (ISO-8601)
     */
    private array $week;

    /**
     * Días que rompen la regla semanal. Un día con lista vacía es feriado; uno
     * con turnos propios es una jornada especial — un sábado que sí se trabaja,
     * por ejemplo.
     *
     * @var array<string, list<WorkShift>>
     */
    private array $exceptions = [];

    /** @var array<string, int> Minutos hábiles por día, memorizados. */
    private array $dayMinutes = [];

    private DateTimeZone $timezone;

    /**
     * @param  array<int, list<WorkShift>>  $week
     * @param  array<string, list<WorkShift>>  $exceptions
     */
    public function __construct(array $week, array $exceptions = [], ?DateTimeZone $timezone = null)
    {
        if ($week === []) {
            throw new InvalidArgumentException('Un calendario sin ningún día hábil no puede programar nada.');
        }

        $this->week = $week;
        $this->exceptions = $exceptions;
        $this->timezone = $timezone ?? new DateTimeZone(config('app.timezone', 'UTC'));
    }

    /**
     * Lunes a viernes, con la jornada que se le pase. El punto de partida más
     * común, y el que usan casi todas las pruebas.
     *
     * @param  list<WorkShift>  $shifts
     */
    public static function standard(?array $shifts = null, ?DateTimeZone $timezone = null): self
    {
        $shifts ??= [WorkShift::fromTimes('09:00', '13:00'), WorkShift::fromTimes('14:00', '18:00')];

        return new self(array_fill_keys(range(1, 5), $shifts), [], $timezone);
    }

    public function timezone(): DateTimeZone
    {
        return $this->timezone;
    }

    /**
     * Marca un día como no laborable. Es lo que hace un feriado.
     */
    public function withHoliday(string $date): self
    {
        return $this->withException($date, []);
    }

    /**
     * @param  list<WorkShift>  $shifts
     */
    public function withException(string $date, array $shifts): self
    {
        $clone = clone $this;
        $clone->exceptions[$date] = $shifts;
        $clone->dayMinutes = [];

        return $clone;
    }

    /**
     * @return list<WorkShift>
     */
    public function shiftsOn(DateTimeImmutable $day): array
    {
        $key = $day->setTimezone($this->timezone)->format('Y-m-d');

        if (array_key_exists($key, $this->exceptions)) {
            return $this->exceptions[$key];
        }

        return $this->week[(int) $day->setTimezone($this->timezone)->format('N')] ?? [];
    }

    public function isWorkingDay(DateTimeImmutable $day): bool
    {
        return $this->shiftsOn($day) !== [];
    }

    public function isWorkingInstant(DateTimeImmutable $instant): bool
    {
        $local = $instant->setTimezone($this->timezone);
        $minuteOfDay = ((int) $local->format('G') * 60) + (int) $local->format('i');

        foreach ($this->shiftsOn($local) as $shift) {
            if ($shift->contains($minuteOfDay)) {
                return true;
            }
        }

        return false;
    }

    /**
     * El propio instante si ya es hábil; si no, el arranque del siguiente turno.
     */
    public function nextWorkingInstant(DateTimeImmutable $instant): DateTimeImmutable
    {
        $local = $instant->setTimezone($this->timezone);
        $minuteOfDay = $this->minuteOfDay($local);

        for ($day = 0; $day < self::MAX_SEARCH_DAYS; $day++) {
            $candidate = $day === 0 ? $local : $local->modify("+{$day} day")->setTime(0, 0);
            $from = $day === 0 ? $minuteOfDay : 0;

            foreach ($this->shiftsOn($candidate) as $shift) {
                if ($shift->contains($from)) {
                    return $candidate;
                }

                if ($shift->startMinute > $from) {
                    return $this->atMinute($candidate, $shift->startMinute);
                }
            }
        }

        throw new RuntimeException('No se encontró ningún instante hábil en diez años: revisa el calendario.');
    }

    /**
     * El propio instante si es hábil o cae justo en el cierre de un turno; si
     * no, el cierre del turno anterior. Es el espejo del anterior, y lo usa el
     * recorrido hacia atrás.
     */
    public function previousWorkingInstant(DateTimeImmutable $instant): DateTimeImmutable
    {
        $local = $instant->setTimezone($this->timezone);
        $minuteOfDay = $this->minuteOfDay($local);

        for ($day = 0; $day < self::MAX_SEARCH_DAYS; $day++) {
            $candidate = $day === 0 ? $local : $local->modify("-{$day} day")->setTime(23, 59);
            $until = $day === 0 ? $minuteOfDay : 1440;

            foreach (array_reverse($this->shiftsOn($candidate)) as $shift) {
                // El cierre del turno cuenta como hábil: una tarea puede terminar
                // ahí, aunque no pueda empezar ahí. El arranque, en cambio, no:
                // un fin a las 09:00 del lunes es en realidad el viernes a las
                // 18:00 — cero minutos de distancia, pero mucho más legible en
                // un Gantt, y hace que este método sea el espejo exacto del otro.
                if ($until > $shift->startMinute && $until <= $shift->endMinute) {
                    return $this->atMinute($candidate, $until);
                }

                if ($shift->endMinute < $until) {
                    return $this->atMinute($candidate, $shift->endMinute);
                }
            }
        }

        throw new RuntimeException('No se encontró ningún instante hábil en diez años hacia atrás.');
    }

    /**
     * Suma minutos de trabajo. Cero no mueve nada (convenio 2 del encabezado).
     */
    public function addWorkingMinutes(DateTimeImmutable $from, int $minutes): DateTimeImmutable
    {
        if ($minutes === 0) {
            return $from->setTimezone($this->timezone);
        }

        if ($minutes < 0) {
            return $this->subtractWorkingMinutes($from, -$minutes);
        }

        $cursor = $this->nextWorkingInstant($from);
        $remaining = $minutes;

        for ($guard = 0; $guard < self::MAX_SEARCH_DAYS * 5; $guard++) {
            $shift = $this->shiftAt($cursor);

            if ($shift === null) {
                $cursor = $this->nextWorkingInstant($cursor);

                continue;
            }

            $available = $shift->endMinute - $this->minuteOfDay($cursor);

            if ($remaining <= $available) {
                return $this->atMinute($cursor, $this->minuteOfDay($cursor) + $remaining);
            }

            $remaining -= $available;
            // Un minuto después del cierre para no volver a caer en este turno.
            $cursor = $this->nextWorkingInstant($this->atMinute($cursor, $shift->endMinute)->modify('+1 minute'));
        }

        throw new RuntimeException('La suma de minutos hábiles no terminó: revisa el calendario.');
    }

    public function subtractWorkingMinutes(DateTimeImmutable $from, int $minutes): DateTimeImmutable
    {
        if ($minutes === 0) {
            return $from->setTimezone($this->timezone);
        }

        if ($minutes < 0) {
            return $this->addWorkingMinutes($from, -$minutes);
        }

        $cursor = $this->previousWorkingInstant($from);
        $remaining = $minutes;

        for ($guard = 0; $guard < self::MAX_SEARCH_DAYS * 5; $guard++) {
            $shift = $this->shiftEndingAt($cursor);

            if ($shift === null) {
                $cursor = $this->previousWorkingInstant($cursor->modify('-1 minute'));

                continue;
            }

            $available = $this->minuteOfDay($cursor) - $shift->startMinute;

            if ($remaining <= $available) {
                return $this->atMinute($cursor, $this->minuteOfDay($cursor) - $remaining);
            }

            $remaining -= $available;
            $cursor = $this->previousWorkingInstant($this->atMinute($cursor, $shift->startMinute)->modify('-1 minute'));
        }

        throw new RuntimeException('La resta de minutos hábiles no terminó: revisa el calendario.');
    }

    /**
     * Minutos de trabajo entre dos instantes. Negativo si van al revés, para que
     * una holgura negativa se pueda expresar sin casos especiales.
     */
    public function workingMinutesBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        $a = $from->setTimezone($this->timezone);
        $b = $to->setTimezone($this->timezone);

        if ($a == $b) {
            return 0;
        }

        if ($a > $b) {
            return -$this->workingMinutesBetween($b, $a);
        }

        $total = 0;
        $day = $a->setTime(0, 0);
        $lastDay = $b->setTime(0, 0);

        for ($guard = 0; $guard <= self::MAX_SEARCH_DAYS; $guard++) {
            if ($day > $lastDay) {
                return $total;
            }

            $isFirst = $day == $a->setTime(0, 0);
            $isLast = $day == $lastDay;

            if (! $isFirst && ! $isLast) {
                $total += $this->minutesOnDay($day);
            } else {
                $lowerBound = $isFirst ? $this->minuteOfDay($a) : 0;
                $upperBound = $isLast ? $this->minuteOfDay($b) : 1440;

                foreach ($this->shiftsOn($day) as $shift) {
                    $start = max($shift->startMinute, $lowerBound);
                    $end = min($shift->endMinute, $upperBound);

                    if ($end > $start) {
                        $total += $end - $start;
                    }
                }
            }

            $day = $day->modify('+1 day');
        }

        throw new RuntimeException('El intervalo entre las dos fechas supera diez años.');
    }

    /** Minutos hábiles de un día completo. Memorizado: se pregunta muchísimo. */
    public function minutesOnDay(DateTimeImmutable $day): int
    {
        $key = $day->setTimezone($this->timezone)->format('Y-m-d');

        return $this->dayMinutes[$key] ??= array_sum(
            array_map(fn (WorkShift $shift): int => $shift->minutes(), $this->shiftsOn($day)),
        );
    }

    private function shiftAt(DateTimeImmutable $instant): ?WorkShift
    {
        $minuteOfDay = $this->minuteOfDay($instant);

        foreach ($this->shiftsOn($instant) as $shift) {
            if ($shift->contains($minuteOfDay)) {
                return $shift;
            }
        }

        return null;
    }

    /** El turno que contiene el instante, aceptando además su minuto de cierre. */
    private function shiftEndingAt(DateTimeImmutable $instant): ?WorkShift
    {
        $minuteOfDay = $this->minuteOfDay($instant);

        foreach ($this->shiftsOn($instant) as $shift) {
            if ($minuteOfDay > $shift->startMinute && $minuteOfDay <= $shift->endMinute) {
                return $shift;
            }
        }

        return null;
    }

    private function minuteOfDay(DateTimeImmutable $instant): int
    {
        return ((int) $instant->format('G') * 60) + (int) $instant->format('i');
    }

    private function atMinute(DateTimeImmutable $day, int $minuteOfDay): DateTimeImmutable
    {
        return $day->setTime(intdiv($minuteOfDay, 60), $minuteOfDay % 60);
    }
}
