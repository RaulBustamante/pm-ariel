<?php

declare(strict_types=1);

namespace Tests\Feature\Initiation;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\User;
use App\Support\Initiation\InitiationStarter;
use App\Support\Initiation\InitiationStep;
use Database\Seeders\ProjectTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class InitiationWalkthroughTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProjectTemplatesSeeder::class);

        // Sin llave configurada el proveedor es el de plantillas, que es el modo
        // de referencia (D-016). Las pruebas no deben depender de una red.
        config()->set('initiation.ai.enabled', false);
    }

    private function manager(): User
    {
        $user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $user->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        return $user;
    }

    private function projectFor(User $owner, ?string $templateKey = null): Project
    {
        $template = $templateKey === null
            ? null
            : ProjectTemplate::query()->where('key', $templateKey)->firstOrFail();

        return app(InitiationStarter::class)->start(
            ['code' => 'P-'.fake()->unique()->numberBetween(100, 999), 'name' => 'Proyecto de prueba'],
            $owner,
            $template,
        );
    }

    #[Test]
    public function creating_a_project_starts_the_walkthrough_at_the_first_step(): void
    {
        $manager = $this->manager();

        $this->actingAs($manager)
            ->post(route('projects.store'), [
                'code' => 'INV-01',
                'planned_start' => '2026-04-06',
                'name' => 'Inventario de refacciones',
                'description' => 'Control de refacciones críticas',
            ])
            ->assertRedirect();

        $project = Project::query()->where('code', 'INV-01')->firstOrFail();

        $this->assertNotNull($project->charter);
        $this->assertSame(InitiationStep::Justification->value, $project->charter->current_step);

        // Quien arranca el proyecto queda como su gerente. Sin esto no podría
        // editar lo que acaba de crear: la regla 2 exige membresía.
        $this->assertSame(Project::ROLE_MANAGER, $manager->fresh()->projectRoleFor($project));
    }

    #[Test]
    public function choosing_a_template_preloads_its_risks_and_stakeholders(): void
    {
        $project = $this->projectFor($this->manager(), 'systems');

        $this->assertGreaterThan(0, $project->risks()->count());
        $this->assertGreaterThan(0, $project->stakeholders()->count());

        // Lo precargado se marca como venido del catálogo: sirve para saber
        // después si el catálogo está sirviendo de algo.
        $this->assertSame('catalog', $project->risks()->first()?->source);
    }

    #[Test]
    public function starting_without_a_template_preloads_nothing(): void
    {
        $project = $this->projectFor($this->manager());

        $this->assertSame(0, $project->risks()->count());
        $this->assertSame(0, $project->stakeholders()->count());
    }

    /**
     * La razón de ser del bloque: nadie termina esto de una sentada. Si al
     * volver no estuviera lo capturado, el documento nunca se terminaría.
     */
    #[Test]
    public function the_walkthrough_can_be_abandoned_and_resumed_without_losing_anything(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $this->actingAs($manager)
            ->put(route(InitiationStep::Justification->route().'.update', $project), [
                'problem_statement' => 'El cierre mensual toma dos días.',
                'expected_benefit' => 'Dos días liberados al mes.',
                'action' => 'exit',
            ])
            ->assertRedirect(route('projects.initiation.overview', $project));

        $charter = $project->charter->refresh();

        $this->assertSame('El cierre mensual toma dos días.', $charter->problem_statement);
        $this->assertTrue($charter->hasCompleted(InitiationStep::Justification->value));
        $this->assertSame(InitiationStep::Stakeholders->value, $charter->current_step);

        $this->actingAs($manager)
            ->get(route(InitiationStep::Justification->route(), $project))
            ->assertOk()
            ->assertSee('El cierre mensual toma dos días.');
    }

    #[Test]
    public function saving_a_step_advances_to_the_next_one(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $this->actingAs($manager)
            ->put(route(InitiationStep::Justification->route().'.update', $project), [
                'problem_statement' => 'Algo duele.',
            ])
            ->assertRedirect(route(InitiationStep::Stakeholders->route(), $project));
    }

    #[Test]
    public function the_last_step_lands_on_the_overview(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $this->actingAs($manager)
            ->put(route(InitiationStep::Risks->route().'.update', $project), [])
            ->assertRedirect(route('projects.initiation.overview', $project));
    }

    #[Test]
    public function every_step_screen_renders(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager, 'launch');

        foreach (InitiationStep::ordered() as $step) {
            $this->actingAs($manager)
                ->get(route($step->route(), $project))
                ->assertOk()
                ->assertSee($step->title());
        }

        $this->actingAs($manager)->get(route('projects.initiation.overview', $project))->assertOk();
        $this->actingAs($manager)->get(route('projects.initiation.package', $project))->assertOk();
    }

    #[Test]
    public function an_unknown_step_is_not_a_route(): void
    {
        $project = $this->projectFor($this->manager());

        $this->actingAs($this->manager())
            ->get(url("/projects/{$project->id}/initiation/whatever"))
            ->assertNotFound();
    }

    /**
     * Nada aquí es obligatorio para poder guardar. Obligar a llenar todo de golpe
     * es la forma más segura de que la gente invente texto para poder avanzar.
     */
    #[Test]
    public function a_step_can_be_saved_empty(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $this->actingAs($manager)
            ->put(route(InitiationStep::Charter->route().'.update', $project), [])
            ->assertSessionHasNoErrors();
    }

    #[Test]
    public function someone_without_write_access_cannot_change_the_charter(): void
    {
        $owner = $this->manager();
        $project = $this->projectFor($owner);

        // Otro gerente de proyecto, pero no miembro de este proyecto: la regla 2
        // dice que ver no es editar.
        $outsider = $this->manager();

        $this->actingAs($outsider)
            ->put(route(InitiationStep::Justification->route().'.update', $project), [
                'problem_statement' => 'Intento de edición ajena',
            ])
            ->assertForbidden();
    }

    #[Test]
    public function an_auditor_can_read_the_package_but_not_write(): void
    {
        $project = $this->projectFor($this->manager());

        $auditor = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $auditor->roles()->attach(Role::query()->where('name', Role::AUDITOR)->value('id'));

        $this->actingAs($auditor)->get(route('projects.initiation.package', $project))->assertOk();

        $this->actingAs($auditor)
            ->put(route(InitiationStep::Charter->route().'.update', $project), ['objectives' => 'x'])
            ->assertForbidden();
    }

    #[Test]
    public function a_stakeholder_gets_the_strategy_of_its_quadrant_when_none_is_written(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $this->actingAs($manager)->post(route('projects.stakeholders.store', $project), [
            'name' => 'Director de Operaciones',
            'power' => 5,
            'interest' => 5,
        ])->assertRedirect();

        $stakeholder = $project->stakeholders()->firstOrFail();

        $this->assertSame(Stakeholder::QUADRANT_MANAGE_CLOSELY, $stakeholder->quadrant());
        $this->assertSame(__('initiation.strategy_manage_closely'), $stakeholder->engagement_strategy);
    }

    #[Test]
    public function the_power_and_interest_scale_is_bounded(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $this->actingAs($manager)
            ->post(route('projects.stakeholders.store', $project), [
                'name' => 'Alguien',
                'power' => 9,
                'interest' => 0,
            ])
            ->assertSessionHasErrors(['power', 'interest']);
    }

    #[Test]
    public function risks_get_a_running_code_per_project(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        foreach (['Se cae el servidor', 'El proveedor no libera en aduana'] as $description) {
            $this->actingAs($manager)->post(route('projects.risks.store', $project), [
                'description' => $description,
                'probability' => 3,
                'impact' => 4,
                'kind' => Risk::KIND_THREAT,
            ]);
        }

        $this->assertSame(['R-01', 'R-02'], $project->risks()->orderBy('code')->pluck('code')->all());
    }

    /**
     * Reusar un código haría que dos riesgos distintos aparecieran con el mismo
     * nombre en dos minutas de fechas distintas.
     */
    #[Test]
    public function a_deleted_risk_does_not_release_its_code(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $risk = Risk::query()->create([
            'project_id' => $project->id,
            'code' => Risk::nextCodeFor($project),
            'description' => 'Primero',
            'probability' => 2, 'impact' => 2,
        ]);

        $risk->delete();

        $this->assertSame('R-02', Risk::nextCodeFor($project->refresh()));
    }

    #[Test]
    public function recording_a_response_moves_the_risk_out_of_identified(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager);

        $risk = Risk::query()->create([
            'project_id' => $project->id,
            'code' => 'R-01',
            'description' => 'Algo malo',
            'probability' => 4, 'impact' => 5,
        ]);

        $this->actingAs($manager)->post(route('projects.risks.responses.store', [$project, $risk]), [
            'strategy' => 'mitigate',
            'description' => 'Contratar un segundo proveedor.',
        ])->assertRedirect();

        $this->assertSame(Risk::STATUS_RESPONDING, $risk->refresh()->status);
    }

    /**
     * La ruta trae proyecto y riesgo por separado. Sin la comprobación de
     * pertenencia, cambiar el número en la barra de direcciones editaría el
     * riesgo de otro proyecto — uno al que sí se tiene acceso, así que la
     * Policy no lo detendría.
     */
    #[Test]
    public function a_risk_cannot_be_touched_through_another_project(): void
    {
        $manager = $this->manager();
        $mine = $this->projectFor($manager);
        $other = $this->projectFor($manager);

        $risk = Risk::query()->create([
            'project_id' => $other->id,
            'code' => 'R-01',
            'description' => 'De otro proyecto',
            'probability' => 2, 'impact' => 2,
        ]);

        $this->actingAs($manager)
            ->delete(route('projects.risks.destroy', [$mine, $risk]))
            ->assertNotFound();

        $this->assertNotSoftDeleted($risk);
    }

    #[Test]
    public function suggesting_risks_twice_does_not_duplicate_them(): void
    {
        $manager = $this->manager();
        $project = $this->projectFor($manager, 'works');

        $before = $project->risks()->count();

        $this->actingAs($manager)->post(route('projects.risks.suggest', $project))->assertRedirect();

        $this->assertSame($before, $project->risks()->count());
    }
}
