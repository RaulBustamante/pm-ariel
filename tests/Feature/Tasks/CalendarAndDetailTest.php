<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CalendarAndDetailTest extends TestCase
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
            'code' => 'CAL-1',
            'name' => 'Proyecto con calendario',
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
    private function task(string $name, int $days = 3, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $days * self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return $task->refresh();
    }

    // ------------------------------------------------------------- Calendario

    /**
     * Sin tareas capturadas el mes actual estaría vacío y parecería que no hay
     * nada. Se abre donde el proyecto tiene trabajo.
     */
    #[Test]
    public function the_calendar_opens_on_the_month_where_the_work_is(): void
    {
        $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->get(route('projects.calendar', $this->project))
            ->assertOk()
            ->assertSee('Levantamiento')
            ->assertSee('March 2026');
    }

    #[Test]
    public function the_month_can_be_navigated(): void
    {
        $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->get(route('projects.calendar', ['project' => $this->project, 'month' => '2026-04']))
            ->assertOk()
            ->assertSee('April 2026');
    }

    /**
     * Un mes de tres semanas y otro de seis harían brincar la pantalla al
     * navegar. Siempre son seis.
     */
    #[Test]
    public function the_grid_always_has_six_weeks(): void
    {
        $this->task('Levantamiento');

        $response = $this->actingAs($this->manager)->get(route('projects.calendar', $this->project));

        $response->assertOk();
        $this->assertSame(6, substr_count($response->getContent() ?: '', '<tr>') - 1, 'Seis semanas más el encabezado.');
    }

    #[Test]
    public function summary_tasks_do_not_appear_on_the_calendar(): void
    {
        $package = $this->task('Paquete', days: 1);
        $this->task('Hija', days: 2, attributes: ['parent_id' => $package->id]);

        $this->actingAs($this->manager)
            ->get(route('projects.calendar', $this->project))
            ->assertOk()
            ->assertSee('Hija')
            ->assertDontSee('>Paquete<', escape: false);
    }

    #[Test]
    public function a_project_without_dates_shows_its_empty_state(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.calendar', $this->project))
            ->assertOk()
            ->assertSee(__('calendar.empty'));
    }

    // ------------------------------------------------------- Detalle de tarea

    #[Test]
    public function the_task_detail_shows_its_dates_and_relations(): void
    {
        $first = $this->task('Primera', days: 2);
        $second = $this->task('Segunda', days: 2);

        TaskDependency::query()->create([
            'project_id' => $this->project->id,
            'predecessor_id' => $first->id,
            'successor_id' => $second->id,
            'type' => 'FS',
        ]);

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $second]))
            ->assertOk()
            ->assertSee('Segunda')
            ->assertSee('Primera')
            // Desde la Etapa 9 se lee como una frase y no como un código: la
            // relación va en español y el nombre de la tarea al lado.
            ->assertSee(__('tasks.depends_on'))
            ->assertSee(__('tasks.rel_FS'));
    }

    /**
     * La bitácora existía desde la Etapa 1 y nunca se mostraba donde se
     * pregunta: frente a la tarea.
     *
     * Desde la Etapa 9 va mezclada con los comentarios en un solo hilo, así que
     * lo que se comprueba es que el cambio siga apareciendo ahí — y con el
     * nombre del campo en español, no con el de la columna.
     */
    #[Test]
    public function the_task_detail_shows_who_changed_what(): void
    {
        $task = $this->task('Con historia');

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $task]), [
            'name' => 'Con historia corregida',
            'duration' => '4d',
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee(__('tasks.comments'))
            ->assertSee(__('tasks.duration'))
            ->assertSee($this->manager->name);
    }

    #[Test]
    public function a_resource_can_be_assigned_from_the_task_detail(): void
    {
        $task = $this->task('Con recurso');

        $resource = new Resource;
        $resource->fill(['project_id' => $this->project->id, 'name' => 'Ana', 'capacity_percent' => 100]);
        $resource->save();

        $this->actingAs($this->manager)
            ->post(route('projects.assignments.store', [$this->project, $task]), [
                'resource_id' => $resource->id,
                'units_percent' => 50,
            ])
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('50 %');
    }

    #[Test]
    public function a_task_from_another_project_is_not_reachable(): void
    {
        $other = Project::query()->create([
            'code' => 'OTRO-1',
            'name' => 'Otro',
            'owner_id' => $this->manager->id,
        ]);
        $other->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $task = $this->task('Mía');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$other, $task]))
            ->assertNotFound();
    }
}
