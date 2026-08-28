<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserPasswordTest extends TestCase
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

    private function member(): User
    {
        return User::factory()->create(['is_active' => true, 'must_change_password' => false]);
    }

    #[Test]
    public function an_administrator_can_set_a_specific_password(): void
    {
        $target = $this->member();

        $this->actingAs($this->admin())
            ->put(route('admin.users.password.update', $target), [
                'password' => 'ariel12345',
                'password_confirmation' => 'ariel12345',
                'must_change_password' => '1',
            ])
            ->assertRedirect(route('admin.users.index'));

        $target->refresh();

        $this->assertTrue(Hash::check('ariel12345', $target->password));
        $this->assertTrue($target->must_change_password);
    }

    #[Test]
    public function the_administrator_can_hand_over_a_password_without_forcing_a_change(): void
    {
        $target = $this->member();

        $this->actingAs($this->admin())
            ->put(route('admin.users.password.update', $target), [
                'password' => 'ariel12345',
                'password_confirmation' => 'ariel12345',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertFalse($target->refresh()->must_change_password);
    }

    #[Test]
    public function a_weak_password_is_rejected(): void
    {
        $target = $this->member();
        $before = $target->password;

        $this->actingAs($this->admin())
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.password.update', $target), [
                'password' => 'ariel',
                'password_confirmation' => 'ariel',
            ])
            ->assertSessionHasErrors('password');

        $this->assertSame($before, $target->refresh()->password);
    }

    #[Test]
    public function a_mistyped_confirmation_is_rejected(): void
    {
        $target = $this->member();

        $this->actingAs($this->admin())
            ->from(route('admin.users.edit', $target))
            ->put(route('admin.users.password.update', $target), [
                'password' => 'ariel12345',
                'password_confirmation' => 'ariel54321',
            ])
            ->assertSessionHasErrors('password');

        $this->assertFalse(Hash::check('ariel12345', $target->refresh()->password));
    }

    #[Test]
    public function an_administrator_can_generate_a_temporary_password(): void
    {
        $target = $this->member();
        $before = $target->password;

        $this->actingAs($this->admin())
            ->post(route('admin.users.password.reset', $target))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('status');

        $target->refresh();

        $this->assertNotSame($before, $target->password);
        $this->assertTrue($target->must_change_password);
    }

    /**
     * La bitácora excluye `password`, así que sin este registro explícito un
     * cambio de contraseña no dejaría rastro de quién lo hizo.
     */
    #[Test]
    public function the_change_is_recorded_without_the_password(): void
    {
        $admin = $this->admin();
        $target = $this->member();

        $this->actingAs($admin)->put(route('admin.users.password.update', $target), [
            'password' => 'ariel12345',
            'password_confirmation' => 'ariel12345',
        ]);

        $entry = AuditLog::query()
            ->where('auditable_type', User::class)
            ->where('auditable_id', $target->id)
            ->where('event', 'password_set')
            ->sole();

        $this->assertSame($admin->id, $entry->user_id);
        $this->assertStringNotContainsString('ariel12345', json_encode($entry->new_values) ?: '');
    }

    #[Test]
    public function someone_without_user_management_cannot_change_another_password(): void
    {
        $target = $this->member();
        $before = $target->password;

        $this->actingAs($this->member())
            ->put(route('admin.users.password.update', $target), [
                'password' => 'ariel12345',
                'password_confirmation' => 'ariel12345',
            ])
            ->assertForbidden();

        $this->assertSame($before, $target->refresh()->password);
    }

    /**
     * Para la propia está "cambiar mi contraseña", que sí pide la actual.
     */
    #[Test]
    public function an_administrator_cannot_reset_their_own_password_from_here(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.users.password.reset', $admin))
            ->assertForbidden();
    }
}
