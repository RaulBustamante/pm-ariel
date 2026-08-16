<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Models\Role;
use App\Models\User;
use App\Support\Hierarchy\HierarchyManager;
use App\Support\Visibility\VisibilityScope;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class HierarchyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        return $admin;
    }

    private function manager(): HierarchyManager
    {
        return app(HierarchyManager::class);
    }

    #[Test]
    public function assigning_a_manager_records_the_relation(): void
    {
        $boss = User::factory()->create();
        $person = User::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.hierarchy.update', $person), ['manager_id' => $boss->id])
            ->assertRedirect(route('admin.hierarchy.index'));

        $this->assertTrue($this->manager()->managerOf($person)?->is($boss));
    }

    /**
     * El histórico es lo que permite explicar meses después por qué alguien veía
     * cierto proyecto. Un cambio cierra la relación anterior; no la borra.
     */
    #[Test]
    public function changing_a_manager_closes_the_previous_relation_instead_of_deleting_it(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $person = User::factory()->create();

        $hierarchy = $this->manager();
        $hierarchy->assign($person, $first);
        $hierarchy->assign($person, $second);

        $rows = DB::table('user_hierarchy')->where('subordinate_id', $person->id)->get();

        $this->assertCount(2, $rows);
        $this->assertNotNull($rows->firstWhere('manager_id', $first->id)?->effective_to);
        $this->assertNull($rows->firstWhere('manager_id', $second->id)?->effective_to);
    }

    #[Test]
    public function removing_a_manager_leaves_the_person_reporting_to_nobody(): void
    {
        $boss = User::factory()->create();
        $person = User::factory()->create();

        $hierarchy = $this->manager();
        $hierarchy->assign($person, $boss);
        $hierarchy->assign($person, null);

        $this->assertNull($hierarchy->managerOf($person));
    }

    #[Test]
    public function nobody_can_be_their_own_manager(): void
    {
        $person = User::factory()->create();

        $this->actingAs($this->admin())
            ->put(route('admin.hierarchy.update', $person), ['manager_id' => $person->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertNull($this->manager()->managerOf($person));
    }

    /**
     * Un ciclo no rompe nada de inmediato — VisibilityScope lo tolera —, pero
     * convierte el organigrama en algo que ya no se puede leer ni auditar.
     */
    #[Test]
    public function a_cycle_is_refused(): void
    {
        $top = User::factory()->create();
        $middle = User::factory()->create();
        $bottom = User::factory()->create();

        $hierarchy = $this->manager();
        $hierarchy->assign($middle, $top);
        $hierarchy->assign($bottom, $middle);

        // Colgar al de arriba del de abajo cerraría el círculo.
        $this->actingAs($this->admin())
            ->put(route('admin.hierarchy.update', $top), ['manager_id' => $bottom->id])
            ->assertSessionHasErrors('manager_id');

        $this->assertNull($hierarchy->managerOf($top));
    }

    /**
     * Un cambio de jefe altera lo que ve toda la rama. Si el caché sobreviviera
     * al cambio, alguien seguiría viendo proyectos que ya no le tocan, durante
     * cinco minutos y sin ninguna señal.
     */
    #[Test]
    public function assigning_a_manager_invalidates_the_visibility_cache(): void
    {
        $boss = User::factory()->create();
        $person = User::factory()->create();

        $visibility = app(VisibilityScope::class);

        $this->assertSame([$boss->id], $visibility->visibleUserIds($boss));

        $this->manager()->assign($person, $boss);

        $this->assertEqualsCanonicalizing(
            [$boss->id, $person->id],
            $visibility->visibleUserIds($boss),
        );
    }

    #[Test]
    public function reassigning_the_same_manager_on_the_same_day_does_not_blow_up(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();
        $person = User::factory()->create();

        $hierarchy = $this->manager();

        $hierarchy->assign($person, $first);
        $hierarchy->assign($person, $second);
        // "Lo moví mal, regrésalo": la relación es única por jefe, subordinado y
        // fecha de inicio, así que reasignar hoy mismo choca si se inserta a ciegas.
        $this->assertTrue($hierarchy->assign($person, $first));

        $this->assertTrue($hierarchy->managerOf($person)?->is($first));
    }

    #[Test]
    public function the_screen_lists_who_has_no_manager(): void
    {
        $withManager = User::factory()->create(['name' => 'Con Jefe']);
        User::factory()->create(['name' => 'Sin Jefe']);

        $this->manager()->assign($withManager, $this->admin());

        $this->actingAs($this->admin())
            ->get(route('admin.hierarchy.index'))
            ->assertOk()
            ->assertSee(__('hierarchy.roots'))
            ->assertSeeInOrder([__('hierarchy.roots_help'), 'Sin Jefe']);
    }

    #[Test]
    public function a_viewer_gets_403_on_the_hierarchy_screen(): void
    {
        $viewer = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $viewer->roles()->attach(Role::query()->where('name', Role::VIEWER)->value('id'));

        $this->actingAs($viewer)->get(route('admin.hierarchy.index'))->assertForbidden();
    }
}
