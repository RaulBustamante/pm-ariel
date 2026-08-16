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
}
