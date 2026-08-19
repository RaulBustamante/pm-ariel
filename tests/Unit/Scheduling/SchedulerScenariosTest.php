<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\CircularDependencyException;
use App\Support\Scheduling\ConstraintType;
use App\Support\Scheduling\DependencyLink;
use App\Support\Scheduling\ScheduleNetwork;
use App\Support\Scheduling\Scheduler;
use App\Support\Scheduling\ScheduleResult;
use App\Support\Scheduling\TaskConstraint;
use App\Support\Scheduling\TaskNode;
use App\Support\Scheduling\WorkingCalendar;
use App\Support\Scheduling\WorkShift;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Los 36 escenarios de aceptación del motor, del BUILD_PLAN.
 *
 * **Cada resultado esperado se calculó en papel antes de escribir el código.**
 * Copiar lo que devuelve el motor y llamarlo prueba no verifica nada: solo
 * congela el error, si lo hay.
 *
 * Calendario de todos los escenarios salvo donde se diga: lunes a viernes,
 * 09:00–18:00 corrido, 540 minutos al día. Se usa jornada corrida y no partida
 * para que la aritmética a mano sea revisable de un vistazo — la jornada partida
 * ya tiene sus propias pruebas en WorkingCalendarTest.
 *
 * Fechas de 2026: el 5 de enero es lunes.
 */
final class SchedulerScenariosTest extends TestCase
{
    private const DAY = 540;

    private DateTimeZone $tz;

    private WorkingCalendar $calendar;

