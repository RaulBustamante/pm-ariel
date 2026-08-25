<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

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
 * El selector de proyecto de la barra superior.
 *
 * Las dos reglas que pueden romperlo en silencio son las que se prueban aquí:
 * que no enseñe un proyecto que el usuario no puede abrir, y que el enlace que
 * ofrece no lleve a un 404 ni a un 403. Un selector que manda a una pantalla de
 * error se siente peor que no tenerlo.
 */
final class ProjectSwitcherTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    private Project $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = $this->makeProject('SW-1', 'Migración del ERP');
        $this->other = $this->makeProject('SW-2', 'Planta Norte');
    }

    private function makeProject(string $code, string $name): Project
    {
        $project = Project::query()->create([
            'code' => $code,
            'name' => $name,
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $project->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        return $project->refresh();
    }

    #[Test]
    public function lista_los_proyectos_que_el_usuario_puede_abrir(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project));

        $response->assertOk();
        $response->assertSee('data-project-switcher', escape: false);
        $response->assertSee('Planta Norte');
        $response->assertSee('SW-2');
    }

    #[Test]
    public function no_lista_un_proyecto_ajeno(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);

        $hidden = Project::query()->create([
            'code' => 'SW-9',
            'name' => 'Proyecto de otra cadena',
            'owner_id' => $stranger->id,
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project));

        $response->assertOk();
        $response->assertDontSee($hidden->name);
        $response->assertDontSee('SW-9');
    }

    #[Test]
    public function cambiarse_desde_el_gantt_lleva_al_gantt_del_otro_proyecto(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('projects.gantt', $this->project));

        $response->assertOk();
        $response->assertSee(route('projects.gantt', $this->other), escape: false);
    }

    #[Test]
    public function una_pantalla_que_cuelga_de_una_tarea_cae_al_tablero(): void
    {
        // La tarea existe en este proyecto y en ningún otro. Arrastrar su número
        // al proyecto de destino sería un 404 garantizado.
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => 'Levantamiento',
            'duration_minutes' => 2 * self::DAY,
            'sort_order' => 0,
        ]);
        $task->save();

        $response = $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', ['project' => $this->project, 'task' => $task]));

        $response->assertOk();
        $response->assertSee(route('projects.dashboard', $this->other), escape: false);
        $response->assertDontSee(
            route('projects.tasks.show', ['project' => $this->other, 'task' => $task]),
            escape: false,
        );
    }

    #[Test]
    public function un_codigo_de_documento_si_viaja_al_otro_proyecto(): void
    {
        // El código nombra un tipo del catálogo, no una fila de este proyecto:
        // el mismo documento existe en los dos y la pantalla se conserva.
        $response = $this->actingAs($this->manager)
            ->get(route('projects.documents.narrative', ['project' => $this->project, 'code' => 'business_case']));

        $response->assertOk();
        $response->assertSee(
            route('projects.documents.narrative', ['project' => $this->other, 'code' => 'business_case']),
            escape: false,
        );
    }

    #[Test]
    public function desde_ajustes_cae_al_tablero_del_proyecto_que_no_puede_editar(): void
    {
        // Lo ve porque es su dueño, pero sin membresía no lo puede editar, así
        // que Ajustes le daría 403. El selector lo manda al tablero en vez de eso.
        $readOnly = $this->makeProject('SW-3', 'Certificación ISO');
        $readOnly->members()->detach($this->manager->id);

        $response = $this->actingAs($this->manager)
            ->get(route('projects.edit', $this->project));

        $response->assertOk();
        $response->assertSee(route('projects.dashboard', $readOnly), escape: false);
        $response->assertDontSee(route('projects.edit', $readOnly), escape: false);
    }

    #[Test]
    public function con_un_solo_proyecto_no_hay_desplegable(): void
    {
        $this->other->forceDelete();

        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project));

        $response->assertOk();
        $response->assertDontSee('data-project-switcher', escape: false);
        // El título sigue estando: lo que desaparece es el desplegable, no el
        // nombre de la pantalla.
        $response->assertSee($this->project->name);
    }

    #[Test]
    public function el_buscador_solo_aparece_cuando_la_lista_es_larga(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project));

        $response->assertOk();
        $response->assertDontSee('data-project-switcher-filter', escape: false);

        for ($index = 3; $index <= 9; $index++) {
            $this->makeProject("SW-{$index}", "Proyecto {$index}");
        }

        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project));

        $response->assertOk();
        $response->assertSee('data-project-switcher-filter', escape: false);
    }

    #[Test]
    public function fuera_de_un_proyecto_no_se_pinta_el_selector(): void
    {
        $response = $this->actingAs($this->manager)->get(route('projects.index'));

        $response->assertOk();
        $response->assertDontSee('data-project-switcher', escape: false);
    }
}
