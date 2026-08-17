<?php

declare(strict_types=1);

namespace Tests\Feature\Preferences;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
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

    // --- El tema ----------------------------------------------------------

    /**
     * El atributo lo escribe el servidor. Si lo pusiera un script al cargar, la
     * página aparecería un instante con el tema equivocado, y ese parpadeo
     * blanco es justo lo que delata a un tema oscuro mal hecho.
     */
    #[Test]
    public function the_chosen_theme_arrives_written_in_the_html(): void
    {
        $this->actingAs($this->user(['theme' => User::THEME_DARK]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-theme="dark"', escape: false);
    }

    /**
     * «Sistema» no escribe atributo: sin él manda `prefers-color-scheme`, que
     * es lo que la persona ya configuró en su computadora una vez.
     */
    #[Test]
    public function the_system_theme_writes_no_attribute(): void
    {
        $this->actingAs($this->user(['theme' => User::THEME_SYSTEM]))
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-theme=', escape: false);
    }

    #[Test]
    public function the_theme_can_be_changed_and_it_sticks(): void
    {
        $user = $this->user(['theme' => User::THEME_SYSTEM]);

        $this->actingAs($user)->put(route('preferences.update'), [
            'locale' => 'es',
            'theme' => User::THEME_LIGHT,
            'timezone' => 'America/Mexico_City',
        ])->assertRedirect(route('preferences.edit'));

        $this->assertSame(User::THEME_LIGHT, $user->fresh()->theme);
    }

    #[Test]
    public function an_invented_theme_is_rejected(): void
    {
        $this->actingAs($this->user())->put(route('preferences.update'), [
            'locale' => 'es',
            'theme' => 'neon',
            'timezone' => 'America/Mexico_City',
        ])->assertSessionHasErrors('theme');
    }

    /**
     * Las vistas previas de un documento se ven claras aunque la persona haya
     * elegido oscuro: quien las abre está revisando cómo va a salir la hoja, y
     * una vista previa oscura de algo que sale blanco no sirve para revisar.
     */
    #[Test]
    public function the_document_preview_stays_light_under_the_dark_theme(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = $this->user(['theme' => User::THEME_DARK]);
        $user->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $project = Project::query()->create([
            'code' => 'THEME-1',
            'name' => 'Proyecto',
            'owner_id' => $user->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $project->members()->attach($user->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->actingAs($user)
            ->get(route('projects.initiation.package', $project))
            ->assertOk()
            ->assertSee('paper', escape: false);
    }
}
