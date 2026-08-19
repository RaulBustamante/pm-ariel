<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Resource;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\BaselineManager;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Documents\DerivedDocument;
use App\Support\Documents\DocumentCatalogue;
use App\Support\Documents\ProjectArchive;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La quinta maquinaria: los documentos que se generan solos.
 *
 * **Por qué faltaba.** D-022 clasificó los setenta documentos en cuatro especies
 * y se construyó motor para tres. La cuarta se fue resolviendo uno por uno,
 * enrutando cada documento a la pantalla que ya tenía sus datos — y por eso los
 * que no tenían pantalla se quedaron sin construir. Los últimos doce del
 * catálogo eran todos de esta especie.
 *
 * Lo que estas pruebas cuidan es que **ninguno invente nada**: un derivado sale
 * de datos que ya existen, y cuando no existen sale vacío diciendo qué capturar
 * — no ofreciendo capturarlo por segunda vez en otro lado.
 */
final class DerivedDocumentTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'DER-1',
            'name' => 'Proyecto derivado',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $this->project->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        $this->project->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(string $name, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => 2 * self::DAY,
            'cost' => 1000,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return $task->refresh();
    }

    /**
     * **La prueba que cierra el catálogo: los setenta se pueden abrir.**
     *
     * Es la que impide que alguien marque un documento como emitible sin darle
     * destino — un renglón verde con un botón que no lleva a ningún lado es peor
     * que decir que falta.
     */
    #[Test]
    public function every_document_in_the_catalogue_opens_something(): void
    {
        $groups = app(DocumentCatalogue::class)->forProject($this->project);
        $opened = 0;

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                $this->assertSame(
                    DocumentCatalogue::STATE_READY,
                    $document['state'],
                    "«{$document['code']}» todavía no se puede emitir.",
                );

                $this->assertNotNull(
                    $document['url'],
                    "«{$document['code']}» está listo y no lleva a ninguna pantalla.",
                );

                $opened++;
            }
        }

        $this->assertGreaterThanOrEqual(70, $opened);
    }

    /** Cada derivado del motor tiene ayuda, columnas y texto de vacío. */
    #[Test]
    public function every_derived_document_explains_itself_in_both_languages(): void
    {
        $engine = app(DerivedDocument::class);

        /** @var array<string, mixed> $documents */
        $documents = config('pmi_derived.documents');

        foreach (array_keys($documents) as $code) {
            $this->assertTrue($engine->handles($code));
            $this->assertNotSame([], $engine->columns($code), "«{$code}» no tiene columnas.");

            foreach (['es', 'en'] as $locale) {
                foreach (["derived.help_{$code}", "derived.empty_{$code}"] as $key) {
                    $this->assertNotSame($key, __($key, [], $locale), "Falta «{$key}» en {$locale}.");
                }

                foreach ($engine->columns($code) as $column) {
                    $this->assertNotSame(
                        "derived.col_{$column}",
                        __("derived.col_{$column}", [], $locale),
                        "Falta el título de la columna «{$column}» en {$locale}.",
                    );
                }
            }
        }
    }

    #[Test]
    public function the_wbs_dictionary_comes_from_the_plan(): void
    {
        $package = $this->task('Análisis', ['duration_minutes' => 0]);
        $this->task('Levantamiento', ['parent_id' => $package->id, 'description' => 'Contar lo que hay.']);

        $this->actingAs($this->manager)
            ->get(route('projects.documents.derived', [$this->project, 'wbs_dictionary']))
            ->assertOk()
            ->assertSee('Levantamiento')
            ->assertSee('Contar lo que hay.')
            ->assertSee(__('derived.col_wbs'));
    }

    #[Test]
    public function the_risk_report_orders_by_exposure(): void
    {
        foreach ([['Menor', 1, 1], ['Grave', 5, 5]] as [$name, $probability, $impact]) {
            Risk::query()->create([
                'project_id' => $this->project->id,
                'code' => 'R-'.$probability,
                'description' => $name,
                'probability' => $probability,
                'impact' => $impact,
                'kind' => Risk::KIND_THREAT,
                'status' => Risk::STATUS_IDENTIFIED,
            ]);
        }

        $rows = app(DerivedDocument::class)->rows($this->project, 'risk_report');

        // De mayor a menor: un informe ordenado por clave obliga a leerlo entero
        // para encontrar el que importa.
        $this->assertSame('Grave', $rows[0]['description']);
    }

    /** El plan de involucramiento deduce el cuadrante de poder e interés. */
    #[Test]
    public function the_engagement_plan_derives_the_quadrant(): void
    {
        Stakeholder::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Jorge',
            'power' => 5,
            'interest' => 5,
            'sort_order' => 0,
        ]);

        $rows = app(DerivedDocument::class)->rows($this->project, 'stakeholder_engagement_plan');

        $this->assertSame(__('derived.quadrant_manage'), $rows[0]['quadrant']);
    }

    /**
     * **Sin línea base, la de costos sale vacía a propósito.** Comparar contra el
     * plan de hoy daría varianza cero en todo y haría creer que el costo no se
     * ha movido.
     */
    #[Test]
    public function the_cost_baseline_stays_empty_without_a_baseline(): void
    {
        $this->task('Sola');

        $this->assertSame([], app(DerivedDocument::class)->rows($this->project, 'cost_baseline'));

        app(BaselineManager::class)->capture($this->project, 'Aprobada', 'Al arrancar.');

        $this->assertNotSame([], app(DerivedDocument::class)->rows($this->project->refresh(), 'cost_baseline'));
    }

    /** Y cuando sale vacío, la pantalla dice qué capturar y dónde. */
    #[Test]
    public function an_empty_document_says_what_to_capture(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.derived', [$this->project, 'resource_breakdown_structure']))
            ->assertOk()
            ->assertSee(__('derived.empty'))
            ->assertSee(__('derived.empty_resource_breakdown_structure'));
    }

    /**
     * El pronóstico **se niega a inventar una fecha** cuando no hay con qué.
     * Una fecha absurda en un informe se cree.
     */
    #[Test]
    public function the_schedule_forecast_refuses_to_invent_a_date(): void
    {
        $this->task('Sola');

        $rows = app(DerivedDocument::class)->rows($this->project, 'schedule_forecasts');
        $forecast = collect($rows)->firstWhere('measure', __('derived.measure_forecast_finish'));

        $this->assertSame('—', $forecast['value']);
        $this->assertSame(__('derived.forecast_blocked'), $forecast['reading']);
    }

    #[Test]
    public function the_rbs_lists_people_and_materials_with_their_own_units(): void
    {
        foreach ([
            ['Ana', Resource::TYPE_PERSON, ['cost_per_hour' => 250, 'capacity_percent' => 100]],
            ['Acero', Resource::TYPE_MATERIAL, ['cost_per_unit' => 40, 'unit_of_measure' => 'kg']],
        ] as [$name, $type, $extra]) {
            $resource = new Resource;
            $resource->fill(['project_id' => $this->project->id, 'name' => $name, 'type' => $type, ...$extra]);
            $resource->save();
        }

        $this->actingAs($this->manager)
            ->get(route('projects.documents.derived', [$this->project, 'resource_breakdown_structure']))
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee('Acero')
            // La tarifa se dice en la unidad de cada especie: «por hora» a un
            // material sería un dato falso.
            ->assertSee('kg');
    }

    #[Test]
    public function a_derived_document_comes_out_as_a_pdf(): void
    {
        $this->task('Una tarea');

        $response = $this->actingAs($this->manager)
            ->get(route('projects.documents.derived.pdf', [$this->project, 'wbs_dictionary']))
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    #[Test]
    public function a_document_the_engine_does_not_handle_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.derived', [$this->project, 'project_schedule']))
            ->assertNotFound();
    }

    // ------------------------------------------- Requisitos y trazabilidad

    /**
     * **Los dos huecos son la razón de ser de la matriz.** Lo que se pidió y
     * nadie construye, y lo que se construye sin que nadie lo haya pedido.
     */
    #[Test]
    public function the_matrix_finds_both_gaps(): void
    {
        $delivered = $this->task('Lo que sí se pidió');
        $this->task('Lo que nadie pidió');

        ProjectRequirement::query()->create([
            'project_id' => $this->project->id,
            'sequence' => 1,
            'description' => 'Cerrar el mes en un día',
            'task_id' => $delivered->id,
            'priority' => 'must',
            'status' => 'approved',
        ]);

        ProjectRequirement::query()->create([
            'project_id' => $this->project->id,
            'sequence' => 2,
            'description' => 'Nadie lo está construyendo',
            'priority' => 'should',
            'status' => 'approved',
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.requirements', $this->project))
            ->assertOk()
            ->assertSee(__('requirements.orphans'))
            ->assertSee(__('requirements.unrequested'))
            ->assertSee('Nadie lo está construyendo')
            ->assertSee(__('requirements.nobody'));
    }

    #[Test]
    public function a_requirement_can_be_captured_and_numbered(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.requirements.store', $this->project), [
                'description' => 'El cierre mensual se hace en un día',
                'origin' => 'Jorge, gerente de almacén',
                'priority' => 'must',
                'status' => 'proposed',
            ])
            ->assertRedirect();

        $requirement = ProjectRequirement::query()->firstOrFail();

        $this->assertSame('REQ-001', $requirement->reference());
        $this->assertTrue($requirement->isOrphan());
    }

    /** Un entregable de otro proyecto se rechaza. */
    #[Test]
    public function a_deliverable_from_another_project_is_rejected(): void
    {
        $other = Project::query()->create([
            'code' => 'DER-2',
            'name' => 'Otro',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $foreign = new Task;
        $foreign->fill(['project_id' => $other->id, 'name' => 'Ajena', 'duration_minutes' => 540, 'sort_order' => 0]);
        $foreign->save();

        $this->actingAs($this->manager)
            ->post(route('projects.requirements.store', $this->project), [
                'description' => 'Algo',
                'priority' => 'must',
                'status' => 'proposed',
                'task_id' => $foreign->id,
            ])
            ->assertSessionHasErrors('task_id');
    }

    // ------------------------------------------------------- El expediente

    /**
     * El expediente lleva **las versiones emitidas**, no lo que el sistema
     * generaría hoy. Uno que se regenera al abrirlo diría cosas distintas cada
     * vez, y entonces no prueba nada.
     */
    #[Test]
    public function the_archive_packs_the_issued_versions_with_an_index(): void
    {
        $this->task('Una tarea');

        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']))
            ->assertRedirect();

        $path = app(ProjectArchive::class)->build($this->project->refresh());

        $this->assertFileExists($path);

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path) === true);

        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }

        $index = (string) $zip->getFromName('INDICE.html');
        $zip->close();
        @unlink($path);

        $this->assertContains('INDICE.html', $names);
        $this->assertGreaterThanOrEqual(2, count($names), 'El paquete tiene que traer el indice y al menos un documento.');
        $this->assertStringContainsString($this->project->code, $index);
        $this->assertStringContainsString(__('archive.index_note'), $index);
    }

    /** Sin nada emitido, el tablero lo dice en vez de ofrecer un ZIP vacío. */
    #[Test]
    public function the_board_offers_no_archive_when_nothing_was_issued(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents', $this->project))
            ->assertOk()
            ->assertSee(__('archive.empty'))
            ->assertDontSee(__('archive.download'));
    }

    #[Test]
    public function the_archive_downloads_as_a_zip(): void
    {
        $this->task('Una tarea');

        $this->actingAs($this->manager)->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $this->actingAs($this->manager)
            ->get(route('projects.documents.archive', $this->project))
            ->assertOk()
            ->assertDownload($this->project->code.'-expediente.zip');
    }

    #[Test]
    public function someone_outside_the_project_cannot_take_the_archive(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->get(route('projects.documents.archive', $this->project))
            ->assertForbidden();
    }
}
