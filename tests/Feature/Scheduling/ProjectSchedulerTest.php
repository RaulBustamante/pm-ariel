<?php

declare(strict_types=1);

namespace Tests\Feature\Scheduling;

use App\Models\Baseline;
use App\Models\Calendar;
use App\Models\Project;
use App\Models\ScheduleRun;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\Scheduling\BaselineManager;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Scheduling\WorkingCalendar;
use App\Support\Scheduling\WorkShift;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * El motor ya está probado sin base de datos. Aquí se prueba el puente: que lo
 * que sale del cálculo se guarde completo, quede constancia de cada corrida, y
 * que una línea base sea de verdad inmutable.
 */
final class ProjectSchedulerTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private function project(): Project
    {
        $owner = User::factory()->create();

        $project = Project::query()->create([
            'code' => 'SCH-'.fake()->unique()->numberBetween(100, 999),
            'name' => 'Proyecto con plan',
            'owner_id' => $owner->id,
            'planned_start' => '2026-01-05 09:00',
        ]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Calendario del proyecto',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        return $project->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(Project $project, string $name, int $days, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $project->id,
            'name' => $name,
            'duration_minutes' => $days * self::DAY,
            'sort_order' => Task::query()->where('project_id', $project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        return $task;
    }

    /** Compara instantes, no cadenas: la zona horaria es parte del dato. */
    private function assertDate(string $expected, mixed $actual): void
    {
        $this->assertInstanceOf(DateTimeInterface::class, $actual);
        $this->assertSame($expected, $actual->format('Y-m-d H:i'));
    }

    private function calendar(): WorkingCalendar
    {
        return WorkingCalendar::standard(
            [WorkShift::fromTimes('09:00', '18:00')],
            new DateTimeZone('America/Mexico_City'),
        );
    }

    #[Test]
    public function the_calculated_dates_are_written_back_to_the_tasks(): void
    {
        $project = $this->project();
        $a = $this->task($project, 'A', 2);
        $b = $this->task($project, 'B', 1);

        TaskDependency::query()->create([
            'project_id' => $project->id,
            'predecessor_id' => $a->id,
            'successor_id' => $b->id,
            'type' => 'FS',
        ]);

        $result = app(ProjectScheduler::class)->reschedule($project);

        $this->assertNotNull($result);

        // A: lunes y martes. B: miércoles.
        $this->assertDate('2026-01-05 09:00', $a->refresh()->early_start);
        $this->assertDate('2026-01-06 18:00', $a->early_finish);
        $this->assertDate('2026-01-07 09:00', $b->refresh()->early_start);
        $this->assertTrue($b->is_critical);
    }

    #[Test]
    public function the_wbs_code_is_written_and_reflects_the_hierarchy(): void
    {
        $project = $this->project();
        $package = $this->task($project, 'Paquete', 0);
        $child = $this->task($project, 'Hija', 1, ['parent_id' => $package->id]);

        app(ProjectScheduler::class)->reschedule($project);

        $this->assertSame('1', $package->refresh()->wbs_code);
        $this->assertSame('1.1', $child->refresh()->wbs_code);
        $this->assertTrue($package->is_summary);
    }

    #[Test]
    public function a_summary_takes_its_dates_from_its_children(): void
    {
        $project = $this->project();
        $package = $this->task($project, 'Paquete', 0);
        $this->task($project, 'A', 1, ['parent_id' => $package->id]);
        $this->task($project, 'B', 4, ['parent_id' => $package->id]);

        app(ProjectScheduler::class)->reschedule($project);

        $this->assertDate('2026-01-05 09:00', $package->refresh()->early_start);
        $this->assertDate('2026-01-08 18:00', $package->early_finish);
    }

    #[Test]
    public function every_run_leaves_a_record(): void
    {
        $project = $this->project();
        $this->task($project, 'A', 1);

        app(ProjectScheduler::class)->reschedule($project);
        app(ProjectScheduler::class)->reschedule($project);

        $runs = ScheduleRun::query()->where('project_id', $project->id)->get();

        $this->assertCount(2, $runs);
        $this->assertSame(ScheduleRun::STATUS_OK, $runs->last()->status);
        $this->assertGreaterThan(0, $runs->last()->elapsed_ms);
    }

    /**
     * Un cálculo que falla es el que más interesa reconstruir después. Guardar
     * solo los buenos deja al usuario con "no se pudo calcular" y a nadie con
     * forma de saber por qué.
     */
    #[Test]
    public function a_cycle_is_recorded_with_the_tasks_involved(): void
    {
        $project = $this->project();
        $a = $this->task($project, 'A', 1);
        $b = $this->task($project, 'B', 1);

        foreach ([[$a, $b], [$b, $a]] as [$from, $to]) {
            TaskDependency::query()->create([
                'project_id' => $project->id,
                'predecessor_id' => $from->id,
                'successor_id' => $to->id,
                'type' => 'FS',
            ]);
        }

        $this->assertNull(app(ProjectScheduler::class)->reschedule($project));

        $run = ScheduleRun::query()->where('project_id', $project->id)->latest('id')->firstOrFail();

        $this->assertTrue($run->failed());
        $this->assertNotEmpty($run->failure_cycle);
        $this->assertEqualsCanonicalizing(
            [(string) $a->id, (string) $b->id],
            array_unique($run->failure_cycle ?? []),
        );
    }

    #[Test]
    public function a_task_with_its_own_calendar_uses_it(): void
    {
        $project = $this->project();

        $site = Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Obra',
            'key' => 'site',
            'timezone' => 'America/Mexico_City',
            // Lunes a sábado.
            'week' => array_fill_keys(range(1, 6), [['start' => '09:00', 'end' => '18:00']]),
            'is_default' => false,
        ]);

        $office = $this->task($project, 'Oficina', 6);
        $onSite = $this->task($project, 'Obra', 6, ['calendar_id' => $site->id]);

        app(ProjectScheduler::class)->reschedule($project);

        $this->assertDate('2026-01-12 18:00', $office->refresh()->early_finish);
        // La obra aprovecha el sábado 10 y cierra un día hábil antes.
        $this->assertDate('2026-01-10 18:00', $onSite->refresh()->early_finish);
    }

    // ------------------------------------------------------------ Líneas base

    #[Test]
    public function a_baseline_freezes_the_plan_and_the_variance_shows_the_slip(): void
    {
        $project = $this->project();
        $a = $this->task($project, 'A', 2);
        $b = $this->task($project, 'B', 2);

        TaskDependency::query()->create([
            'project_id' => $project->id,
            'predecessor_id' => $a->id,
            'successor_id' => $b->id,
            'type' => 'FS',
        ]);

        $scheduler = app(ProjectScheduler::class);
        $baselines = app(BaselineManager::class);

        $scheduler->reschedule($project);
        $baseline = $baselines->capture($project, 'Línea base original');

        // Se alarga A un día: B y el proyecto se recorren.
        $a->update(['duration_minutes' => 3 * self::DAY]);
        $scheduler->reschedule($project);

        $comparison = $baselines->compare($project, $baseline, $this->calendar());

        $rowB = collect($comparison['tasks'])->firstWhere('task_id', $b->id);

        $this->assertSame(self::DAY, $rowB['finish_variance_minutes'], 'B se recorre una jornada.');
        $this->assertSame(self::DAY, $comparison['finish_variance_minutes']);

        $rowA = collect($comparison['tasks'])->firstWhere('task_id', $a->id);
        $this->assertSame(0, $rowA['start_variance_minutes'], 'A no cambió de inicio.');
    }

    #[Test]
    public function the_baseline_does_not_move_when_the_plan_moves(): void
    {
        $project = $this->project();
        $a = $this->task($project, 'A', 2);

        app(ProjectScheduler::class)->reschedule($project);
        $baseline = app(BaselineManager::class)->capture($project, 'Original');

        $frozenFinish = $baseline->tasks()->firstOrFail()->finish;

        $a->update(['duration_minutes' => 10 * self::DAY]);
        app(ProjectScheduler::class)->reschedule($project);

        $this->assertEquals($frozenFinish, $baseline->refresh()->tasks()->firstOrFail()->finish);
    }

    #[Test]
    public function a_baseline_cannot_be_edited(): void
    {
        $project = $this->project();
        $this->task($project, 'A', 1);

        app(ProjectScheduler::class)->reschedule($project);
        $baseline = app(BaselineManager::class)->capture($project, 'Original');

        $this->expectException(RuntimeException::class);

        $baseline->update(['name' => 'Corregida a modo']);
    }

    #[Test]
    public function a_baseline_cannot_be_deleted(): void
    {
        $project = $this->project();
        $this->task($project, 'A', 1);

        app(ProjectScheduler::class)->reschedule($project);
        $baseline = app(BaselineManager::class)->capture($project, 'Original');

        $this->expectException(RuntimeException::class);

        $baseline->delete();
    }

    #[Test]
    public function only_one_baseline_is_active_at_a_time(): void
    {
        $project = $this->project();
        $this->task($project, 'A', 1);

        $scheduler = app(ProjectScheduler::class);
        $manager = app(BaselineManager::class);

        $scheduler->reschedule($project);

        $first = $manager->capture($project, 'Primera');
        $second = $manager->capture($project, 'Segunda');

        $this->assertFalse($first->refresh()->is_active);
        $this->assertTrue($second->refresh()->is_active);
        $this->assertSame(1, Baseline::query()->where('project_id', $project->id)->where('is_active', true)->count());
    }

    /**
     * Borrar del plan lo que no se alcanzó a hacer sería la forma más cómoda de
     * cumplir una línea base. El reporte no lo permite.
     */
    #[Test]
    public function a_task_removed_from_the_plan_still_shows_in_the_comparison(): void
    {
        $project = $this->project();
        $a = $this->task($project, 'A', 1);
        $b = $this->task($project, 'Se va a borrar', 1);

        app(ProjectScheduler::class)->reschedule($project);
        $baseline = app(BaselineManager::class)->capture($project, 'Original');

        $b->delete();
        app(ProjectScheduler::class)->reschedule($project);

        $comparison = app(BaselineManager::class)->compare($project, $baseline, $this->calendar());

        $this->assertCount(1, $comparison['removed']);
        $this->assertSame('Se va a borrar', $comparison['removed'][0]['name']);
        $this->assertNotNull($a->refresh()->early_start);
    }

    #[Test]
    public function a_task_added_after_the_baseline_is_flagged_as_new(): void
    {
        $project = $this->project();
        $this->task($project, 'A', 1);

        app(ProjectScheduler::class)->reschedule($project);
        $baseline = app(BaselineManager::class)->capture($project, 'Original');

        $this->task($project, 'Nueva', 1, ['cost' => 1500]);
        app(ProjectScheduler::class)->reschedule($project);

        $comparison = app(BaselineManager::class)->compare($project, $baseline, $this->calendar());

        $new = collect($comparison['tasks'])->firstWhere('is_new', true);

        $this->assertNotNull($new);
        $this->assertSame('Nueva', $new['name']);
        $this->assertSame(1500.0, $comparison['cost_variance']);
    }
}
