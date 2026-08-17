<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Baseline;
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

final class ProjectSettingsTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'SET-1',
            'name' => 'Proyecto configurable',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
            'currency' => 'MXN',
        ]);

        $this->project->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        $this->project->refresh();
    }

    private function task(string $name, int $days = 2): Task
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

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => $this->project->name,
            'code' => $this->project->code,
            'status' => 'active',
            'currency' => 'MXN',
            'planned_start' => $this->project->planned_start?->format('Y-m-d'),
            ...$overrides,
        ];
    }

    #[Test]
    public function the_settings_screen_renders(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertSee(__('projects.details'))
            ->assertSee($this->project->code);
    }

    /**
     * Sin recálculo, el proyecto diría que empieza en abril mientras sus tareas
     * siguen con las fechas de marzo, y nadie notaría la contradicción hasta
     * imprimirlo.
     */
    #[Test]
    public function changing_the_start_date_moves_every_task(): void
    {
        $task = $this->task('Levantamiento');

        $this->assertSame('2026-03-02', $task->early_start?->format('Y-m-d'));

        $this->actingAs($this->manager)
            ->put(route('projects.update', $this->project), $this->payload(['planned_start' => '2026-04-06']))
            ->assertRedirect();

        $this->assertSame('2026-04-06', $task->refresh()->early_start?->format('Y-m-d'));
    }

    #[Test]
    public function a_duplicate_code_is_rejected(): void
    {
        Project::query()->create(['code' => 'OCUPADO', 'name' => 'Otro', 'owner_id' => $this->manager->id]);

        $this->actingAs($this->manager)
            ->put(route('projects.update', $this->project), $this->payload(['code' => 'OCUPADO']))
            ->assertSessionHasErrors('code');
    }

    #[Test]
    public function its_own_code_is_not_a_duplicate_of_itself(): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.update', $this->project), $this->payload(['name' => 'Nombre nuevo']))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nombre nuevo', $this->project->refresh()->name);
    }

    #[Test]
    public function a_member_can_be_added_and_removed(): void
    {
        $other = User::factory()->create(['is_active' => true]);

        $this->actingAs($this->manager)->post(route('projects.members.store', $this->project), [
            'user_id' => $other->id,
            'project_role' => Project::ROLE_MEMBER,
        ])->assertRedirect();

        $this->assertSame(Project::ROLE_MEMBER, $other->fresh()->projectRoleFor($this->project->refresh()));

        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $other]))
            ->assertRedirect();

        $this->assertNull($other->fresh()->projectRoleFor($this->project->refresh()));
    }

    /**
     * Quitar al dueño lo dejaría sin poder editar su propio proyecto, y
     * recuperarlo exigiría a un administrador.
     */
    #[Test]
    public function the_owner_cannot_be_removed_from_their_own_project(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('projects.members.destroy', [$this->project, $this->manager]))
            ->assertSessionHas('warning', __('projects.cannot_remove_owner'));

        $this->assertSame(Project::ROLE_MANAGER, $this->manager->fresh()->projectRoleFor($this->project->refresh()));
    }

    // ---------------------------------------------------------- Líneas base

    #[Test]
    public function a_baseline_is_captured_from_the_settings_screen(): void
    {
        $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->post(route('projects.baselines.store', $this->project), ['name' => 'Original'])
            ->assertRedirect();

        $this->assertSame(1, Baseline::query()->where('project_id', $this->project->id)->count());
    }

    #[Test]
    public function a_baseline_without_tasks_is_refused_with_an_explanation(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.baselines.store', $this->project), ['name' => 'Vacía'])
            ->assertSessionHas('warning', __('projects.baseline_needs_tasks'));

        $this->assertSame(0, Baseline::query()->where('project_id', $this->project->id)->count());
    }

    #[Test]
    public function the_comparison_shows_the_slip_per_task(): void
    {
        $task = $this->task('Levantamiento', days: 2);

        $this->actingAs($this->manager)->post(route('projects.baselines.store', $this->project), ['name' => 'Original']);

        $baseline = Baseline::query()->where('project_id', $this->project->id)->firstOrFail();

        $task->update(['duration_minutes' => 5 * self::DAY]);
        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)
            ->get(route('projects.baselines.compare', [$this->project, $baseline]))
            ->assertOk()
            ->assertSee('Levantamiento')
            ->assertSee(__('projects.finish_variance'));
    }

    /**
     * Sin la barra gris, el Gantt muestra el plan de hoy y nadie recuerda cuál
     * era el de hace un mes.
     */
    #[Test]
    public function the_gantt_draws_the_baseline_bar(): void
    {
        $this->task('Levantamiento');

        $this->actingAs($this->manager)->post(route('projects.baselines.store', $this->project), ['name' => 'Original']);

        $this->actingAs($this->manager)
            ->get(route('projects.gantt', $this->project))
            ->assertOk()
            ->assertSee('Original')
            ->assertSee('#94a3b8', escape: false);
    }

    #[Test]
    public function a_baseline_from_another_project_is_not_reachable(): void
    {
        $this->task('Levantamiento');
        $this->actingAs($this->manager)->post(route('projects.baselines.store', $this->project), ['name' => 'Original']);
        $baseline = Baseline::query()->firstOrFail();

        $other = Project::query()->create(['code' => 'AJENO', 'name' => 'Ajeno', 'owner_id' => $this->manager->id]);
        $other->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->actingAs($this->manager)
            ->get(route('projects.baselines.compare', [$other, $baseline]))
            ->assertNotFound();
    }

    #[Test]
    public function someone_without_write_access_cannot_change_the_settings(): void
    {
        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->put(route('projects.update', $this->project), $this->payload(['name' => 'Secuestrado']))
            ->assertForbidden();
    }
}
