<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Armar la jerarquía sin acomodar renglón por renglón.
 *
 * Las flechitas ya existían y funcionan, pero cada clic es un recálculo del
 * proyecto y una recarga de una pantalla que trae un formulario por renglón:
 * capturar las diez tareas de una semana con su estructura eran veinte viajes
 * al servidor, la mitad de ellos solo para acomodar algo que ya se sabía dónde
 * iba desde antes de escribirlo.
 *
 * Estas pruebas cubren los dos caminos que quitan ese trabajo:
 *
 * - **Nacer en su lugar.** `?parent=` apunta el alta a un paquete, y al guardar
 *   el formulario se queda ahí para la siguiente. Cero indentadas.
 * - **Mover en grupo.** Una selección y un destino, un solo recálculo.
 */
final class TaskOutlineSpeedTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'EDT-1',
            'name' => 'Proyecto con estructura',
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

    /** @param  array<string, mixed>  $extra */
    private function addTask(string $name, array $extra = []): Task
    {
        $this->actingAs($this->manager)->post(
            route('projects.tasks.store', $this->project),
            array_merge(['name' => $name, 'duration' => '1d'], $extra),
        );

        return Task::query()
            ->where('project_id', $this->project->id)
            ->where('name', $name)
            ->firstOrFail();
    }

    // -- Nacer dentro del paquete -------------------------------------------

    #[Test]
    public function a_task_can_be_born_inside_a_package_without_indenting(): void
    {
        $package = $this->addTask('Fase de arranque');

        $child = $this->addTask('Levantamiento', ['parent_id' => $package->id]);

        $this->assertSame($package->id, $child->parent_id);
        // Nació en su lugar: la EDT ya la numera como hija, sin haber pasado
        // nunca por `indent`.
        $this->assertSame('1.1', $child->wbs_code);
        $this->assertTrue($package->refresh()->is_summary);
    }

    #[Test]
    public function after_saving_a_subtask_the_form_stays_in_the_same_package(): void
    {
        $package = $this->addTask('Fase de arranque');

        $response = $this->actingAs($this->manager)->post(
            route('projects.tasks.store', $this->project),
            ['name' => 'Levantamiento', 'duration' => '1d', 'parent_id' => $package->id],
        );

        // Capturar cinco subtareas seguidas es escribir, Enter, escribir. Si el
        // regreso fuera a la lista pelona, entre cada una habría que volver a
        // buscar el renglón del paquete y oprimir «+».
        $response->assertRedirect(
            route('projects.tasks.index', [$this->project, 'parent' => $package->id]).'#nueva-tarea',
        );
    }

    #[Test]
    public function the_list_points_the_new_task_form_at_the_package_in_the_address(): void
    {
        $package = $this->addTask('Fase de arranque');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', [$this->project, 'parent' => $package->id]))
            ->assertOk()
            ->assertSee(__('tasks.new_subtask_of', ['name' => 'Fase de arranque']))
            ->assertSee('name="parent_id" value="'.$package->id.'"', false)
            // El foco entra solo: se viene de oprimir «+», no de abrir la lista.
            ->assertSee('autofocus', false);
    }

    #[Test]
    public function the_plain_list_does_not_steal_the_focus_or_preselect_a_package(): void
    {
        $this->addTask('Fase de arranque');

        // Se busca el campo oculto del alta y no cualquier `parent_id`: el
        // bloque de mover en grupo tiene su propio `<select name="parent_id">`
        // y siempre esta en la pantalla.
        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSee(__('tasks.new_task'))
            ->assertDontSee('<input type="hidden" name="parent_id"', false)
            ->assertDontSee('autofocus', false);
    }

    #[Test]
    public function a_package_from_another_project_is_ignored_instead_of_failing(): void
    {
        $other = Project::query()->create([
            'code' => 'OTRO-1',
            'name' => 'Otro proyecto',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-01-05 09:00',
        ]);
        $other->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $intruder = Task::query()->create([
            'project_id' => $other->id,
            'name' => 'Ajena',
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);

        // La lista sí existe; lo único que sobra es un número que no es de aquí.
        // Un 404 diría que la pantalla no está, y no es cierto.
        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', [$this->project, 'parent' => $intruder->id]))
            ->assertOk()
            ->assertDontSee('<input type="hidden" name="parent_id"', false);
    }

    // -- Mover en grupo ------------------------------------------------------

    #[Test]
    public function several_tasks_move_into_a_package_in_one_go(): void
    {
        $package = $this->addTask('Fase de arranque');
        $one = $this->addTask('Entrevistas');
        $two = $this->addTask('Encuestas');
        $three = $this->addTask('Reporte');

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [$one->id, $two->id, $three->id],
                'parent_id' => $package->id,
            ])
            ->assertSessionHas('status', __('tasks.bulk_moved', ['count' => 3]));

        foreach ([$one, $two, $three] as $moved) {
            $this->assertSame($package->id, $moved->refresh()->parent_id);
        }

        // El orden de la pantalla se respeta, no el que mandó el navegador.
        $this->assertSame('1.1', $one->refresh()->wbs_code);
        $this->assertSame('1.2', $two->refresh()->wbs_code);
        $this->assertSame('1.3', $three->refresh()->wbs_code);
    }

    #[Test]
    public function the_selection_keeps_the_order_of_the_screen_not_of_the_request(): void
    {
        $package = $this->addTask('Fase de arranque');
        $one = $this->addTask('Primera');
        $two = $this->addTask('Segunda');

        // Al revés a propósito: el orden en que el navegador serializa las
        // casillas no es una decisión del usuario, y no debería mandar.
        $this->actingAs($this->manager)->post(route('projects.tasks.reparent', $this->project), [
            'tasks' => [$two->id, $one->id],
            'parent_id' => $package->id,
        ]);

        $this->assertSame('1.1', $one->refresh()->wbs_code);
        $this->assertSame('1.2', $two->refresh()->wbs_code);
    }

    #[Test]
    public function an_empty_destination_pulls_the_tasks_up_to_the_top_level(): void
    {
        $package = $this->addTask('Fase de arranque');
        $child = $this->addTask('Detalle', ['parent_id' => $package->id]);

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [$child->id],
                'parent_id' => '',
            ])
            ->assertSessionHas('status');

        $this->assertNull($child->refresh()->parent_id);
        $this->assertFalse($package->refresh()->is_summary);
    }

    #[Test]
    public function a_package_and_something_from_inside_it_move_as_one_branch(): void
    {
        $target = $this->addTask('Destino');
        $package = $this->addTask('Paquete');
        $child = $this->addTask('Detalle', ['parent_id' => $package->id]);

        // Marcar el paquete y también su hija es lo que pasa al arrastrar el
        // ratón por la columna. Mover las dos por separado desarmaría la rama:
        // la hija acabaría de hermana de su propio padre.
        $this->actingAs($this->manager)->post(route('projects.tasks.reparent', $this->project), [
            'tasks' => [$package->id, $child->id],
            'parent_id' => $target->id,
        ]);

        $this->assertSame($target->id, $package->refresh()->parent_id);
        $this->assertSame($package->id, $child->refresh()->parent_id, 'La hija se queda con su padre.');
    }

    #[Test]
    public function a_task_cannot_be_moved_inside_its_own_subtask(): void
    {
        $package = $this->addTask('Paquete');
        $child = $this->addTask('Detalle', ['parent_id' => $package->id]);

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [$package->id],
                'parent_id' => $child->id,
            ])
            ->assertSessionHas('error', __('tasks.bulk_cycle', ['name' => 'Detalle']));

        // El plan no se queda a medias: se valida antes de escribir.
        $this->assertNull($package->refresh()->parent_id);
        $this->assertSame($package->id, $child->refresh()->parent_id);
    }

    #[Test]
    public function a_task_cannot_be_moved_inside_itself(): void
    {
        $task = $this->addTask('Sola');

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [$task->id],
                'parent_id' => $task->id,
            ])
            ->assertSessionHas('error', __('tasks.bulk_into_itself'));

        $this->assertNull($task->refresh()->parent_id);
    }

    #[Test]
    public function moving_without_marking_anything_says_so_instead_of_failing(): void
    {
        $this->addTask('Una');

        // Los números de otro proyecto se filtran antes de mover, así que una
        // selección puede quedar vacía aunque el formulario traiga algo.
        $this->actingAs($this->manager)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [999999],
                'parent_id' => '',
            ])
            ->assertSessionHas('warning', __('tasks.bulk_none_selected'));
    }

    #[Test]
    public function a_destination_from_another_project_is_not_reachable(): void
    {
        $mine = $this->addTask('Mía');

        $other = Project::query()->create([
            'code' => 'OTRO-2',
            'name' => 'Otro proyecto',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-01-05 09:00',
        ]);
        $other->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $intruder = Task::query()->create([
            'project_id' => $other->id,
            'name' => 'Ajena',
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);

        // Es un proyecto al que sí se tiene acceso, así que la Policy no lo
        // detendría: la que cierra la puerta es la comprobación de pertenencia.
        $this->actingAs($this->manager)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [$mine->id],
                'parent_id' => $intruder->id,
            ])
            ->assertNotFound();

        $this->assertNull($mine->refresh()->parent_id);
    }

    #[Test]
    public function someone_who_cannot_edit_the_project_cannot_move_tasks(): void
    {
        $task = $this->addTask('Una');

        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->post(route('projects.tasks.reparent', $this->project), [
                'tasks' => [$task->id],
                'parent_id' => null,
            ])
            ->assertForbidden();
    }

    // -- Lo que la pantalla ofrece -------------------------------------------

    #[Test]
    public function every_row_offers_to_create_a_subtask_and_to_be_selected(): void
    {
        $task = $this->addTask('Fase de arranque');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            // El «+» del renglón: un enlace a la misma lista apuntada a este
            // paquete, para que se pueda compartir y abrir en otra pestaña.
            ->assertSee(route('projects.tasks.index', [$this->project, 'parent' => $task->id]).'#nueva-tarea', false)
            ->assertSee('name="tasks[]" value="'.$task->id.'"', false)
            ->assertSee(__('tasks.bulk_title'))
            ->assertSee(__('tasks.bulk_apply'));
    }

    /**
     * La casilla tiene que apuntar al formulario que la envía.
     *
     * Es el fallo que nadie ve venir: un `<form>` no puede vivir entre renglones
     * de una tabla, así que las casillas alcanzan el suyo con el atributo
     * `form`. Si ese identificador se queda vacío o deja de coincidir, el
     * navegador no reclama nada —simplemente no manda la selección— y la
     * pantalla contesta «no marcaste ninguna tarea» a alguien que marcó cinco.
     */
    #[Test]
    public function the_checkboxes_point_at_the_form_that_submits_them(): void
    {
        $task = $this->addTask('Fase de arranque');

        $html = $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->getContent();

        $checkbox = 'name="tasks[]" value="'.$task->id.'"';
        $position = strpos($html, $checkbox);

        $this->assertNotFalse($position, 'El renglón no trae su casilla.');

        // El `form=` va en la misma etiqueta, no en cualquier parte del HTML.
        $tag = substr($html, (int) $position - 200, 400);
        $this->assertStringContainsString('form="bulk-outline"', $tag);

        // Y del otro lado tiene que existir un formulario con ese nombre.
        $this->assertStringContainsString('id="bulk-outline"', $html);
        $this->assertStringContainsString(
            route('projects.tasks.reparent', $this->project),
            $html,
            'El formulario de grupo apunta a otra parte.',
        );
    }
}
