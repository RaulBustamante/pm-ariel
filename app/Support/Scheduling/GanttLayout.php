<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use App\Models\Task;
use DateTimeImmutable;
use Illuminate\Support\Collection;

/**
 * Convierte fechas en coordenadas. Toda la geometría del Gantt vive aquí.
 *
 * Separarla de la vista tiene un motivo práctico: la posición de una barra se
 * puede probar con una aserción numérica, y "la barra sale en el lugar
 * equivocado" es de los errores más difíciles de cazar mirando una pantalla.
 */
final class GanttLayout
{
    public const ZOOM_DAY = 'day';

    public const ZOOM_WEEK = 'week';

    public const ZOOM_MONTH = 'month';

    /** Píxeles por día en cada nivel de acercamiento. */
    private const PIXELS_PER_DAY = [
        self::ZOOM_DAY => 40,
        self::ZOOM_WEEK => 14,
        self::ZOOM_MONTH => 4,
    ];

    public const ROW_HEIGHT = 26;

    public const HEADER_HEIGHT = 42;

    public string $zoom;

    public DateTimeImmutable $start;

    public DateTimeImmutable $finish;

    public int $totalDays;

    public float $pixelsPerDay;

    public float $width;

    public float $height;

    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function __construct(
        private readonly Collection $tasks,
        string $zoom = self::ZOOM_WEEK,
    ) {
        $this->zoom = array_key_exists($zoom, self::PIXELS_PER_DAY) ? $zoom : self::ZOOM_WEEK;
        $this->pixelsPerDay = self::PIXELS_PER_DAY[$this->zoom];

        $starts = $tasks->pluck('early_start')->filter();
        $finishes = $tasks->pluck('early_finish')->filter();

        $first = $starts->min();
        $last = $finishes->max();

        $today = new DateTimeImmutable('today');

        // Un margen de un día a cada lado: una barra pegada al borde se ve
        // cortada y la gente cree que el dato está mal.
        $this->start = ($first ? DateTimeImmutable::createFromInterface($first) : $today)
            ->setTime(0, 0)->modify('-1 day');

        $this->finish = ($last ? DateTimeImmutable::createFromInterface($last) : $today)
            ->setTime(0, 0)->modify('+2 days');

        $this->totalDays = max(1, (int) $this->start->diff($this->finish)->days);
        $this->width = $this->totalDays * $this->pixelsPerDay;
        $this->height = self::HEADER_HEIGHT + ($tasks->count() * self::ROW_HEIGHT) + 8;
    }

    public function x(mixed $date): float
    {
        if ($date === null) {
            return 0.0;
        }

        $instant = DateTimeImmutable::createFromInterface($date);

        $minutes = ($instant->getTimestamp() - $this->start->getTimestamp()) / 60;

        return round(($minutes / 1440) * $this->pixelsPerDay, 2);
    }

    public function y(int $rowIndex): float
    {
        return self::HEADER_HEIGHT + ($rowIndex * self::ROW_HEIGHT);
    }

    /** Ancho de una barra, con mínimo visible: una tarea de una hora existe. */
    public function barWidth(mixed $from, mixed $to): float
    {
        return max(2.0, $this->x($to) - $this->x($from));
    }

    /**
     * Las divisiones de la escala de tiempo, ya con su rótulo.
     *
     * @return list<array{x: float, label: string, major: bool}>
     */
    public function ticks(): array
    {
        $ticks = [];
        $cursor = $this->start;

        $step = match ($this->zoom) {
            self::ZOOM_DAY => '+1 day',
            self::ZOOM_MONTH => '+1 month',
            default => '+1 week',
        };

        // En vista semanal se arranca en lunes para que las divisiones coincidan
        // con la semana laboral y no con un día arbitrario.
        if ($this->zoom === self::ZOOM_WEEK) {
            $cursor = $cursor->modify('monday this week');
        } elseif ($this->zoom === self::ZOOM_MONTH) {
            $cursor = $cursor->modify('first day of this month');
        }

        for ($guard = 0; $guard < 500 && $cursor <= $this->finish; $guard++) {
            $ticks[] = [
                'x' => $this->x($cursor),
                'label' => match ($this->zoom) {
                    self::ZOOM_DAY => $cursor->format('d/m'),
                    self::ZOOM_MONTH => $cursor->format('M Y'),
                    default => $cursor->format('d/m'),
                },
                'major' => $this->zoom === self::ZOOM_MONTH || (int) $cursor->format('j') === 1,
            ];

            $cursor = $cursor->modify($step);
        }

        return $ticks;
    }

    /**
     * Franjas de los días no laborables, para que se vean como lo que son.
     * Sin ellas, una barra que "salta" el fin de semana parece un error.
     *
     * @return list<array{x: float, width: float}>
     */
    public function weekendBands(): array
    {
        if ($this->zoom === self::ZOOM_MONTH) {
            // A esta escala serían rayas de cuatro píxeles: ruido, no información.
            return [];
        }

        $bands = [];
        $cursor = $this->start;

        for ($guard = 0; $guard < 3000 && $cursor <= $this->finish; $guard++) {
            if (in_array((int) $cursor->format('N'), [6, 7], strict: true)) {
                $bands[] = ['x' => $this->x($cursor), 'width' => $this->pixelsPerDay];
            }

            $cursor = $cursor->modify('+1 day');
        }

        return $bands;
    }

    public function todayX(): ?float
    {
        $today = new DateTimeImmutable('today');

        if ($today < $this->start || $today > $this->finish) {
            return null;
        }

        return $this->x($today);
    }

    /**
     * Posición de cada tarea por id, para poder trazar las flechas de
     * dependencia sin volver a recorrer la lista.
     *
     * @return array<int, int>
     */
    public function rowIndexById(): array
    {
        $index = [];

        foreach ($this->tasks as $position => $task) {
            $index[(int) $task->id] = $position;
        }

        return $index;
    }
}
