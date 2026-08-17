<?php

declare(strict_types=1);

namespace Tests\Feature\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\User;
use App\Services\Initiation\OpenAiSuggestionProvider;
use App\Services\Initiation\TemplateSuggestionProvider;
use App\Support\Initiation\InitiationStarter;
use Database\Seeders\ProjectTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La IA es opcional (D-016 / D-018). Lo que estas pruebas cuidan es que el
 * recorrido no dependa de ella: si no hay llave, si el proveedor falla o si
 * responde basura, la plantilla contesta y el usuario no se queda a medias.
 */
final class SuggestionProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProjectTemplatesSeeder::class);
    }

    private function project(string $templateKey = 'systems'): Project
    {
        $owner = User::factory()->create();
        $owner->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        return app(InitiationStarter::class)->start(
            ['code' => 'S-'.fake()->unique()->numberBetween(100, 999), 'name' => 'Proyecto'],
            $owner,
            ProjectTemplate::query()->where('key', $templateKey)->firstOrFail(),
        );
    }

    #[Test]
    public function without_a_key_the_container_resolves_the_template_provider(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', null);

        $this->assertInstanceOf(TemplateSuggestionProvider::class, app(SuggestsContent::class));
    }

    #[Test]
    public function with_the_switch_off_the_key_alone_does_not_turn_it_on(): void
    {
        config()->set('initiation.ai.enabled', false);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');

        $this->assertInstanceOf(TemplateSuggestionProvider::class, app(SuggestsContent::class));
    }

    #[Test]
    public function with_key_and_switch_the_container_resolves_the_model_provider(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');

        $this->assertInstanceOf(OpenAiSuggestionProvider::class, app(SuggestsContent::class));
    }

    #[Test]
    public function the_strategy_comes_from_the_quadrant_and_never_from_the_model(): void
    {
        $provider = app(TemplateSuggestionProvider::class);

        $stakeholder = new Stakeholder(['name' => 'X', 'power' => 5, 'interest' => 5]);
        $this->assertSame(__('initiation.strategy_manage_closely'), $provider->suggestEngagementStrategy($stakeholder));

        $stakeholder = new Stakeholder(['name' => 'X', 'power' => 1, 'interest' => 1]);
        $this->assertSame(__('initiation.strategy_monitor'), $provider->suggestEngagementStrategy($stakeholder));
    }

    #[Test]
    public function when_the_provider_fails_the_template_answers(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');

        Http::fake(['*' => Http::response('nope', 500)]);

        $project = $this->project();
        $provider = app(SuggestsContent::class);

        $this->assertNotEmpty($provider->suggestRisks($project));
    }

    #[Test]
    public function when_the_provider_returns_unreadable_content_the_template_answers(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => 'esto no es JSON']]],
        ])]);

        $project = $this->project();

        $this->assertNotEmpty(app(SuggestsContent::class)->suggestRisks($project));
    }

    #[Test]
    public function a_valid_answer_from_the_provider_is_used(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => json_encode([
                'risks' => [[
                    'description' => 'El sistema de aduana cambia de formato',
                    'probability' => 3, 'impact' => 5,
                ]],
            ])]]],
        ])]);

        $suggested = app(SuggestsContent::class)->suggestRisks($this->project());

        $this->assertSame('El sistema de aduana cambia de formato', $suggested[0]['description']);
    }

    /**
     * La lista blanca de `config/initiation.php` es la única puerta por la que
     * sale información de Ariel. Si dejara pasar algo más, saldría sin que nadie
     * lo notara.
     */
    #[Test]
    public function only_whitelisted_fields_leave_the_network(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');

        Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => '{"risks":[]}']]]])]);

        $project = $this->project();

        // Un dato de contacto que existe en la base y no debe salir jamás.
        $project->stakeholders()->create([
            'name' => 'Director',
            'email' => 'secreto@ariel.example',
            'phone' => '555-0000',
            'power' => 5, 'interest' => 5,
        ]);

        $project->charter->update(['problem_statement' => 'Duele el cierre mensual.']);

        app(SuggestsContent::class)->suggestRisks($project->refresh());

        $carries = fn (string $needle): callable => fn (Request $request): bool => str_contains(
            (string) json_encode($request->data()),
            $needle,
        );

        // Ninguna petición, de las varias que se hacen, lleva datos de contacto.
        Http::assertNotSent($carries('secreto@ariel.example'));
        Http::assertNotSent($carries('555-0000'));

        // Y el campo que sí está en la lista blanca sí viaja: si no, la prueba
        // pasaría igual con un contexto vacío, que no demuestra nada.
        Http::assertSent($carries('Duele el cierre mensual.'));
    }

    /**
     * Cada sugerencia es una llamada facturada. Sin tope, quien oprime el botón
     * veinte veces porque no le gustó el borrador gasta veinte veces, y nadie se
     * entera hasta el estado de cuenta.
     */
    #[Test]
    public function the_spend_cap_stops_calling_the_provider(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');
        config()->set('initiation.ai.rate_limit.per_minute', 3);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"risks":[{"description":"Del modelo","probability":3,"impact":3}]}']]],
        ])]);

        $project = $this->project();

        // Se cuenta desde aquí: crear el proyecto ya gastó sus propias llamadas
        // precargando el recorrido, y mezclarlas escondería lo que se mide.
        $before = count(Http::recorded());

        $this->actingAs($project->owner);
        $provider = app(SuggestsContent::class);

        for ($i = 0; $i < 6; $i++) {
            $provider->suggestRisks($project);
        }

        // Seis intentos, tres llamadas: el tope se aplica dentro del proveedor,
        // así que ninguna ruta nueva puede saltárselo por olvido.
        $this->assertSame(3, count(Http::recorded()) - $before);
    }

    /**
     * Toparse con el límite no puede dejar el formulario vacío. Igual que
     * cuando no hay internet, contesta la plantilla y el recorrido sigue.
     */
    #[Test]
    public function past_the_cap_the_template_answers_instead_of_failing(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');
        config()->set('initiation.ai.rate_limit.per_minute', 1);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"risks":[{"description":"Del modelo","probability":3,"impact":3}]}']]],
        ])]);

        $project = $this->project();
        $this->actingAs($project->owner);

        $provider = app(SuggestsContent::class);
        $provider->suggestRisks($project);

        $afterCap = $provider->suggestRisks($project);

        $this->assertNotEmpty($afterCap);
        $this->assertNotSame('Del modelo', $afterCap[0]['description']);
    }

    /**
     * El tope es por persona. Que alguien agote su cuota no puede dejar sin
     * asistente a todo el equipo.
     */
    #[Test]
    public function the_cap_belongs_to_each_person_and_not_to_everyone(): void
    {
        config()->set('initiation.ai.enabled', true);
        config()->set('initiation.ai.key', 'sk-lo-que-sea');
        config()->set('initiation.ai.rate_limit.per_minute', 1);

        Http::fake(['*' => Http::response([
            'choices' => [['message' => ['content' => '{"risks":[{"description":"Del modelo","probability":3,"impact":3}]}']]],
        ])]);

        $project = $this->project();
        $provider = app(SuggestsContent::class);

        $before = count(Http::recorded());

        $this->actingAs($project->owner);
        $provider->suggestRisks($project);
        $provider->suggestRisks($project);

        $other = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->actingAs($other);
        $provider->suggestRisks($project);

        // Dos llamadas: la primera de cada quien. La segunda del titular se topó.
        $this->assertSame(2, count(Http::recorded()) - $before);
    }
}