    private Scheduler $scheduler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tz = new DateTimeZone('America/Mexico_City');
        $this->calendar = WorkingCalendar::standard([WorkShift::fromTimes('09:00', '18:00')], $this->tz);
        $this->scheduler = new Scheduler;
    }

    private function at(string $datetime): DateTimeImmutable
    {
        return new DateTimeImmutable($datetime, $this->tz);
    }

    /**
     * @param  list<TaskNode>  $tasks
     * @param  list<DependencyLink>  $links
     * @param  array<string, WorkingCalendar>  $extra
     */
    private function calculate(array $tasks, array $links = [], string $start = '2026-01-05 09:00', ?WorkingCalendar $calendar = null, array $extra = []): ScheduleResult
    {
        return $this->scheduler->schedule(
            new ScheduleNetwork($tasks, $links),
            $this->at($start),
            $calendar ?? $this->calendar,
            $extra,
        );
    }

    private function task(string $id, int $days = 1, ?TaskConstraint $constraint = null, ?string $calendarKey = null): TaskNode
    {
        return new TaskNode(
            id: $id,
            name: $id,
            durationMinutes: $days * self::DAY,
            constraint: $constraint,
            calendarKey: $calendarKey,
        );
    }

    private function assertStart(ScheduleResult $result, string $id, string $expected): void
    {
        $this->assertEquals($this->at($expected), $result->task($id)->earlyStart, "Inicio de {$id}");
    }

    private function assertFinish(ScheduleResult $result, string $id, string $expected): void
    {
        $this->assertEquals($this->at($expected), $result->task($id)->earlyFinish, "Fin de {$id}");
    }

    // ---------------------------------------------------------------- 1 a 3

    #[Test]
    public function scenario_01_simple_finish_to_start_chain_of_three(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B'), $this->task('C')],
            [DependencyLink::finishToStart('A', 'B'), DependencyLink::finishToStart('B', 'C')],
        );

        // Un día cada una: lunes, martes, miércoles.
        $this->assertStart($result, 'A', '2026-01-05 09:00');
        $this->assertFinish($result, 'A', '2026-01-05 18:00');
        $this->assertStart($result, 'B', '2026-01-06 09:00');
        $this->assertStart($result, 'C', '2026-01-07 09:00');
        $this->assertFinish($result, 'C', '2026-01-07 18:00');
    }

    #[Test]
    public function scenario_02_finish_to_start_with_two_days_of_lag(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B')],
            [DependencyLink::finishToStart('A', 'B', 2 * self::DAY)],
        );

        // A cierra el lunes 18:00. +2 jornadas de espera = jueves 09:00.
        $this->assertStart($result, 'B', '2026-01-08 09:00');
    }

    #[Test]
    public function scenario_03_finish_to_start_with_two_days_of_lead(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 3), $this->task('B')],
            [DependencyLink::finishToStart('A', 'B', -2 * self::DAY)],
        );

        // A: lunes a miércoles, cierra miércoles 18:00. Dos jornadas antes: martes 09:00.
        $this->assertFinish($result, 'A', '2026-01-07 18:00');
        $this->assertStart($result, 'B', '2026-01-06 09:00');
    }

    // ---------------------------------------------------------------- 4 a 8

    #[Test]
    public function scenario_04_start_to_start_with_no_lag(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 3), $this->task('B')],
            [DependencyLink::of('A', 'B', 'SS')],
        );

        $this->assertStart($result, 'B', '2026-01-05 09:00');
    }

    #[Test]
    public function scenario_05_start_to_start_with_three_days_of_lag(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 5), $this->task('B')],
            [DependencyLink::of('A', 'B', 'SS', 3 * self::DAY)],
        );

        // Lunes 09:00 + 3 jornadas = jueves 09:00.
        $this->assertStart($result, 'B', '2026-01-08 09:00');
    }

    #[Test]
    public function scenario_06_finish_to_finish_with_no_lag(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 3), $this->task('B')],
            [DependencyLink::of('A', 'B', 'FF')],
        );

        // A cierra miércoles 18:00; B dura un día, así que arranca miércoles 09:00.
        $this->assertFinish($result, 'B', '2026-01-07 18:00');
        $this->assertStart($result, 'B', '2026-01-07 09:00');
    }

    #[Test]
    public function scenario_07_finish_to_finish_with_one_day_of_lag(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 3), $this->task('B')],
            [DependencyLink::of('A', 'B', 'FF', self::DAY)],
        );

        // Miércoles 18:00 + 1 jornada = jueves 18:00.
        $this->assertFinish($result, 'B', '2026-01-08 18:00');
        $this->assertStart($result, 'B', '2026-01-08 09:00');
    }

    #[Test]
    public function scenario_08_start_to_finish(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 2), $this->task('B')],
            [DependencyLink::of('A', 'B', 'SF')],
        );

        // B no puede terminar antes de que A empiece. A arranca el lunes 09:00,
        // así que a B le bastaría con terminar ahí — pero eso la pondría a
        // trabajar antes del arranque del proyecto, así que se recorre al lunes.
        // La restricción se cumple: terminar el lunes 18:00 no es antes de que A
        // empiece.
        $this->assertStart($result, 'B', '2026-01-05 09:00');
        $this->assertFinish($result, 'B', '2026-01-05 18:00');
    }

    // ---------------------------------------------------------------- 9 a 12

    #[Test]
    public function scenario_09_several_predecessors_the_most_restrictive_wins(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B', days: 4), $this->task('C')],
            [DependencyLink::finishToStart('A', 'C'), DependencyLink::finishToStart('B', 'C')],
        );

        // A cierra lunes; B cierra jueves. Manda B.
        $this->assertStart($result, 'C', '2026-01-09 09:00');
    }

    #[Test]
    public function scenario_10_several_successors_from_one_predecessor(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B'), $this->task('C')],
            [DependencyLink::finishToStart('A', 'B'), DependencyLink::finishToStart('A', 'C')],
        );

        $this->assertStart($result, 'B', '2026-01-06 09:00');
        $this->assertStart($result, 'C', '2026-01-06 09:00');
    }

    #[Test]
    public function scenario_11_a_milestone_in_the_middle_of_a_chain(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('M', days: 0), $this->task('B')],
            [DependencyLink::finishToStart('A', 'M'), DependencyLink::finishToStart('M', 'B')],
        );

        // El hito se queda donde cierra A, sin empujarse a la mañana siguiente.
        $this->assertStart($result, 'M', '2026-01-05 18:00');
        $this->assertFinish($result, 'M', '2026-01-05 18:00');
        // Y la tarea siguiente sí arranca en la siguiente jornada.
        $this->assertStart($result, 'B', '2026-01-06 09:00');
    }

    #[Test]
    public function scenario_12_a_milestone_with_several_predecessors(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B', days: 3), $this->task('M', days: 0)],
            [DependencyLink::finishToStart('A', 'M'), DependencyLink::finishToStart('B', 'M')],
        );

        $this->assertStart($result, 'M', '2026-01-07 18:00');
    }

    // ---------------------------------------------------------------- 13 a 17

    #[Test]
    public function scenario_13_a_task_crossing_saturday_and_sunday(): void
    {
        // Jueves + 3 días: jueves, viernes, lunes.
        $result = $this->calculate([$this->task('A', days: 3)], start: '2026-01-08 09:00');

        $this->assertFinish($result, 'A', '2026-01-12 18:00');
    }

    #[Test]
    public function scenario_14_a_holiday_in_the_middle_of_the_task(): void
    {
        $calendar = $this->calendar->withHoliday('2026-01-07');

        $result = $this->calculate([$this->task('A', days: 3)], calendar: $calendar);

        // Lunes, martes, (miércoles feriado), jueves.
        $this->assertFinish($result, 'A', '2026-01-08 18:00');
    }

    #[Test]
    public function scenario_15_a_holiday_on_the_starting_day(): void
    {
        $calendar = $this->calendar->withHoliday('2026-01-05');

        $result = $this->calculate([$this->task('A', days: 2)], calendar: $calendar);

        $this->assertStart($result, 'A', '2026-01-06 09:00');
        $this->assertFinish($result, 'A', '2026-01-07 18:00');
    }

    #[Test]
    public function scenario_16_a_task_with_its_own_calendar(): void
    {
        // El calendario "obra" trabaja también los sábados.
        $siteCalendar = new WorkingCalendar(
            array_fill_keys(range(1, 6), [WorkShift::fromTimes('09:00', '18:00')]),
            [],
            $this->tz,
        );

        $result = $this->calculate(
            [$this->task('oficina', days: 6), $this->task('obra', days: 6, calendarKey: 'site')],
            start: '2026-01-05 09:00',
            extra: ['site' => $siteCalendar],
        );

        // Oficina: 6 jornadas de lunes a viernes → cierra el lunes siguiente.
        $this->assertFinish($result, 'oficina', '2026-01-12 18:00');
        // Obra: aprovecha el sábado 10 → cierra el sábado.
        $this->assertFinish($result, 'obra', '2026-01-10 18:00');
    }

    #[Test]
    public function scenario_17_a_four_hour_day_takes_twice_the_calendar_days(): void
    {
        $halfDay = WorkingCalendar::standard([WorkShift::fromTimes('09:00', '13:00')], $this->tz);

        // 1080 minutos de trabajo: dos jornadas de 9 h, o cuatro y media de 4 h.
        $full = $this->calculate([new TaskNode('A', durationMinutes: 1080)]);
        $half = $this->calculate([new TaskNode('A', durationMinutes: 1080)], calendar: $halfDay);

        $this->assertFinish($full, 'A', '2026-01-06 18:00');
        // 4 h × 4 días = 960; faltan 120 min del quinto día → viernes 11:00.
        $this->assertFinish($half, 'A', '2026-01-09 11:00');
    }

    // ---------------------------------------------------------------- 18 a 23

    #[Test]
    public function scenario_18_start_no_earlier_than_pushes_the_task(): void
    {
        $result = $this->calculate([
            $this->task('A', constraint: new TaskConstraint(
                ConstraintType::StartNoEarlierThan,
                $this->at('2026-01-08 09:00'),
            )),
        ]);

        $this->assertStart($result, 'A', '2026-01-08 09:00');
    }

    #[Test]
    public function scenario_19_start_no_earlier_than_in_the_past_is_ignored(): void
    {
        $result = $this->calculate(
            [
                $this->task('A', days: 3),
                $this->task('B', constraint: new TaskConstraint(
                    ConstraintType::StartNoEarlierThan,
                    $this->at('2026-01-05 09:00'),
                )),
            ],
            [DependencyLink::finishToStart('A', 'B')],
        );

        // "No antes del lunes" no adelanta nada: B sigue arrancando el jueves.
        $this->assertStart($result, 'B', '2026-01-08 09:00');
    }

    #[Test]
    public function scenario_20_finish_no_later_than_produces_negative_float(): void
    {
        $result = $this->calculate(
            [
                $this->task('A', days: 5),
                $this->task('B', days: 3, constraint: new TaskConstraint(
                    ConstraintType::FinishNoLaterThan,
                    $this->at('2026-01-13 18:00'),
                )),
            ],
            [DependencyLink::finishToStart('A', 'B')],
        );

        // A cierra el viernes 9. B necesita 3 jornadas: lunes, martes, miércoles
        // → cerraría el miércoles 14. Se le exige cerrar el martes 13: va un día
        // tarde, y eso es holgura de −540 minutos.
        $this->assertFinish($result, 'B', '2026-01-14 18:00');
        $this->assertSame(-self::DAY, $result->task('B')->totalFloatMinutes);
        $this->assertTrue($result->task('B')->isCritical);
    }

    #[Test]
    public function scenario_21_must_start_on_fixes_the_start(): void
    {
        $result = $this->calculate(
            [
                $this->task('A'),
                $this->task('B', constraint: new TaskConstraint(
                    ConstraintType::MustStartOn,
                    $this->at('2026-01-12 09:00'),
                )),
            ],
            [DependencyLink::finishToStart('A', 'B')],
        );

        // Gana sobre la predecesora, que la dejaría arrancar mucho antes.
        $this->assertStart($result, 'B', '2026-01-12 09:00');
        $this->assertFinish($result, 'B', '2026-01-12 18:00');
    }

    #[Test]
    public function scenario_22_must_finish_on_fixes_the_finish(): void
    {
        $result = $this->calculate([
            $this->task('A', days: 2, constraint: new TaskConstraint(
                ConstraintType::MustFinishOn,
                $this->at('2026-01-09 18:00'),
            )),
        ]);

        $this->assertFinish($result, 'A', '2026-01-09 18:00');
        $this->assertStart($result, 'A', '2026-01-08 09:00');
    }

    #[Test]
    public function scenario_23_as_late_as_possible(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B', constraint: TaskConstraint::asLateAsPossible()), $this->task('C', days: 5)],
            [DependencyLink::finishToStart('A', 'B'), DependencyLink::finishToStart('A', 'C')],
        );

        // A cierra el lunes. C ocupa martes a lunes 12 y marca el fin del
        // proyecto. B, que dura un día y no estorba a nadie, se recorre al final.
        $this->assertFinish($result, 'C', '2026-01-12 18:00');
        $this->assertStart($result, 'B', '2026-01-12 09:00');
        $this->assertFinish($result, 'B', '2026-01-12 18:00');
    }

    #[Test]
    public function a_requested_start_respects_dependencies_and_a_deadline_exposes_conflict(): void
    {
        $result = $this->calculate(
            [
                $this->task('A', days: 3),
                new TaskNode(
                    id: 'B',
                    durationMinutes: 2 * self::DAY,
                    requestedStart: $this->at('2026-01-06 09:00'),
                    deadline: $this->at('2026-01-09 23:59:59'),
                ),
            ],
            [DependencyLink::finishToStart('A', 'B')],
        );

        // La persona pidió martes, pero A termina el miércoles: B reacciona a
        // la dependencia y comienza el jueves, sin violarla.
        $this->assertStart($result, 'B', '2026-01-08 09:00');
        $this->assertFinish($result, 'B', '2026-01-09 18:00');
        $this->assertSame(0, $result->task('B')->totalFloatMinutes);

        $late = $this->calculate([
            new TaskNode(
                id: 'C',
                durationMinutes: 5 * self::DAY,
                requestedStart: $this->at('2026-01-08 09:00'),
                deadline: $this->at('2026-01-09 23:59:59'),
            ),
        ]);

        $this->assertSame(-3 * self::DAY, $late->task('C')->totalFloatMinutes);
    }

    // ---------------------------------------------------------------- 24 a 28

    #[Test]
    public function scenario_24_total_and_free_float_differ_on_the_same_task(): void
    {
        // A → B → D y A → C(larga) → D. B tiene holgura; su sucesora D no la usa.
        $result = $this->calculate(
            [$this->task('A'), $this->task('B'), $this->task('C', days: 5), $this->task('D')],
            [
                DependencyLink::finishToStart('A', 'B'),
                DependencyLink::finishToStart('A', 'C'),
                DependencyLink::finishToStart('B', 'D'),
                DependencyLink::finishToStart('C', 'D'),
            ],
        );

        // B puede retrasarse 4 jornadas sin mover el proyecto...
        $this->assertSame(4 * self::DAY, $result->task('B')->totalFloatMinutes);
        // ...y también 4 sin mover a D, porque D ya espera a C de todos modos.
        $this->assertSame(4 * self::DAY, $result->task('B')->freeFloatMinutes);
        // C, en cambio, no tiene ninguna.
        $this->assertSame(0, $result->task('C')->totalFloatMinutes);
    }

    #[Test]
    public function scenario_25_free_float_zero_with_total_float_above_zero(): void
    {
        // A → B → C, y una rama larga que fija el fin del proyecto.
        $result = $this->calculate(
            [$this->task('A'), $this->task('B'), $this->task('C'), $this->task('L', days: 6)],
            [
                DependencyLink::finishToStart('A', 'B'),
                DependencyLink::finishToStart('B', 'C'),
            ],
        );

        // B arranca martes y C miércoles: B no puede moverse ni un minuto sin
        // empujar a C, aunque el proyecto entero tenga holgura de sobra.
        $this->assertSame(0, $result->task('B')->freeFloatMinutes);
        $this->assertGreaterThan(0, $result->task('B')->totalFloatMinutes);
    }

    #[Test]
    public function scenario_26_a_single_critical_path(): void
    {
        $result = $this->calculate(
            [$this->task('A'), $this->task('B', days: 5), $this->task('C'), $this->task('D')],
            [
                DependencyLink::finishToStart('A', 'B'),
                DependencyLink::finishToStart('A', 'C'),
                DependencyLink::finishToStart('B', 'D'),
                DependencyLink::finishToStart('C', 'D'),
            ],
        );

        $this->assertSame(['A', 'B', 'D'], $result->criticalTaskIds);
        $this->assertCount(1, $result->criticalPaths);
        $this->assertFalse($result->task('C')->isCritical);
    }

    #[Test]
    public function scenario_27_two_critical_paths_when_they_tie(): void
    {
        // Dos ramas de la misma duración: las dos son críticas.
        $result = $this->calculate(
            [$this->task('A'), $this->task('B', days: 3), $this->task('C', days: 3), $this->task('D')],
            [
                DependencyLink::finishToStart('A', 'B'),
                DependencyLink::finishToStart('A', 'C'),
                DependencyLink::finishToStart('B', 'D'),
                DependencyLink::finishToStart('C', 'D'),
            ],
        );

        $this->assertTrue($result->task('B')->isCritical);
        $this->assertTrue($result->task('C')->isCritical);
        $this->assertCount(2, $result->criticalPaths);
    }

    #[Test]
    public function scenario_28_a_lead_longer_than_the_predecessor(): void
    {
        // Adelanto de 5 jornadas sobre una predecesora que dura 2: la sucesora
        // querría empezar antes del inicio del proyecto. No se permite.
        $result = $this->calculate(
            [$this->task('A', days: 2), $this->task('B')],
            [DependencyLink::finishToStart('A', 'B', -5 * self::DAY)],
        );

        $this->assertStart($result, 'B', '2026-01-05 09:00');
        $this->assertGreaterThanOrEqual(
            $this->at('2026-01-05 09:00')->getTimestamp(),
            $result->task('B')->earlyStart->getTimestamp(),
        );
    }

    // ---------------------------------------------------------------- 29 a 33

    #[Test]
    public function scenario_29_a_cycle_names_the_tasks_involved(): void
    {
        try {
            $this->calculate(
                [$this->task('A'), $this->task('B'), $this->task('C')],
                [
                    DependencyLink::finishToStart('A', 'B'),
                    DependencyLink::finishToStart('B', 'C'),
                    DependencyLink::finishToStart('C', 'A'),
                ],
            );

            $this->fail('Se esperaba una excepción de dependencia circular.');
        } catch (CircularDependencyException $exception) {
            // Lo importante no es que falle, sino que diga por dónde.
            $cycle = $exception->cycle();

            $this->assertSame($cycle[0], $cycle[count($cycle) - 1], 'El ciclo debe venir cerrado.');
            $this->assertEqualsCanonicalizing(['A', 'B', 'C'], array_unique($cycle));
            $this->assertStringContainsString('→', $exception->getMessage());
        }
    }

    #[Test]
    public function scenario_30_a_summary_takes_its_dates_from_its_children(): void
    {
        $result = $this->calculate([
            new TaskNode('P', 'Paquete'),
            new TaskNode('A', 'A', self::DAY, parentId: 'P'),
            new TaskNode('B', 'B', 3 * self::DAY, parentId: 'P'),
        ]);

        $this->assertStart($result, 'P', '2026-01-05 09:00');
        $this->assertFinish($result, 'P', '2026-01-07 18:00');
        $this->assertTrue($result->task('P')->isSummary);
    }

    #[Test]
    public function scenario_31_summaries_nested_three_levels_deep(): void
    {
        $result = $this->calculate([
            new TaskNode('N1', 'Nivel 1'),
            new TaskNode('N2', 'Nivel 2', parentId: 'N1'),
            new TaskNode('N3', 'Nivel 3', parentId: 'N2'),
            new TaskNode('hoja', 'Hoja', 2 * self::DAY, parentId: 'N3'),
        ]);

        foreach (['N1', 'N2', 'N3'] as $summary) {
            $this->assertStart($result, $summary, '2026-01-05 09:00');
            $this->assertFinish($result, $summary, '2026-01-06 18:00');
            $this->assertTrue($result->task($summary)->isSummary);
        }

        $this->assertSame('1', $result->task('N1')->wbsCode);
        $this->assertSame('1.1', $result->task('N2')->wbsCode);
        $this->assertSame('1.1.1', $result->task('N3')->wbsCode);
        $this->assertSame('1.1.1.1', $result->task('hoja')->wbsCode);
    }

    #[Test]
    public function scenario_32_a_project_starting_on_a_non_working_day(): void
    {
        // Domingo 4 de enero.
        $result = $this->calculate([$this->task('A')], start: '2026-01-04 09:00');

        $this->assertStart($result, 'A', '2026-01-05 09:00');
    }

    #[Test]
    public function scenario_33_a_task_without_predecessors_starts_with_the_project(): void
    {
        $result = $this->calculate(
            [$this->task('A', days: 3), $this->task('suelta')],
            [],
        );

        $this->assertStart($result, 'suelta', '2026-01-05 09:00');
    }

    // ---------------------------------------------------------------- 34 a 36

    #[Test]
    public function scenario_34_changing_the_calendar_recalculates_everything(): void
    {
        $tasks = [$this->task('A', days: 2), $this->task('B', days: 2)];
        $links = [DependencyLink::finishToStart('A', 'B')];

        $before = $this->calculate($tasks, $links);

        // Se declara feriado el martes: todo lo que venía después se recorre.
        $after = $this->calculate($tasks, $links, calendar: $this->calendar->withHoliday('2026-01-06'));

        $this->assertFinish($before, 'B', '2026-01-08 18:00');
        $this->assertFinish($after, 'B', '2026-01-09 18:00');
    }

    #[Test]
    public function scenario_35_variance_against_a_baseline_per_task_and_total(): void
    {
        $tasks = [$this->task('A', days: 2), $this->task('B', days: 2)];
        $links = [DependencyLink::finishToStart('A', 'B')];

        $baseline = $this->calculate($tasks, $links);

        // Se alarga A un día: B y el proyecto se recorren.
        $current = $this->calculate(
            [$this->task('A', days: 3), $this->task('B', days: 2)],
            $links,
        );

        $varianceB = $this->calendar->workingMinutesBetween(
            $baseline->task('B')->earlyFinish,
            $current->task('B')->earlyFinish,
        );

        $varianceProject = $this->calendar->workingMinutesBetween(
            $baseline->projectFinish,
            $current->projectFinish,
        );

        $this->assertSame(self::DAY, $varianceB, 'B se recorre exactamente un día.');
        $this->assertSame(self::DAY, $varianceProject);
        $this->assertSame(0, $this->calendar->workingMinutesBetween(
            $baseline->task('A')->earlyStart,
            $current->task('A')->earlyStart,
        ), 'A no se movió de inicio.');
    }

    #[Test]
    public function scenario_36_a_chain_of_five_hundred_tasks_is_correct_and_quick(): void
    {
        $tasks = [];
        $links = [];

        for ($i = 1; $i <= 500; $i++) {
            $tasks[] = $this->task("T{$i}");

            if ($i > 1) {
                $links[] = DependencyLink::finishToStart('T'.($i - 1), "T{$i}");
            }
        }

        $result = $this->calculate($tasks, $links);

        // 500 jornadas encadenadas: cada tarea arranca el día hábil siguiente.
        $this->assertStart($result, 'T1', '2026-01-05 09:00');
        $this->assertSame(
            499 * self::DAY,
            $this->calendar->workingMinutesBetween(
                $result->task('T1')->earlyFinish,
                $result->task('T500')->earlyFinish,
            ),
        );

        // Toda la cadena es crítica: no hay ninguna rama alterna.
        $this->assertCount(500, $result->criticalTaskIds);
        $this->assertLessThan(2000, $result->elapsedMilliseconds);
    }
}
