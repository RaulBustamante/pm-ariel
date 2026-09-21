<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\DetailLevel;
use Database\Seeders\ProjectTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El asistente de cuatro pasos (WF-002).
 *
 * Lo que de verdad decide si sirve está en la última línea del flujo: los
 * entregables se vuelven tareas. Sin eso, el asistente termina en una pantalla
 * vacía y el usuario tiene que empezar de nuevo.
 */
final class ProjectWizardTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProjectTemplatesSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Sistema de inventario',
            'code' => 'INV-9',
            'description' => 'Control de refacciones',
            'planned_start' => '2026-04-06',
            ...$overrides,
        ];
    }

    /**
     * El alta dejó de ser cuatro pasos en fila. Lo esencial queda a la vista y
     * el resto vive plegado en «Más opciones»: es el reclamo que originó el
     * cambio —demasiadas opciones de entrada— y lo que esta prueba cuida es
     * que nadie vuelva a desplegarlo todo sin querer.
     */
    #[Test]
    public function the_screen_shows_the_essentials_and_keeps_the_rest_folded(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee(__('wizard.deliverables_become_tasks'))
            ->assertSee(__('common.detail_level'))
            // Siguen existiendo, pero adentro del plegado.
            ->assertSee(__('wizard.more_options'))
            ->assertSee(__('wizard.step_who'))
            ->assertSee(__('wizard.step_when'))
            ->assertSee(__('wizard.step_measure'));

        // Cerrado de entrada: un `<details>` sin `open`. Si alguien le pone el
        // atributo, el muro de campos vuelve y esta prueba lo dice.
        $html = $response->getContent();
        $this->assertIsString($html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringNotContainsString('<details class="border-t border-slate-100 pt-5" open', $html);
    }

    /**
     * La razón de ser del bloque: terminar con un plan, no con una pantalla
     * vacía.
     */
    #[Test]
    public function the_deliverables_become_first_level_tasks(): void
    {
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'deliverables' => "Documento de requerimientos\n- Ambiente de pruebas\n\nSistema en producción",
        ]))->assertRedirect();

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();
        $tasks = Task::query()->where('project_id', $project->id)->orderBy('sort_order')->get();

        $this->assertCount(3, $tasks);
        // Se limpia la viñeta del segundo renglón: nadie debería tener que
        // acordarse de no ponerla.
        $this->assertSame(
            ['Documento de requerimientos', 'Ambiente de pruebas', 'Sistema en producción'],
            $tasks->pluck('name')->all(),
        );
        $this->assertTrue($tasks->every(fn (Task $task): bool => $task->parent_id === null));
    }

    #[Test]
    public function the_seeded_tasks_get_their_dates_calculated(): void
    {
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'deliverables' => 'Entrega final',
        ]));

        $task = Task::query()->where('name', 'Entrega final')->firstOrFail();

        $this->assertNotNull($task->early_start);
        $this->assertSame('1', $task->wbs_code);
    }

    #[Test]
    public function the_success_criteria_reach_the_charter(): void
    {
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'success_criteria' => 'El cierre de marzo se hace en un día.',
        ]));

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame('El cierre de marzo se hace en un día.', $project->charter?->success_criteria);
    }

    #[Test]
    public function the_start_date_is_stored_and_used(): void
    {
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'deliverables' => 'Entrega',
        ]));

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame('2026-04-06', $project->planned_start?->format('Y-m-d'));
    }

    /**
     * La fecha de inicio salió de la parte visible del alta, así que dejarla
     * vacía ya no es un error: el proyecto arranca hoy. Rechazarla sería
     * mandar a la gente de vuelta al formulario por un campo que ni le
     * preguntamos.
     */
    #[Test]
    public function an_empty_start_means_the_project_begins_today(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.store'), $this->payload(['planned_start' => '']))
            ->assertSessionHasNoErrors();

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame(now()->toDateString(), $project->planned_start?->format('Y-m-d'));
    }

    #[Test]
    public function the_committed_date_cannot_precede_the_start(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.store'), $this->payload([
                'planned_finish' => '2026-04-05',
            ]))
            ->assertSessionHasErrors('planned_finish');

        $this->actingAs($this->manager)
            ->post(route('projects.store'), $this->payload([
                'planned_finish' => '2026-04-30',
            ]))
            ->assertRedirect();

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame('2026-04-30', $project->planned_finish?->format('Y-m-d'));
    }

    // ------------------------------------------- Nivel de detalle

    /**
     * Sin escoger nada, el proyecto nace en Estándar. Es la mitad del arreglo
     * que pidieron: quien no sabe que existe un nivel no tiene que enterarse.
     */
    #[Test]
    public function a_project_is_born_in_standard(): void
    {
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload());

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame(DetailLevel::Standard, $project->detailLevel());
        $this->assertFalse($project->isSpecialist());
    }

    #[Test]
    public function the_level_can_be_chosen_while_creating(): void
    {
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'detail_level' => DetailLevel::Specialist->value,
        ]));

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertTrue($project->isSpecialist());
    }

    /**
     * Un nivel inventado no tumba el alta: cae en Estándar. Un proyecto a
     * medio crear es peor que un proyecto que enseña de menos.
     */
    #[Test]
    public function an_unknown_level_is_rejected_without_losing_the_form(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.store'), $this->payload(['detail_level' => 'gerente']))
            ->assertSessionHasErrors('detail_level');

        $this->assertDatabaseMissing('projects', ['code' => 'INV-9']);
    }

    /**
     * La preferencia de quien crea solo decide qué viene marcado. El nivel
     * vive en el proyecto, así que la preferencia no puede mandar después.
     */
    #[Test]
    public function the_creators_preference_seeds_the_marked_level(): void
    {
        $this->manager->update(['expert_mode' => true]);

        $this->actingAs($this->manager)
            ->get(route('projects.create'))
            ->assertOk()
            ->assertSee('value="'.DetailLevel::Specialist->value.'"', false);

        // Y aun así el proyecto guarda lo que se mandó, no la preferencia.
        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'detail_level' => DetailLevel::Standard->value,
        ]));

        $this->assertFalse(
            Project::query()->where('code', 'INV-9')->firstOrFail()->isSpecialist(),
        );
    }

    #[Test]
    public function the_chosen_members_can_edit_the_plan(): void
    {
        $other = User::factory()->create(['is_active' => true]);
        $other->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($this->manager)->post(route('projects.store'), $this->payload([
            'members' => [$other->id],
        ]));

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame(Project::ROLE_MEMBER, $other->fresh()->projectRoleFor($project));
        // El dueño sigue siendo gerente, no se degrada a miembro.
        $this->assertSame(Project::ROLE_MANAGER, $this->manager->fresh()->projectRoleFor($project));
    }

    #[Test]
    public function a_project_without_deliverables_still_gets_created(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.store'), $this->payload())
            ->assertRedirect();

        $project = Project::query()->where('code', 'INV-9')->firstOrFail();

        $this->assertSame(0, Task::query()->where('project_id', $project->id)->count());
        $this->assertNotNull($project->charter);
    }

    #[Test]
    public function someone_without_the_permission_cannot_open_the_wizard(): void
    {
        $viewer = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $viewer->roles()->attach(Role::query()->where('name', Role::VIEWER)->value('id'));

        $this->actingAs($viewer)->get(route('projects.create'))->assertForbidden();

        $this->assertFalse($viewer->hasPermission(Permission::PROJECTS_MANAGE));
    }
}
