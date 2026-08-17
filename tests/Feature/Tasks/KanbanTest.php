<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

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
 * El tablero no tiene datos propios: las columnas salen del avance de la tarea
 * (CL-004). Lo que estas pruebas cuidan es justamente eso — que no aparezca un
 * estado paralelo que pueda contradecir a la vista Lista.
 */
final class KanbanTest extends TestCase
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
            'code' => 'KB-1',
            'name' => 'Proyecto con tablero',
            'owner_id' => $this->manager->id,
            'planned_start' => now()->addWeek()->startOfWeek()->setTime(9, 0),
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(string $name, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        return $task;
    }

    #[Test]
    public function tasks_land_in_the_column_that_matches_their_progress(): void
    {
        $this->task('Sin empezar');
        $this->task('A medias', ['percent_complete' => 40]);
        $this->task('Lista', ['percent_complete' => 100]);

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertSeeInOrder([__('kanban.todo'), 'Sin empezar'])
            ->assertSee('A medias')
            ->assertSee('Lista');
    }

    #[Test]
    public function moving_a_card_changes_the_progress_of_the_task(): void
    {
        $task = $this->task('Por hacer');

        $this->actingAs($this->manager)
            ->post(route('projects.kanban.move', [$this->project, $task]), ['column' => 'done'])
            ->assertRedirect();

        $this->assertSame('100.00', $task->refresh()->percent_complete);
    }

    /**
     * Alguien pudo haber capturado 30 % a mano. Mover la tarjeta dentro de la
     * misma columna no debería redondear ese número a la mitad.
     */
    #[Test]
    public function moving_to_in_progress_does_not_overwrite_a_captured_number(): void
    {
        $task = $this->task('A medias', ['percent_complete' => 30]);

        $this->actingAs($this->manager)
            ->post(route('projects.kanban.move', [$this->project, $task]), ['column' => 'doing']);

        $this->assertSame('30.00', $task->refresh()->percent_complete);
    }

    #[Test]
    public function starting_a_task_that_was_untouched_marks_it_in_progress(): void
    {
        $task = $this->task('Sin empezar');

        $this->actingAs($this->manager)
            ->post(route('projects.kanban.move', [$this->project, $task]), ['column' => 'doing']);

        $progress = (float) $task->refresh()->percent_complete;

        $this->assertGreaterThan(0, $progress);
        $this->assertLessThan(100, $progress);
    }

    /** Un resumen no es trabajo que alguien haga: es el encabezado del trabajo. */
    #[Test]
    public function summary_tasks_do_not_show_up_as_cards(): void
    {
        $package = $this->task('Paquete');
        $this->task('Hija', ['parent_id' => $package->id]);

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertSee('Hija')
            ->assertDontSee('>Paquete<', escape: false);
    }

    #[Test]
    public function the_board_can_be_grouped_by_package(): void
    {
        $package = $this->task('Paquete');
        $this->task('Hija', ['parent_id' => $package->id]);

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', ['project' => $this->project, 'lane' => 'package']))
            ->assertOk()
            ->assertSee('Paquete')
            ->assertSee('Hija');
    }

    /**
     * El límite de trabajo en curso se dice, no se impide: el sistema no manda
     * sobre cómo trabaja el equipo, pero el número tiene que estar a la vista.
     */
    #[Test]
    public function exceeding_the_work_in_progress_limit_warns_without_blocking(): void
    {
        foreach (range(1, 6) as $index) {
            $this->task("En curso {$index}", ['percent_complete' => 50]);
        }

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertSee(__('kanban.wip_exceeded', ['limit' => 5]));
    }

    #[Test]
    public function an_unknown_column_is_rejected(): void
    {
        $task = $this->task('Tarea');

        $this->actingAs($this->manager)
            ->post(route('projects.kanban.move', [$this->project, $task]), ['column' => 'archivada'])
            ->assertNotFound();
    }

    #[Test]
    public function someone_without_write_access_cannot_move_cards(): void
    {
        $task = $this->task('Tarea');

        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->post(route('projects.kanban.move', [$this->project, $task]), ['column' => 'done'])
            ->assertForbidden();

        $this->assertSame('0.00', $task->refresh()->percent_complete);
    }
}
