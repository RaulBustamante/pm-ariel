<?php

declare(strict_types=1);

namespace Tests\Feature\Preferences;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PreferencesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(array $attributes = []): User
    {
        return User::factory()->create([
            'locale' => 'es',
            'timezone' => 'America/Mexico_City',
            'expert_mode' => false,
            'is_active' => true,
            'must_change_password' => false,
            ...$attributes,
        ]);
    }

    #[Test]
    public function the_preferences_screen_shows_the_current_mode(): void
    {
        $user = $this->user(['expert_mode' => true]);

        $this->actingAs($user)
            ->get(route('preferences.edit'))
            ->assertOk()
            ->assertSee(__('preferences.current_expert'));
    }

    #[Test]
    public function turning_expert_mode_on_persists_it(): void
    {
        $user = $this->user(['expert_mode' => false]);

        $this->actingAs($user)
            ->put(route('preferences.update'), [
                'locale' => 'es',
                'timezone' => 'America/Mexico_City',
                'expert_mode' => '1',
            ])
            ->assertRedirect(route('preferences.edit'));

        $this->assertTrue($user->refresh()->expert_mode);
    }

    /**
     * Una casilla sin marcar no viaja en el formulario. Si el controlador se
     * limitara a lo que llega, el Modo Experto solo se podría encender.
     */
    #[Test]
    public function turning_expert_mode_off_persists_it_even_though_the_checkbox_sends_nothing(): void
    {
        $user = $this->user(['expert_mode' => true]);

        $this->actingAs($user)
            ->put(route('preferences.update'), [
                'locale' => 'es',
                'timezone' => 'America/Mexico_City',
            ])
            ->assertRedirect(route('preferences.edit'));

        $this->assertFalse($user->refresh()->expert_mode);
    }

    #[Test]
    public function changing_the_language_changes_what_the_next_screen_says(): void
    {
        $user = $this->user(['locale' => 'es']);

        $this->actingAs($user)->put(route('preferences.update'), [
            'locale' => 'en',
            'timezone' => 'America/Mexico_City',
            'expert_mode' => '0',
        ]);

        $this->assertSame('en', $user->refresh()->locale);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Preferences');
    }

    #[Test]
    public function an_unsupported_language_is_rejected(): void
    {
        $user = $this->user(['locale' => 'es']);

        $this->actingAs($user)
            ->put(route('preferences.update'), [
                'locale' => 'fr',
                'timezone' => 'America/Mexico_City',
            ])
            ->assertSessionHasErrors('locale');

        $this->assertSame('es', $user->refresh()->locale);
    }

    #[Test]
    public function an_invalid_timezone_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)
            ->put(route('preferences.update'), [
                'locale' => 'es',
                'timezone' => 'Mars/Olympus_Mons',
            ])
            ->assertSessionHasErrors('timezone');
    }

    #[Test]
    public function a_guest_cannot_reach_the_preferences_screen(): void
    {
        $this->get(route('preferences.edit'))->assertRedirect(route('login'));
    }

    /**
     * Las etapas siguientes van a esconder columnas con estas directivas. Si
     * dejan de responder a la preferencia, lo avanzado se muestra a todos.
     */
    #[Test]
    public function the_blade_directives_follow_the_preference(): void
    {
        $template = "@expert\nA\n@endexpert\n@simple\nB\n@endsimple";

        $this->actingAs($this->user(['expert_mode' => true]));
        $this->assertSame('A', trim(Blade::render($template)));

        $this->actingAs($this->user(['expert_mode' => false]));
        $this->assertSame('B', trim(Blade::render($template)));
    }
}
