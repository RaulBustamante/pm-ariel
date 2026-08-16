<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function a_user_can_sign_in_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-horse-9',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
    }

    #[Test]
    public function a_deactivated_account_cannot_sign_in_even_with_the_right_password(): void
    {
        $user = User::factory()->create([
            'password' => 'correct-horse-9',
            'is_active' => false,
        ]);

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    #[Test]
    public function login_is_rate_limited_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'correct-horse-9']);

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);
        }

        $response = $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'correct-horse-9',
        ]);

        // Tras agotar los intentos, ni la contraseña correcta debe pasar.
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    #[Test]
    public function a_temporary_password_forces_a_change_before_anything_else(): void
    {
        $user = User::factory()->create([
            'must_change_password' => true,
            'is_active' => true,
        ]);

        $user->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
        $this->actingAs($user)->get(route('admin.users.index'))->assertRedirect(route('password.change'));

        // La pantalla de cambio sí es alcanzable: es la salida de ese estado.
        $this->actingAs($user)->get(route('password.change'))->assertOk();
    }

    #[Test]
    public function changing_the_temporary_password_clears_the_flag(): void
    {
        $user = User::factory()->create([
            'password' => 'temporary-123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'temporary-123',
            'password' => 'a-longer-secret-1',
            'password_confirmation' => 'a-longer-secret-1',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($user->fresh()->must_change_password);
    }

    #[Test]
    public function the_new_password_must_differ_from_the_temporary_one(): void
    {
        $user = User::factory()->create([
            'password' => 'temporary-123',
            'must_change_password' => true,
        ]);

        $this->actingAs($user)->put(route('password.change.update'), [
            'current_password' => 'temporary-123',
            'password' => 'temporary-123',
            'password_confirmation' => 'temporary-123',
        ])->assertSessionHasErrors('password');

        $this->assertTrue($user->fresh()->must_change_password);
    }

    #[Test]
    public function the_password_reset_link_is_actually_sent(): void
    {
        Notification::fake();

        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class);
    }

    #[Test]
    public function an_unknown_email_does_not_reveal_whether_the_account_exists(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertNothingSent();
    }

    #[Test]
    public function a_reset_token_lets_the_user_set_a_new_password(): void
    {
        $user = User::factory()->create(['must_change_password' => true]);
        $token = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'brand-new-secret-1',
            'password_confirmation' => 'brand-new-secret-1',
        ])->assertRedirect(route('login'));

        // La eligió la persona, no un administrador: deja de ser temporal.
        $this->assertFalse($user->fresh()->must_change_password);
    }
}
