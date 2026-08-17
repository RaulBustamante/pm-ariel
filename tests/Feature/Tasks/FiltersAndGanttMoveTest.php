<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Scheduling\ConstraintType;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Filtro compartido entre vistas (4.19), tope de renglones (4.11) y arrastre en
 * el Gantt (4.12).
 */
final class FiltersAndGanttMoveTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false, 'name' => 'Ana Gerente']);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'FIL-1',
            'name' => 'Proyecto filtrable',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
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
            'duration_minutes' => 2 * self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return $task->refresh();
    }

    // -------------------------------------------------------------- Filtros

    #[Test]
    public function the_search_filters_the_list(): void
    {
        $this->task('Levantamiento de requerimientos');
        $this->task('Pruebas con usuarios');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', ['project' => $this->project, 'q' => 'pruebas']))
            ->assertOk()
            ->assertSee('Pruebas con usuarios')
            ->assertDontSee('Levantamiento de requerimientos');
    }

    #[Test]
    public function only_mine_filters_by_owner(): void
    {
        $this->task('Mía', ['owner_id' => $this->manager->id]);
        $this->task('De alguien más');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', ['project' => $this->project, 'mine' => 1]))
            ->assertOk()
            ->assertSee('Mía')
            ->assertDontSee('De alguien más');
    }

    /**
     * Los nombres no repiten texto de la interfaz a propósito: el propio menú de
     * filtros dice «Sin empezar», así que una tarea con ese nombre haría que la
     * prueba se contradijera sola.
     */
    #[Test]
    public function the_progress_filter_works(): void
    {
        $this->task('Cierre contable', ['percent_complete' => 100]);
        $this->task('Migración pendiente');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', ['project' => $this->project, 'progress' => 'done']))
            ->assertOk()
            ->assertSee('Cierre contable')
            ->assertDontSee('Migración pendiente');
    }

    /**
     * Si cada vista tuviera su propio filtro, la gente filtraría en la Lista,
     * saltaría al Gantt, vería todo otra vez y concluiría que no sirve.
     */
    #[Test]
    public function the_filter_survives_switching_tabs(): void
    {
        $this->task('Levantamiento');
        $this->task('Pruebas');

        $response = $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', ['project' => $this->project, 'q' => 'pruebas']))
            ->assertOk();

        // Los enlaces de las pestañas arrastran el filtro.
        $response->assertSee('q=pruebas', escape: false);

        $this->actingAs($this->manager)
            ->get(route('projects.gantt', ['project' => $this->project, 'q' => 'pruebas']))
            ->assertOk()
            ->assertSee('Pruebas')
            ->assertDontSee('Levantamiento');
    }

    #[Test]
    public function the_filter_applies_to_the_board_and_the_calendar_too(): void
    {
        $this->task('Levantamiento');
        $this->task('Pruebas');

        foreach (['projects.kanban', 'projects.calendar'] as $route) {
            $this->actingAs($this->manager)
                ->get(route($route, ['project' => $this->project, 'q' => 'pruebas']))
                ->assertOk()
                ->assertSee('Pruebas')
                ->assertDontSee('Levantamiento');
        }
    }

    // ---------------------------------------------------------- Arrastre

    /**
     * Arrastrar deja una restricción, no una fecha suelta: así el motor la
     * respeta al recalcular y el detalle dice de dónde salió.
     */
    #[Test]
    public function dragging_a_bar_leaves_a_constraint(): void
    {
        $task = $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->post(route('projects.gantt.move', $this->project), ['task' => $task->id, 'days' => 3])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame(ConstraintType::StartNoEarlierThan->value, $task->constraint_type);
        $this->assertNotNull($task->constraint_date);
        $this->assertSame('2026-03-05', $task->early_start?->format('Y-m-d'));
    }

    #[Test]
    public function a_package_cannot_be_dragged(): void
    {
        $package = $this->task('Paquete');
        $this->task('Hija', ['parent_id' => $package->id]);

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)
            ->post(route('projects.gantt.move', $this->project), ['task' => $package->id, 'days' => 2])
            ->assertSessionHas('warning', __('gantt.cannot_move_summary'));
    }

    #[Test]
    public function someone_without_write_access_cannot_drag(): void
    {
        $task = $this->task('Levantamiento');

        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->post(route('projects.gantt.move', $this->project), ['task' => $task->id, 'days' => 3])
            ->assertForbidden();
    }

    // ------------------------------------------------------- Tope de renglones

    /**
     * El motor aguanta 2,000 tareas en 220 ms, pero mil renglones de formulario
     * en una sola página son varios megabytes de HTML y un navegador arrastrado.
     */
    #[Test]
    public function a_huge_plan_is_capped_and_says_so(): void
    {
        $rows = [];

        for ($i = 1; $i <= 320; $i++) {
            $rows[] = [
                'project_id' => $this->project->id,
                'name' => "Tarea {$i}",
                'duration_minutes' => self::DAY,
                'sort_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        Task::query()->insert($rows);
        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSee(__('tasks.showing_capped', ['shown' => 300, 'total' => 320]));
    }
}
