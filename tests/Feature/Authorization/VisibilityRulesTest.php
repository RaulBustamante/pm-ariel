<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\OrgUnit;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Visibility\VisibilityScope;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Las cuatro reglas de visibilidad, una prueba por regla.
 *
 * Un permiso sin prueba no cuenta como implementado: esta clase es la evidencia
 * del criterio de cierre de la Etapa 1.
 */
final class VisibilityRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function rule_1_a_manager_sees_the_whole_chain_below_them_at_any_depth(): void
    {
        $director = $this->userWithRole(Role::PROJECT_MANAGER);
        $manager = $this->userWithRole(Role::PROJECT_MANAGER);
        $supervisor = $this->userWithRole(Role::TEAM_MEMBER);
        $member = $this->userWithRole(Role::TEAM_MEMBER);

        // Tres niveles: director -> gerente -> supervisor -> integrante
        $this->makeManagerOf($director, $manager);
        $this->makeManagerOf($manager, $supervisor);
        $this->makeManagerOf($supervisor, $member);

        $project = $this->projectOwnedBy($member);

        $this->assertTrue(
            $director->can('view', $project),
            'Un jefe debe ver los proyectos de toda su cadena hacia abajo, sin configurar nada.'
        );

        $stranger = $this->userWithRole(Role::PROJECT_MANAGER);
        $this->assertFalse(
            $stranger->can('view', $project),
            'Quien está fuera de la cadena no ve el proyecto.'
        );
    }

    #[Test]
    public function rule_2_being_a_manager_grants_reading_but_never_editing(): void
    {
        $manager = $this->userWithRole(Role::PROJECT_MANAGER);
        $subordinate = $this->userWithRole(Role::PROJECT_MANAGER);
        $this->makeManagerOf($manager, $subordinate);

        $project = $this->projectOwnedBy($subordinate);
        $project->members()->attach($subordinate, ['project_role' => Project::ROLE_MANAGER]);
        $project->load('members');

        $this->assertTrue($manager->can('view', $project), 'El jefe sí puede ver.');
        $this->assertFalse(
            $manager->can('update', $project),
            'Ser jefe no otorga edición: se edita solo donde se es miembro.'
        );

        $this->actingAs($manager)
            ->put(route('admin.users.update', $subordinate), [])
            ->assertForbidden();
    }

    #[Test]
    public function rule_3_costs_are_a_separate_permission_from_hierarchy(): void
    {
        $supervisor = $this->userWithRole(Role::PROJECT_MANAGER);
        $subordinate = $this->userWithRole(Role::TEAM_MEMBER);
        $this->makeManagerOf($supervisor, $subordinate);

        $project = $this->projectOwnedBy($subordinate);

        $this->assertTrue(
            $supervisor->can('view', $project),
            'Ve el avance de su gente.'
        );
        $this->assertFalse(
            $supervisor->can('viewCosts', $project),
            'project_manager no trae permisos de costo: verlos es un permiso aparte.'
        );

        // El mismo usuario, con un rol que sí trae costos, sí los ve.
        $withCosts = $this->userWithRole(Role::PORTFOLIO_MANAGER);
        $this->makeManagerOf($withCosts, $subordinate);
        $withCosts->load('roles.permissions');

        $this->assertTrue($withCosts->can('viewCosts', $project));
    }

    #[Test]
    public function rule_4_the_auditor_reads_everything_and_writes_nothing(): void
    {
        $auditor = $this->userWithRole(Role::AUDITOR);
        $stranger = $this->userWithRole(Role::PROJECT_MANAGER);

        $project = $this->projectOwnedBy($stranger);
        // Miembro con rol de escritura: aun así no debe poder escribir.
        $project->members()->attach($auditor, ['project_role' => Project::ROLE_MANAGER]);
        $project->load('members');

        $this->assertTrue($auditor->can('view', $project), 'El auditor ve cualquier proyecto.');
        $this->assertFalse($auditor->can('update', $project), 'El auditor no escribe en ninguna parte.');
        $this->assertFalse($auditor->can('delete', $project));
        $this->assertFalse($auditor->can('create', User::class));

        $this->actingAs($auditor)
            ->get(route('admin.users.create'))
            ->assertForbidden();
    }

    #[Test]
    public function a_user_without_permission_gets_403_on_the_admin_screens(): void
    {
        $member = $this->userWithRole(Role::TEAM_MEMBER);

        $this->actingAs($member)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.users.create'))->assertForbidden();
        $this->actingAs($member)->get(route('admin.audit.index'))->assertForbidden();
    }

    #[Test]
    public function an_accidental_cycle_in_the_hierarchy_does_not_hang_the_expansion(): void
    {
        $first = $this->userWithRole(Role::TEAM_MEMBER);
        $second = $this->userWithRole(Role::TEAM_MEMBER);

        $this->makeManagerOf($first, $second);
        $this->makeManagerOf($second, $first);

        $ids = app(VisibilityScope::class)->visibleUserIds($first);

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $ids);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'org_unit_id' => OrgUnit::query()->firstOrCreate(['name' => 'Operaciones'])->id,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->roles()->attach(Role::query()->where('name', $role)->value('id'));

        return $user->load('roles.permissions');
    }

    private function makeManagerOf(User $manager, User $subordinate): void
    {
        DB::table('user_hierarchy')->insert([
            'manager_id' => $manager->id,
            'subordinate_id' => $subordinate->id,
            'effective_from' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(VisibilityScope::class)->flush();
    }

    private function projectOwnedBy(User $owner): Project
    {
        $project = Project::query()->create([
            'code' => 'P-'.str()->random(6),
            'name' => 'Proyecto de prueba',
            'owner_id' => $owner->id,
            'status' => 'active',
        ]);

        return $project->load('members');
    }
}
