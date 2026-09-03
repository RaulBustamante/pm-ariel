<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Ver todo y trabajar en algo son dos preguntas distintas.
 *
 * El inicio contesta «¿qué traigo yo?» y esta pantalla «¿quién trae qué?». El
 * atajo que se usaba para lo segundo era meter al administrador como miembro de
 * todos los proyectos, y eso ensuciaba lo primero: su inicio se llenaba de
 * trabajo ajeno y «mi semana» contaba tareas que no eran suyas.
 *
 * Estas pruebas fijan las dos mitades del arreglo, porque cualquiera de las dos
 * sola deja el problema: que exista el lugar donde ver todo, y que el inicio
 * **no** sea ese lugar ni para un administrador.
 */
final class ProjectOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::query()->where('name', $role)->value('id'));

        return $user;
    }

    private function project(string $name, User $owner): Project
    {
        return Project::query()->create([
            'code' => strtoupper(substr(md5($name), 0, 8)),
            'name' => $name,
            'owner_id' => $owner->id,
            'planned_start' => '2026-01-05 09:00',
        ]);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    // -- Quién entra ---------------------------------------------------------

    #[Test]
    public function an_administrator_sees_every_project_and_who_is_on_it(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $stranger = $this->userWithRole(Role::PROJECT_MANAGER);

        // Un proyecto de alguien con quien el administrador no tiene ninguna
        // relación: no es su dueño, no es miembro y no es su jefe. Es el caso
        // que ninguna otra pantalla le mostraba.
        $ajeno = $this->project('Proyecto de alguien mas', $stranger);
        $ajeno->members()->attach($stranger->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Proyecto de alguien mas')
            ->assertSee($stranger->name)
            ->assertSee(__('projects.role_manager'));
    }

    #[Test]
    public function an_auditor_can_look_but_the_screen_is_read_only(): void
    {
        $auditor = $this->userWithRole(Role::AUDITOR);
        $owner = $this->userWithRole(Role::PROJECT_MANAGER);
        $this->project('Auditable', $owner);

        // El auditor ve todo (regla 4) y esta pantalla no escribe nada, así que
        // no hay nada que negarle aquí.
        $this->actingAs($auditor)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee('Auditable');
    }

    #[Test]
    public function a_project_manager_cannot_see_the_map_of_everyone(): void
    {
        $manager = $this->userWithRole(Role::PROJECT_MANAGER);

        $this->actingAs($manager)
            ->get(route('admin.projects.index'))
            ->assertForbidden();
    }

    #[Test]
    public function a_portfolio_manager_is_not_let_in_either(): void
    {
        // Se deja fuera a propósito: ampliar quién ve el trabajo ajeno es una
        // decisión de la organización, no un efecto secundario de un nombre de
        // rol que suena a «ve todo el portafolio».
        $portfolio = $this->userWithRole(Role::PORTFOLIO_MANAGER);

        $this->actingAs($portfolio)
            ->get(route('admin.projects.index'))
            ->assertForbidden();
    }

    // -- Qué contesta --------------------------------------------------------

    #[Test]
    public function it_answers_what_does_this_person_have(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $rodrigo = $this->userWithRole(Role::PROJECT_MANAGER);
        $alfredo = $this->userWithRole(Role::PORTFOLIO_MANAGER);

        $suyo = $this->project('Lo de Rodrigo', $admin);
        $suyo->members()->attach($rodrigo->id, ['project_role' => Project::ROLE_MEMBER]);

        $this->project('Lo de nadie mas', $admin);

        $this->actingAs($admin)
            ->get(route('admin.projects.index', ['user' => $rodrigo->id]))
            ->assertOk()
            ->assertSee('Lo de Rodrigo')
            ->assertDontSee('Lo de nadie mas');

        // Y la respuesta más útil de todas: «ninguno», dicha con palabras y no
        // con una tabla vacía que hace dudar de si el filtro sirvió.
        $this->actingAs($admin)
            ->get(route('admin.projects.index', ['user' => $alfredo->id]))
            ->assertOk()
            ->assertSee(__('project_overview.none_for_user', ['name' => $alfredo->name]));
    }

    #[Test]
    public function the_filter_counts_owning_a_project_not_only_being_on_its_team(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $owner = $this->userWithRole(Role::PROJECT_MANAGER);

        // Dueño sin estar en su propia lista de miembros. Pasa de verdad, y un
        // filtro que solo mirara `project_members` diría que no tiene nada.
        $this->project('Cargado sin ser miembro', $owner);

        $this->actingAs($admin)
            ->get(route('admin.projects.index', ['user' => $owner->id]))
            ->assertOk()
            ->assertSee('Cargado sin ser miembro');
    }

    #[Test]
    public function it_points_out_the_accounts_that_have_nothing(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $olvidado = $this->userWithRole(Role::TEAM_MEMBER);

        $mio = $this->project('Con equipo', $admin);
        $mio->members()->attach($admin->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee(__('project_overview.idle_title'))
            ->assertSee($olvidado->name);
    }

    #[Test]
    public function a_project_with_nobody_on_it_says_so(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $this->project('Sin nadie', $admin);

        // Un proyecto sin equipo es un dato —alguien lo creó y nadie puede
        // trabajarlo— y no un hueco en la tabla.
        $this->actingAs($admin)
            ->get(route('admin.projects.index'))
            ->assertOk()
            ->assertSee(__('project_overview.no_team'));
    }

    #[Test]
    public function a_filter_pointing_at_nobody_shows_the_whole_list_instead_of_failing(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $this->project('Existe', $admin);

        $this->actingAs($admin)
            ->get(route('admin.projects.index', ['user' => 999999]))
            ->assertOk()
            ->assertSee('Existe');
    }

    // -- El inicio se queda siendo «lo mío» ----------------------------------

    #[Test]
    public function the_home_page_does_not_show_an_administrator_other_peoples_work(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $stranger = $this->userWithRole(Role::PROJECT_MANAGER);

        $mio = $this->project('El mio', $admin);
        $mio->members()->attach($admin->id, ['project_role' => Project::ROLE_MANAGER]);

        $ajeno = $this->project('El de alguien mas', $stranger);
        $ajeno->members()->attach($stranger->id, ['project_role' => Project::ROLE_MANAGER]);

        // Esta es la mitad que arregla lo confuso. Antes el inicio se saltaba el
        // filtro para un administrador, así que «mi semana» y el portafolio
        // hablaban del trabajo de los demás sin decirlo.
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('El mio')
            ->assertDontSee('El de alguien mas');
    }

    #[Test]
    public function the_home_page_says_it_is_filtered_and_where_to_see_everything(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $mio = $this->project('El mio', $admin);
        $mio->members()->attach($admin->id, ['project_role' => Project::ROLE_MANAGER]);

        // Un administrador que aquí ve tres y sabe que hay veinte necesita poder
        // distinguir «no es mío» de «el sistema me está escondiendo algo».
        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('dashboard.scoped_to_me'))
            ->assertSee(route('admin.projects.index'), false);
    }

    #[Test]
    public function someone_who_cannot_see_everything_is_not_told_about_that_screen(): void
    {
        $manager = $this->userWithRole(Role::PROJECT_MANAGER);
        $mio = $this->project('El mio', $manager);
        $mio->members()->attach($manager->id, ['project_role' => Project::ROLE_MANAGER]);

        // Para quien no puede ver todo, el aviso no aplica: su inicio ya es todo
        // lo que hay para él, y el enlace solo llevaría a un 403.
        $this->actingAs($manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(__('dashboard.scoped_to_me'))
            ->assertDontSee(route('admin.projects.index'), false);
    }

    #[Test]
    public function the_project_list_does_keep_showing_everything_to_an_administrator(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);
        $stranger = $this->userWithRole(Role::PROJECT_MANAGER);
        $this->project('El de alguien mas', $stranger);

        // El listado sí es un listado: ahí ver todo nunca estorbó, y quitárselo
        // habría sido cambiar de problema en vez de resolverlo.
        $this->actingAs($admin)
            ->get(route('projects.index'))
            ->assertOk()
            ->assertSee('El de alguien mas');
    }
}
