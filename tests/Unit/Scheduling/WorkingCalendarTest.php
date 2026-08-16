<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\WorkingCalendar;
use App\Support\Scheduling\WorkShift;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Todos los resultados esperados se calcularon a mano antes de escribir el
 * código. Las fechas usadas son de 2026: el 5 de enero es lunes.
 */
final class WorkingCalendarTest extends TestCase
{
    private DateTimeZone $tz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tz = new DateTimeZone('America/Mexico_City');
    }

    /** Jornada partida: 09:00–13:00 y 14:00–18:00. Ocho horas, con comida fuera. */
    private function calendar(): WorkingCalendar
    {
        return WorkingCalendar::standard(
            [WorkShift::fromTimes('09:00', '13:00'), WorkShift::fromTimes('14:00', '18:00')],
            $this->tz,
        );
    }

    private function at(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, $this->tz);
    }

    #[Test]
    public function a_working_day_has_the_shift_minutes_and_not_the_lunch_hour(): void
    {
        // 4 h + 4 h = 480 min. La hora de comida no cuenta.
        $this->assertSame(480, $this->calendar()->minutesOnDay($this->at('2026-01-05 00:00')));
    }

    #[Test]
    public function saturday_and_sunday_have_no_working_minutes(): void
    {
        $calendar = $this->calendar();

        $this->assertSame(0, $calendar->minutesOnDay($this->at('2026-01-10 00:00')));
        $this->assertSame(0, $calendar->minutesOnDay($this->at('2026-01-11 00:00')));
    }

    #[Test]
    public function the_lunch_hour_is_not_working_time(): void
    {
        $calendar = $this->calendar();

        $this->assertTrue($calendar->isWorkingInstant($this->at('2026-01-05 12:59')));
        $this->assertFalse($calendar->isWorkingInstant($this->at('2026-01-05 13:30')));
        $this->assertTrue($calendar->isWorkingInstant($this->at('2026-01-05 14:00')));
    }

    #[Test]
    public function an_instant_in_the_lunch_hour_moves_to_the_afternoon_shift(): void
    {
        $this->assertEquals(
            $this->at('2026-01-05 14:00'),
            $this->calendar()->nextWorkingInstant($this->at('2026-01-05 13:20')),
        );
    }

    #[Test]
    public function friday_evening_moves_to_monday_morning(): void
    {
        // Viernes 9 de enero, 18:00 → lunes 12, 09:00.
        $this->assertEquals(
            $this->at('2026-01-12 09:00'),
            $this->calendar()->nextWorkingInstant($this->at('2026-01-09 18:00')),
        );
    }

    #[Test]
    public function going_backwards_from_monday_morning_lands_on_friday_evening(): void
    {
        $this->assertEquals(
            $this->at('2026-01-09 18:00'),
            $this->calendar()->previousWorkingInstant($this->at('2026-01-12 09:00')),
        );
    }

    /**
     * El convenio que evita que toda tarea aparente durar un día más: terminar
     * al cierre del turno es terminar al cierre, no a la mañana siguiente.
     */
    #[Test]
    public function finishing_exactly_at_close_of_business_stays_there(): void
    {
        // Lunes 09:00 + 480 min = lunes 18:00, no martes 09:00.
        $this->assertEquals(
            $this->at('2026-01-05 18:00'),
            $this->calendar()->addWorkingMinutes($this->at('2026-01-05 09:00'), 480),
        );
    }

    #[Test]
    public function adding_minutes_jumps_over_the_lunch_hour(): void
    {
        // 09:00 + 5 h: 4 h llegan a 13:00, la quinta arranca a las 14:00 → 15:00.
        $this->assertEquals(
            $this->at('2026-01-05 15:00'),
            $this->calendar()->addWorkingMinutes($this->at('2026-01-05 09:00'), 300),
        );
    }

    #[Test]
    public function adding_minutes_jumps_over_the_weekend(): void
    {
        // Viernes 09:00 + 960 min = 2 jornadas: el propio viernes y el lunes.
        // Sábado y domingo no aportan nada, así que cierra el lunes a las 18:00.
        $this->assertEquals(
            $this->at('2026-01-12 18:00'),
            $this->calendar()->addWorkingMinutes($this->at('2026-01-09 09:00'), 960),
        );
    }

    #[Test]
    public function a_holiday_in_the_middle_pushes_the_finish_one_day(): void
    {
        // Miércoles 7 feriado. Lunes 09:00 + 3 jornadas: lunes, martes, jueves.
        $calendar = $this->calendar()->withHoliday('2026-01-07');

        $this->assertEquals(
            $this->at('2026-01-08 18:00'),
            $calendar->addWorkingMinutes($this->at('2026-01-05 09:00'), 1440),
        );
    }

    #[Test]
    public function a_holiday_on_the_starting_day_moves_the_start(): void
    {
        $calendar = $this->calendar()->withHoliday('2026-01-05');

        $this->assertEquals(
            $this->at('2026-01-06 09:00'),
            $calendar->nextWorkingInstant($this->at('2026-01-05 09:00')),
        );
    }

    /** Una excepción también sirve para abrir un día normalmente cerrado. */
    #[Test]
    public function an_exception_can_open_a_saturday(): void
    {
        $calendar = $this->calendar()->withException('2026-01-10', [WorkShift::fromTimes('09:00', '13:00')]);

        $this->assertTrue($calendar->isWorkingDay($this->at('2026-01-10 00:00')));
        $this->assertSame(240, $calendar->minutesOnDay($this->at('2026-01-10 00:00')));
    }

    #[Test]
    public function subtracting_minutes_is_the_mirror_of_adding_them(): void
    {
        $calendar = $this->calendar();

        $this->assertEquals(
            $this->at('2026-01-09 09:00'),
            $calendar->subtractWorkingMinutes($this->at('2026-01-12 18:00'), 960),
        );
    }

    #[Test]
    public function subtracting_jumps_backwards_over_the_lunch_hour(): void
    {
        // 15:00 − 5 h: una hora hasta 14:00, y cuatro más desde 13:00 → 09:00.
        $this->assertEquals(
            $this->at('2026-01-05 09:00'),
            $this->calendar()->subtractWorkingMinutes($this->at('2026-01-05 15:00'), 300),
        );
    }

    #[Test]
    public function working_minutes_between_two_instants_ignores_nights_and_weekends(): void
    {
        $calendar = $this->calendar();

        // Viernes 09:00 → martes 18:00: viernes, lunes, martes = 3 jornadas.
        $this->assertSame(
            1440,
            $calendar->workingMinutesBetween($this->at('2026-01-09 09:00'), $this->at('2026-01-13 18:00')),
        );
    }

    #[Test]
    public function the_distance_is_negative_when_the_dates_run_backwards(): void
    {
        $calendar = $this->calendar();

        $this->assertSame(
            -480,
            $calendar->workingMinutesBetween($this->at('2026-01-06 18:00'), $this->at('2026-01-06 09:00')),
        );
    }

    #[Test]
    public function adding_and_measuring_agree_with_each_other(): void
    {
        $calendar = $this->calendar();
        $start = $this->at('2026-01-05 10:30');

        foreach ([1, 60, 480, 961, 5000] as $minutes) {
            $finish = $calendar->addWorkingMinutes($start, $minutes);

            $this->assertSame(
                $minutes,
                $calendar->workingMinutesBetween($start, $finish),
                "Sumar {$minutes} minutos y medir la distancia deben coincidir.",
            );
        }
    }

    #[Test]
    public function zero_minutes_does_not_move_a_milestone(): void
    {
        $calendar = $this->calendar();
        $closeOfBusiness = $this->at('2026-01-05 18:00');

        $this->assertEquals($closeOfBusiness, $calendar->addWorkingMinutes($closeOfBusiness, 0));
    }

    /**
     * Escenario 17 del plan: la misma cantidad de trabajo en jornadas distintas
     * ocupa el mismo número de minutos, y distinto número de días.
     */
    #[Test]
    public function the_same_work_takes_the_same_minutes_on_a_four_hour_day(): void
    {
        $halfDay = WorkingCalendar::standard([WorkShift::fromTimes('09:00', '13:00')], $this->tz);

        // 480 minutos de trabajo son dos jornadas de 4 h: lunes y martes.
        $this->assertEquals(
            $this->at('2026-01-06 13:00'),
            $halfDay->addWorkingMinutes($this->at('2026-01-05 09:00'), 480),
        );
    }
}
