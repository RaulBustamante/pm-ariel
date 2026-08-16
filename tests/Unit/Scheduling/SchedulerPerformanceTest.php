<?php

declare(strict_types=1);

namespace Tests\Unit\Scheduling;

use App\Support\Scheduling\DependencyLink;
use App\Support\Scheduling\ScheduleNetwork;
use App\Support\Scheduling\Scheduler;
use App\Support\Scheduling\TaskNode;
use App\Support\Scheduling\WorkingCalendar;
use App\Support\Scheduling\WorkShift;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El compromiso del plan: 2,000 tareas se calculan en menos de 2 segundos.
 *
 * Es un límite de producto, no un capricho. Por encima de dos segundos la gente
 * deja de tocar el plan porque "se traba", y un plan que nadie toca deja de ser
 * cierto en una semana.
 *
 * La red no es una cadena: una cadena de 2,000 es el caso fácil. Se arma un
 * proyecto con la forma de uno real — paquetes, tareas en paralelo dentro de
 * cada uno, y ligas entre paquetes consecutivos.
 */
#[Group('performance')]
final class SchedulerPerformanceTest extends TestCase
{
    private const BUDGET_MILLISECONDS = 2000.0;

    #[Test]
    public function two_thousand_tasks_schedule_within_the_budget(): void
    {
        $tz = new DateTimeZone('America/Mexico_City');
        $calendar = WorkingCalendar::standard([WorkShift::fromTimes('09:00', '18:00')], $tz);

        [$tasks, $links] = $this->buildProject(packages: 100, perPackage: 20);

        $this->assertCount(2100, $tasks, '2,000 hojas más los 100 paquetes que las agrupan.');

        $network = new ScheduleNetwork($tasks, $links);

        $result = (new Scheduler)->schedule($network, new DateTimeImmutable('2026-01-05 09:00', $tz), $calendar);

        // Correcto antes que rápido: si el resultado está mal, el tiempo no importa.
        $this->assertCount(2100, $result->tasks);
        $this->assertTrue($result->task('P1')->isSummary);
        $this->assertGreaterThan(0, count($result->criticalTaskIds));

        $this->assertLessThan(
            self::BUDGET_MILLISECONDS,
            $result->elapsedMilliseconds,
            sprintf(
                'El cálculo tomó %.1f ms y el compromiso son %.0f ms.',
                $result->elapsedMilliseconds,
                self::BUDGET_MILLISECONDS,
            ),
        );

        fwrite(STDERR, sprintf(
            "\n  [rendimiento] 2,000 tareas en %.1f ms (presupuesto: %.0f ms)\n",
            $result->elapsedMilliseconds,
            self::BUDGET_MILLISECONDS,
        ));
    }

    /**
     * @return array{0: list<TaskNode>, 1: list<DependencyLink>}
     */
    private function buildProject(int $packages, int $perPackage): array
    {
        $day = 540;
        $tasks = [];
        $links = [];

        for ($p = 1; $p <= $packages; $p++) {
            $tasks[] = new TaskNode("P{$p}", "Paquete {$p}", sortOrder: $p);

            for ($t = 1; $t <= $perPackage; $t++) {
                $id = "P{$p}T{$t}";
                $tasks[] = new TaskNode($id, $id, durationMinutes: (1 + ($t % 5)) * $day, parentId: "P{$p}", sortOrder: $t);

                // Dentro del paquete, cadenas cortas en paralelo: unas dependen
                // de la anterior y otras arrancan libres.
                if ($t > 1 && $t % 4 !== 0) {
                    $links[] = DependencyLink::finishToStart("P{$p}T".($t - 1), $id);
                }
            }

            // Entre paquetes: la primera tarea del siguiente espera a la última
            // del anterior. Es lo que hace larga la ruta crítica.
            if ($p > 1) {
                $links[] = DependencyLink::finishToStart('P'.($p - 1)."T{$perPackage}", "P{$p}T1");
            }
        }

        return [$tasks, $links];
    }
}
