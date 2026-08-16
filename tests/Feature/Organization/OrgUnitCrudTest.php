<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Models\OrgUnit;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrgUnitCrudTest extends TestCase
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

    private function unit(string $name, ?OrgUnit $parent = null): OrgUnit
    {
        return OrgUnit::query()->create([
            'name' => $name,
            'parent_id' => $parent?->id,
        ]);
    }

    #[Test]
    public function an_administrator_can_create_a_top_level_area(): void
    {
        $this->actingAs($this->admin())
            ->post(route('admin.org-units.store'), ['name' => 'Dirección', 'sort_order' => 0])
            ->assertRedirect(route('admin.org-units.index'));

        $unit = OrgUnit::query()->where('name', 'Dirección')->firstOrFail();

        $this->assertSame(0, $unit->depth);
        $this->assertSame('/', $unit->path);
    }

    #[Test]
    public function a_child_area_gets_its_depth_and_path_from_its_parent(): void
    {
        $root = $this->unit('Dirección');

        $this->actingAs($this->admin())->post(route('admin.org-units.store'), [
            'name' => 'Sistemas',
            'parent_id' => $root->id,
        ]);

        $child = OrgUnit::query()->where('name', 'Sistemas')->firstOrFail();

        $this->assertSame(1, $child->depth);
        $this->assertSame("/{$root->id}/", $child->path);
    }

    /**
     * El caso que no estaba cubierto: mover un área movía solo a ella. Las hijas
     * conservaban la ruta anterior y un subárbol consultado por `path` empezaba a
     * devolver mentiras sin que nada fallara.
     */
    #[Test]
    public function moving_an_area_rewrites_the_path_of_everything_below_it(): void
    {
        $first = $this->unit('Dirección');
        $second = $this->unit('Operaciones');
        $middle = $this->unit('Sistemas', $first);
        $leaf = $this->unit('Soporte', $middle);

        $this->actingAs($this->admin())->put(route('admin.org-units.update', $middle), [
            'name' => 'Sistemas',
            'parent_id' => $second->id,
        ]);

        $this->assertSame("/{$second->id}/", $middle->refresh()->path);
        $this->assertSame("/{$second->id}/{$middle->id}/", $leaf->refresh()->path);
        $this->assertSame(2, $leaf->depth);
    }

    #[Test]
    public function an_area_cannot_hang_from_one_of_its_own_descendants(): void
    {
        $root = $this->unit('Dirección');
        $child = $this->unit('Sistemas', $root);

        $this->actingAs($this->admin())
            ->put(route('admin.org-units.update', $root), [
                'name' => 'Dirección',
                'parent_id' => $child->id,
            ])
            ->assertSessionHasErrors('parent_id');

        $this->assertNull($root->refresh()->parent_id);
    }

    #[Test]
    public function an_area_cannot_be_its_own_parent(): void
    {
        $unit = $this->unit('Dirección');

        $this->actingAs($this->admin())
            ->put(route('admin.org-units.update', $unit), [
                'name' => 'Dirección',
                'parent_id' => $unit->id,
            ])
            ->assertSessionHasErrors('parent_id');
    }

    #[Test]
    public function an_area_with_people_is_not_deleted(): void
    {
        $unit = $this->unit('Sistemas');
        User::factory()->create(['org_unit_id' => $unit->id]);

        $this->actingAs($this->admin())
            ->from(route('admin.org-units.index'))
            ->delete(route('admin.org-units.destroy', $unit))
            ->assertRedirect(route('admin.org-units.index'))
            ->assertSessionHas('error', __('org_units.has_users'));

        $this->assertNotSoftDeleted($unit);
    }

    #[Test]
    public function an_area_with_areas_below_it_is_not_deleted(): void
    {
        $root = $this->unit('Dirección');
        $this->unit('Sistemas', $root);

        $this->actingAs($this->admin())
            ->from(route('admin.org-units.index'))
            ->delete(route('admin.org-units.destroy', $root))
            ->assertSessionHas('error', __('org_units.has_children'));

        $this->assertNotSoftDeleted($root);
    }

    #[Test]
    public function an_empty_area_is_deleted(): void
    {
        $unit = $this->unit('Sistemas');

        $this->actingAs($this->admin())
            ->delete(route('admin.org-units.destroy', $unit))
            ->assertRedirect(route('admin.org-units.index'));

        $this->assertSoftDeleted($unit);
    }

    /**
     * Las pantallas de alta y edición se dibujan. Sin esto, un error de plantilla
     * solo aparecería usándola a mano.
     */
    #[Test]
    public function the_area_forms_render(): void
    {
        $root = $this->unit('Dirección');
        $child = $this->unit('Sistemas', $root);

        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.org-units.create'))->assertOk();

        $this->actingAs($admin)
            ->get(route('admin.org-units.edit', $root))
            ->assertOk()
            // Un área no se ofrece como padre de sí misma ni de su ascendencia.
            ->assertDontSee('value="'.$child->id.'"', escape: false);
    }

    #[Test]
    public function a_viewer_gets_403_on_the_area_screens(): void
    {
        $viewer = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $viewer->roles()->attach(Role::query()->where('name', Role::VIEWER)->value('id'));

        $this->actingAs($viewer)->get(route('admin.org-units.index'))->assertForbidden();
        $this->actingAs($viewer)->post(route('admin.org-units.store'), ['name' => 'X'])->assertForbidden();
    }

    /**
     * El auditor lee todo y no escribe nada. Si un permiso añadido después le
     * diera escritura por descuido, esta prueba lo detiene.
     */
    #[Test]
    public function an_auditor_can_look_but_not_touch(): void
    {
        $auditor = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $auditor->roles()->attach(Role::query()->where('name', Role::AUDITOR)->value('id'));

        $this->actingAs($auditor)->get(route('admin.org-units.index'))->assertOk();
        $this->actingAs($auditor)->post(route('admin.org-units.store'), ['name' => 'X'])->assertForbidden();
    }
}
