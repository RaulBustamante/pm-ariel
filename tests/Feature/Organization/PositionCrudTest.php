<?php

declare(strict_types=1);

namespace Tests\Feature\Organization;

use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\PositionsSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los puestos.
 *
 * El modelo existía desde la Etapa 1 y el alta de usuarios ya ofrecía el campo,
 * pero no había pantalla para crear uno ni semilla que los cargara: el
 * desplegable llevaba cinco etapas vacío. No fallaba nada —un desplegable vacío
 * se ve igual que uno cuyas opciones no aplican— y por eso pasó inadvertido.
 */
final class PositionCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));
    }

    #[Test]
    public function a_position_can_be_created_and_shows_up_in_the_user_form(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.positions.store'), ['name' => 'Gerencia de Sistemas', 'level' => 3])
            ->assertRedirect(route('admin.positions.index'));

        $this->assertDatabaseHas('positions', ['name' => 'Gerencia de Sistemas', 'level' => 3]);

        // Lo que de verdad importaba: que el desplegable deje de estar vacío.
        $this->actingAs($this->admin)
            ->get(route('admin.users.create'))
            ->assertOk()
            ->assertSee('Gerencia de Sistemas');
    }

    #[Test]
    public function two_positions_cannot_share_a_name(): void
    {
        Position::query()->create(['name' => 'Analista', 'level' => 5]);

        $this->actingAs($this->admin)
            ->post(route('admin.positions.store'), ['name' => 'Analista', 'level' => 4])
            ->assertSessionHasErrors('name');
    }

    #[Test]
    public function a_position_can_be_renamed_without_colliding_with_itself(): void
    {
        $position = Position::query()->create(['name' => 'Analista', 'level' => 5]);

        $this->actingAs($this->admin)
            ->put(route('admin.positions.update', $position), ['name' => 'Analista', 'level' => 4])
            ->assertRedirect(route('admin.positions.index'));

        $this->assertSame(4, $position->refresh()->level);
    }

    /**
     * Borrar un puesto con gente asignada la dejaría sin puesto sin avisarle a
     * nadie. Se pide mover a esas personas primero, que es la decisión que
     * alguien tiene que tomar de todos modos.
     */
    #[Test]
    public function a_position_in_use_is_not_deleted(): void
    {
        $position = Position::query()->create(['name' => 'Supervisión', 'level' => 5]);
        $this->admin->forceFill(['position_id' => $position->id])->save();

        $this->actingAs($this->admin)
            ->delete(route('admin.positions.destroy', $position))
            ->assertRedirect();

        $this->assertDatabaseHas('positions', ['id' => $position->id]);
    }

    #[Test]
    public function an_unused_position_is_deleted(): void
    {
        $position = Position::query()->create(['name' => 'Becario', 'level' => 6]);

        $this->actingAs($this->admin)
            ->delete(route('admin.positions.destroy', $position))
            ->assertRedirect(route('admin.positions.index'));

        // Borrado suave, como todo en este sistema: desaparece de las pantallas
        // pero la bitácora de quién lo borró sigue teniendo a qué apuntar.
        $this->assertSoftDeleted('positions', ['id' => $position->id]);
        $this->assertSame(0, Position::query()->count());
    }

    #[Test]
    public function the_empty_state_explains_how_to_load_a_starter_catalogue(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.positions.index'))
            ->assertOk()
            ->assertSee(__('positions.empty_title'));
    }

    #[Test]
    public function the_starter_catalogue_can_be_seeded_twice_without_duplicating(): void
    {
        $this->seed(PositionsSeeder::class);
        $first = Position::query()->count();

        $this->seed(PositionsSeeder::class);

        $this->assertGreaterThan(0, $first);
        $this->assertSame($first, Position::query()->count());
    }

    #[Test]
    public function someone_without_the_permission_cannot_reach_the_screen(): void
    {
        $member = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $member->roles()->attach(Role::query()->where('name', Role::TEAM_MEMBER)->value('id'));

        $this->actingAs($member)->get(route('admin.positions.index'))->assertForbidden();
    }
}
