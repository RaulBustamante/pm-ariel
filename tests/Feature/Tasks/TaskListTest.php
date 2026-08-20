<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Support\Scheduling\GanttLayout;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TaskListTest extends TestCase
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
            'code' => 'PLAN-1',
            'name' => 'Proyecto con plan',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-01-05 09:00',
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

    private function addTask(string $name, string $duration = '1d'): Task
    {
        $this->actingAs($this->manager)->post(route('projects.tasks.store', $this->project), [
            'name' => $name,
            'duration' => $duration,
        ]);

        return Task::query()->where('project_id', $this->project->id)->where('name', $name)->firstOrFail();
    }

    #[Test]
    public function adding_a_task_calculates_its_dates_right_away(): void
    {
        $task = $this->addTask('Levantar requerimientos', '2d');

        // El plan arranca el lunes 5. Dos jornadas de 9 h: lunes y martes.
        $this->assertSame('2026-01-05', $task->early_start?->format('Y-m-d'));
        $this->assertSame('2026-01-06', $task->early_finish?->format('Y-m-d'));
        $this->assertSame('1', $task->wbs_code);
    }

    /**
     * La lista tiene que poder contestar sola «¿qué es ese número?».
     *
     * El campo «Depende de» se guarda y se vuelve a dibujar con el código de la
     * EDT, pero la primera columna mostraba la posición en pantalla: dos números
     * distintos, ninguno explicado, y el usuario tecleaba «3» y le aparecía
     * «1.2». Estas tres cosas juntas son las que cierran el hueco.
     */
    #[Test]
    public function the_list_explains_the_number_written_under_depends_on(): void
    {
        $first = $this->addTask('Montaje de racks');
        $second = $this->addTask('Acomodo en racks');

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $second]), [
            'name' => $second->name,
            'duration' => '1d',
            'predecessors' => '1',
        ])->assertRedirect();

        $response = $this->actingAs($this->manager)->get(route('projects.tasks.index', $this->project));

        $response->assertOk();
        // El número de la primera columna es el mismo que se escribe en el campo.
        $response->assertSee($first->refresh()->wbs_code, false);
        $response->assertSee(__('tasks.reference_of', ['name' => $first->name]));
        // Y la dependencia se puede leer sin conocer el código.
        $response->assertSee(__('tasks.rel_FS_short').' '.$first->wbs_code.' '.$first->name);
        $response->assertSee(__('glossary.predecessor_label'));
    }

    /** El doble clic del tablero, también en la lista. */
    #[Test]
    public function a_row_carries_the_address_of_its_detail(): void
    {
        $task = $this->addTask('Con detalle');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSee('data-task-url="'.route('projects.tasks.show', [$this->project, $task]).'"', false)
            ->assertSee(__('tasks.detail_hint_row'))
            ->assertSee(__('tasks.legend'));
    }

    #[Test]
    public function a_task_can_choose_its_start_and_a_congruent_deadline(): void
    {
        $this->actingAs($this->manager)->post(route('projects.tasks.store', $this->project), [
            'name' => 'Inicio elegido',
            'duration' => '2d',
            'requested_start' => '2026-01-12',
            'deadline' => '2026-01-14',
        ])->assertRedirect();

        $task = Task::query()->where('name', 'Inicio elegido')->firstOrFail();

        $this->assertSame('2026-01-12', $task->requested_start?->format('Y-m-d'));
        $this->assertSame('2026-01-14', $task->deadline?->format('Y-m-d'));
        $this->assertSame('2026-01-12', $task->early_start?->format('Y-m-d'));

        $this->actingAs($this->manager)->post(route('projects.tasks.store', $this->project), [
            'name' => 'Fechas incongruentes',
            'duration' => '1d',
            'requested_start' => '2026-01-20',
            'deadline' => '2026-01-19',
        ])->assertSessionHasErrors('deadline');
    }

    #[Test]
    public function an_inline_update_does_not_erase_the_requested_dates(): void
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => 'Con fechas',
            'duration_minutes' => self::DAY,
            'requested_start' => '2026-01-12',
            'deadline' => '2026-01-20',
            'sort_order' => 1,
        ]);
        $task->save();

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $task]), [
            'name' => 'Con fechas editada',
            'duration' => '1d',
        ])->assertRedirect();

        $task->refresh();
        $this->assertSame('2026-01-12', $task->requested_start?->format('Y-m-d'));
        $this->assertSame('2026-01-20', $task->deadline?->format('Y-m-d'));
    }

    /** El campo acepta lo que la gente ya escribe sin pensarlo. */
    #[Test]
    public function the_duration_field_understands_how_people_write(): void
    {
        foreach (['3d' => 3 * self::DAY, '4h' => 240, '30m' => 30, '1.5d' => (int) (1.5 * self::DAY)] as $written => $minutes) {
            $task = $this->addTask("Tarea {$written}", $written);

            $this->assertSame($minutes, $task->duration_minutes, "«{$written}» debería ser {$minutes} minutos.");
        }
    }

    #[Test]
    public function a_zero_duration_task_is_a_milestone(): void
    {
        $this->assertTrue($this->addTask('Entrega', '0')->isMilestone());
    }

    #[Test]
    public function dependencies_are_written_as_text_and_shift_the_dates(): void
    {
        $first = $this->addTask('Primera', '2d');
        $second = $this->addTask('Segunda', '1d');

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $second]), [
            'name' => 'Segunda',
            'duration' => '1d',
            'predecessors' => '1',
        ])->assertRedirect();

        $this->assertSame(1, TaskDependency::query()->where('successor_id', $second->id)->count());
        // La primera cierra el martes; la segunda arranca el miércoles.
        $this->assertSame('2026-01-07', $second->refresh()->early_start?->format('Y-m-d'));
        $this->assertNotNull($first->refresh()->early_finish);
    }

    #[Test]
    public function a_lag_written_in_the_expression_is_respected(): void
    {
        $this->addTask('Primera', '1d');
        $second = $this->addTask('Segunda', '1d');

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $second]), [
            'name' => 'Segunda',
            'duration' => '1d',
            'predecessors' => '1FS+2d',
        ]);

        // La primera cierra el lunes. Dos jornadas de espera: jueves.
        $this->assertSame('2026-01-08', $second->refresh()->early_start?->format('Y-m-d'));
    }

    #[Test]
    public function an_unreadable_dependency_is_rejected_with_an_explanation(): void
    {
        $task = $this->addTask('Tarea', '1d');

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => 'Tarea',
                'duration' => '1d',
                'predecessors' => 'ayer por la tarde',
            ])
            ->assertSessionHasErrors('predecessors');
    }

    #[Test]
    public function depending_on_a_task_that_does_not_exist_is_rejected(): void
    {
        $task = $this->addTask('Tarea', '1d');

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => 'Tarea',
                'duration' => '1d',
                'predecessors' => '99',
            ])
            ->assertSessionHasErrors('predecessors');
    }

    #[Test]
    public function a_task_cannot_depend_on_itself(): void
    {
        $this->addTask('Primera', '1d');
        $second = $this->addTask('Segunda', '1d');

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $second]), [
                'name' => 'Segunda',
                'duration' => '1d',
                'predecessors' => '2',
            ])
            ->assertSessionHasErrors('predecessors');
    }

    #[Test]
    public function indenting_makes_the_task_above_a_summary(): void
    {
        $parent = $this->addTask('Paquete', '1d');
        $child = $this->addTask('Detalle', '3d');

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.outline', [$this->project, $child, 'indent']))
            ->assertRedirect();

        $this->assertSame($parent->id, $child->refresh()->parent_id);
        $this->assertTrue($parent->refresh()->is_summary);
        $this->assertSame('1.1', $child->wbs_code);
        // El resumen toma las fechas de su hija.
        $this->assertSame('2026-01-07', $parent->early_finish?->format('Y-m-d'));
    }

    #[Test]
    public function the_first_task_cannot_be_indented_and_says_why(): void
    {
        $first = $this->addTask('Primera', '1d');

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.outline', [$this->project, $first, 'indent']))
            ->assertSessionHas('warning', __('tasks.cannot_indent'));

        $this->assertNull($first->refresh()->parent_id);
    }

    #[Test]
    public function outdenting_returns_the_task_to_the_level_above(): void
    {
        $parent = $this->addTask('Paquete', '1d');
        $child = $this->addTask('Detalle', '1d');

        $this->actingAs($this->manager)->post(route('projects.tasks.outline', [$this->project, $child, 'indent']));
        $this->actingAs($this->manager)->post(route('projects.tasks.outline', [$this->project, $child, 'outdent']));

        $this->assertNull($child->refresh()->parent_id);
        $this->assertFalse($parent->refresh()->is_summary);
    }

    #[Test]
    public function moving_a_task_down_renumbers_the_wbs(): void
    {
        $first = $this->addTask('Primera', '1d');
        $second = $this->addTask('Segunda', '1d');

        $this->actingAs($this->manager)->post(route('projects.tasks.outline', [$this->project, $first, 'down']));

        $this->assertSame('2', $first->refresh()->wbs_code);
        $this->assertSame('1', $second->refresh()->wbs_code);
    }

    #[Test]
    public function a_cycle_does_not_leave_the_plan_half_calculated(): void
    {
        $a = $this->addTask('A', '1d');
        $b = $this->addTask('B', '1d');

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $b]), [
            'name' => 'B', 'duration' => '1d', 'predecessors' => '1',
        ]);

        // Ahora A depende de B: se cierra el círculo.
        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $a]), [
                'name' => 'A', 'duration' => '1d', 'predecessors' => '2',
            ])
            ->assertSessionHas('error');

        $run = $this->project->scheduleRuns()->first();

        $this->assertTrue($run?->failed());
    }

    #[Test]
    public function the_list_screen_renders_with_its_dates(): void
    {
        $this->addTask('Levantamiento', '2d');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSee('Levantamiento')
            ->assertSee('05/01/26');
    }

    #[Test]
    public function the_gantt_renders_and_places_the_bars(): void
    {
        $this->addTask('Levantamiento', '2d');

        $this->actingAs($this->manager)
            ->get(route('projects.gantt', $this->project))
            ->assertOk()
            ->assertSee('<svg', escape: false)
            ->assertSee('Levantamiento');
    }

    #[Test]
    public function the_gantt_zoom_changes_the_scale(): void
    {
        $this->addTask('Levantamiento', '5d');

        foreach ([GanttLayout::ZOOM_DAY, GanttLayout::ZOOM_WEEK, GanttLayout::ZOOM_MONTH] as $zoom) {
            $this->actingAs($this->manager)
                ->get(route('projects.gantt', ['project' => $this->project, 'zoom' => $zoom]))
                ->assertOk();
        }
    }

    /**
     * Ser jefe da lectura, no escritura (regla 2 de visibilidad). El plan de
     * trabajo no es la excepción.
     */
    #[Test]
    public function someone_who_is_not_a_member_cannot_add_tasks(): void
    {
        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->post(route('projects.tasks.store', $this->project), ['name' => 'Ajena', 'duration' => '1d'])
            ->assertForbidden();
    }
}
