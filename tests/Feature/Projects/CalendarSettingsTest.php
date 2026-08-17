<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Un feriado puede recorrer la entrega dos semanas. Enterarse **después** de
 * guardar es la peor forma de descubrirlo, y por eso el aviso de impacto es
 * parte del bloque y no un adorno.
 */
final class CalendarSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    private Calendar $calendar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'CALS-1',
            'name' => 'Proyecto con feriados',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $this->project->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->calendar = Calendar::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        $this->project->refresh();
    }

    private function task(string $name, int $days = 5): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $days * self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return $task->refresh();
    }

    #[Test]
    public function the_calendars_screen_renders(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.calendars.index', $this->project))
            ->assertOk()
            ->assertSee('Calendario')
            ->assertSee(__('calendars.exceptions'));
    }

    /**
     * La vista previa calcula con el calendario propuesto **sin guardarlo**. El
     * motor nunca tocó la base, así que simular no exige ningún truco.
     */
    #[Test]
    public function the_impact_preview_does_not_change_anything(): void
    {
        $task = $this->task('Levantamiento', days: 5);
        $before = $task->early_finish?->format('Y-m-d');

        $this->actingAs($this->manager)
            ->post(route('projects.calendars.preview', [$this->project, $this->calendar]), [
                'date' => '2026-03-04',
                'action' => 'holiday',
            ])
            ->assertOk()
            ->assertSee(__('calendars.impact_title'));

        $this->assertSame($before, $task->refresh()->early_finish?->format('Y-m-d'));
        $this->assertSame([], $this->calendar->refresh()->exceptions ?? []);
    }

    #[Test]
    public function the_preview_says_how_much_the_delivery_slips(): void
    {
        // Cinco jornadas desde el lunes 2: cierra el viernes 6.
        $this->task('Levantamiento', days: 5);

        // Se marca el miércoles 4 como feriado: la entrega debe recorrerse.
        $this->actingAs($this->manager)
            ->post(route('projects.calendars.preview', [$this->project, $this->calendar]), [
                'date' => '2026-03-04',
                'action' => 'holiday',
            ])
            ->assertOk()
            ->assertSee(__('calendars.impact_moved'));
    }

    /**
     * Marcar un día que a nadie afecta debe decirlo, no inventar un impacto.
     */
    #[Test]
    public function a_day_that_affects_nothing_says_so(): void
    {
        $this->task('Levantamiento', days: 2);

        $this->actingAs($this->manager)
            ->post(route('projects.calendars.preview', [$this->project, $this->calendar]), [
                'date' => '2026-12-25',
                'action' => 'holiday',
            ])
            ->assertOk()
            ->assertSee(__('calendars.impact_no_change'));
    }

    #[Test]
    public function applying_a_holiday_moves_the_plan(): void
    {
        $task = $this->task('Levantamiento', days: 5);

        $this->assertSame('2026-03-06', $task->early_finish?->format('Y-m-d'));

        $this->actingAs($this->manager)
            ->post(route('projects.calendars.exception', [$this->project, $this->calendar]), [
                'date' => '2026-03-04',
                'action' => 'holiday',
            ])
            ->assertRedirect();

        // Con el miércoles fuera, la quinta jornada cae el lunes siguiente.
        $this->assertSame('2026-03-09', $task->refresh()->early_finish?->format('Y-m-d'));
        $this->assertArrayHasKey('2026-03-04', $this->calendar->refresh()->exceptions ?? []);
    }

    #[Test]
    public function a_special_working_saturday_can_be_opened(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.calendars.exception', [$this->project, $this->calendar]), [
                'date' => '2026-03-07',
                'action' => 'workday',
            ])
            ->assertRedirect();

        $exceptions = $this->calendar->refresh()->exceptions ?? [];

        $this->assertNotSame([], $exceptions['2026-03-07'] ?? []);
    }

    #[Test]
    public function an_exception_can_be_removed(): void
    {
        $this->actingAs($this->manager)->post(route('projects.calendars.exception', [$this->project, $this->calendar]), [
            'date' => '2026-03-04', 'action' => 'holiday',
        ]);

        $this->actingAs($this->manager)->post(route('projects.calendars.exception', [$this->project, $this->calendar]), [
            'date' => '2026-03-04', 'action' => 'remove',
        ]);

        $this->assertArrayNotHasKey('2026-03-04', $this->calendar->refresh()->exceptions ?? []);
    }

    #[Test]
    public function a_second_calendar_can_be_created_and_made_default(): void
    {
        $this->actingAs($this->manager)->post(route('projects.calendars.store', $this->project), [
            'name' => 'Obra',
            'key' => 'obra',
            'timezone' => 'America/Mexico_City',
            'days' => [1, 2, 3, 4, 5, 6],
            'start' => '07:00',
            'end' => '17:00',
        ])->assertRedirect();

        $site = Calendar::query()->where('key', 'obra')->firstOrFail();

        $this->assertFalse($site->is_default);

        $this->actingAs($this->manager)
            ->post(route('projects.calendars.default', [$this->project, $site]))
            ->assertRedirect();

        $this->assertTrue($site->refresh()->is_default);
        $this->assertFalse($this->calendar->refresh()->is_default);
    }

    #[Test]
    public function a_duplicate_key_in_the_same_project_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.calendars.store', $this->project), [
                'name' => 'Repetido',
                'key' => Calendar::DEFAULT_KEY,
                'timezone' => 'America/Mexico_City',
                'days' => [1],
                'start' => '09:00',
                'end' => '18:00',
            ])
            ->assertSessionHasErrors('key');
    }

    #[Test]
    public function someone_without_write_access_cannot_touch_the_calendar(): void
    {
        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->post(route('projects.calendars.exception', [$this->project, $this->calendar]), [
                'date' => '2026-03-04', 'action' => 'holiday',
            ])
            ->assertForbidden();
    }
}
