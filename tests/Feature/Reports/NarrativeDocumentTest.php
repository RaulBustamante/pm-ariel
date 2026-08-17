<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Role;
use App\Models\User;
use App\Support\Documents\DocumentCatalogue;
use App\Support\Documents\NarrativeDocument;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El motor de documentos narrativos.
 *
 * Lo que se cuida aquí no es una pantalla: es que **veinticinco documentos
 * funcionen con una sola maquinaria**. Si el motor deja de ser genérico —si
 * alguien mete un caso especial para el plan de costos— la etapa entera vuelve a
 * costar veinticinco pantallas.
 */
final class NarrativeDocumentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'NAR-1',
            'name' => 'Proyecto redactado',
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
    }

    /**
     * La prueba que sostiene la decisión: **cada documento narrativo tiene a
     * dónde ir**. O lo atiende el motor genérico, o tiene una pantalla propia.
     *
     * El acta constitutiva es el caso de la segunda clase: se captura en el
     * recorrido de inicio, con su propio modelo y sus propias reglas, desde la
     * Etapa 2. Meterla al motor genérico duplicaría el mismo documento en dos
     * lugares — y dos lugares que guardan lo mismo acaban discrepando.
     *
     * Lo que esta prueba impide es el tercer caso: un documento marcado como
     * narrativo, sin secciones y sin pantalla propia, que abriría un formulario
     * vacío en producción sin que nada falle.
     */
    #[Test]
    public function every_narrative_document_has_somewhere_to_go(): void
    {
        $engine = app(NarrativeDocument::class);
        $groups = app(DocumentCatalogue::class)->forProject($this->project);

        /** @var array<string, string> $urls */
        $urls = [];

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                if ($document['url'] !== null) {
                    $urls[$document['code']] = $document['url'];
                }
            }
        }

        /** @var array<string, array{kind: string}> $catalogue */
        $catalogue = config('pmi_documents.catalogue');

        $generic = 0;

        foreach ($catalogue as $code => $entry) {
            if ($entry['kind'] !== 'narrative') {
                continue;
            }

            if ($engine->isNarrative($code)) {
                $this->assertNotSame(
                    [],
                    $engine->sections($code),
                    "«{$code}» abriría un formulario sin una sola sección.",
                );

                $generic++;

                continue;
            }

            $this->assertArrayHasKey(
                $code,
                $urls,
                "«{$code}» es narrativo, no tiene juego de secciones y tampoco pantalla propia: "
                .'abriría un formulario vacío o un enlace muerto.',
            );
        }

        $this->assertGreaterThanOrEqual(20, $generic, 'Se esperaban al menos veinte documentos sobre el motor genérico.');
    }

    /** Y cada sección tiene título y ayuda en los dos idiomas. */
    #[Test]
    public function every_section_has_a_title_and_help_in_both_languages(): void
    {
        $engine = app(NarrativeDocument::class);

        /** @var array<string, array<string, mixed>> $sets */
        $sets = config('pmi_sections.sets');

        foreach ($sets as $set => $sections) {
            foreach (array_keys($sections) as $key) {
                foreach (['es', 'en'] as $locale) {
                    foreach (['title', 'help'] as $part) {
                        $this->assertNotSame(
                            "sections.{$part}_{$key}",
                            __("sections.{$part}_{$key}", [], $locale),
                            "Falta «{$part}» de la sección «{$key}» del juego «{$set}» en {$locale}.",
                        );
                    }
                }
            }
        }

        $this->assertNotSame([], $engine->sections('scope_management_plan'));
    }

    #[Test]
    public function a_document_can_be_written_and_read_back(): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$this->project, 'scope_management_plan']), [
                'sections' => [
                    'approach' => 'El alcance se congela al aprobar el acta.',
                    'roles' => 'Raúl aprueba los cambios de alcance.',
                ],
            ])
            ->assertRedirect();

        $document = ProjectDocument::query()->firstOrFail();

        $this->assertSame('El alcance se congela al aprobar el acta.', $document->section('approach'));
        $this->assertNull($document->section('process'));
    }

    /**
     * Solo se guardan las secciones **del juego de este documento**. Filtrarlo
     * en el motor y no confiar en el formulario impide que una petición armada a
     * mano meta llaves arbitrarias en el JSON.
     */
    #[Test]
    public function keys_that_do_not_belong_to_the_document_are_discarded(): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$this->project, 'scope_management_plan']), [
                'sections' => [
                    'approach' => 'Legítima.',
                    'inventada' => 'No pertenece a este juego.',
                    'lessons' => 'Es de otro documento.',
                ],
            ]);

        $content = ProjectDocument::query()->firstOrFail()->content;

        $this->assertArrayHasKey('approach', $content);
        $this->assertArrayNotHasKey('inventada', $content);
        $this->assertArrayNotHasKey('lessons', $content);
    }

    /**
     * Nada obliga a llenar todo para guardar: lo que falta se señala. Obligar a
     * completar de golpe solo consigue que alguien invente texto para avanzar, y
     * un plan con relleno es peor que uno con huecos visibles.
     */
    #[Test]
    public function a_half_written_document_saves_and_reports_what_is_missing(): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$this->project, 'scope_management_plan']), [
                'sections' => ['approach' => 'Solo esta.'],
            ])
            ->assertRedirect();

        $engine = app(NarrativeDocument::class);
        $document = $engine->of($this->project, 'scope_management_plan');

        // Del juego `management_plan` son necesarias tres: approach, roles y
        // process. Con una escrita faltan dos.
        $this->assertSame(2, $engine->missing('scope_management_plan', $document));
    }

    /** Las secciones opcionales vacías no cuentan como faltantes. */
    #[Test]
    public function optional_sections_do_not_count_as_missing(): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$this->project, 'scope_management_plan']), [
                'sections' => [
                    'approach' => 'Una.',
                    'roles' => 'Dos.',
                    'process' => 'Tres.',
                ],
            ]);

        $engine = app(NarrativeDocument::class);

        $this->assertSame(0, $engine->missing(
            'scope_management_plan',
            $engine->of($this->project, 'scope_management_plan'),
        ));
    }

    #[Test]
    public function the_document_comes_out_as_a_pdf(): void
    {
        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$this->project, 'team_charter']), [
                'sections' => ['values' => 'Se avisa antes, no después.'],
            ]);

        $response = $this->actingAs($this->manager)
            ->get(route('projects.documents.narrative.pdf', [$this->project, 'team_charter']))
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    #[Test]
    public function an_invented_document_code_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.narrative', [$this->project, 'plan_inventado']))
            ->assertNotFound();
    }

    /** Un documento que se genera solo no se redacta: no tiene formulario. */
    #[Test]
    public function a_derived_document_has_no_writing_screen(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.narrative', [$this->project, 'project_schedule']))
            ->assertNotFound();
    }

    #[Test]
    public function someone_without_write_access_cannot_save(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->put(route('projects.documents.narrative.update', [$this->project, 'team_charter']), [
                'sections' => ['values' => 'Intruso.'],
            ])
            ->assertForbidden();
    }

    /** El tablero ya enlaza los veinticinco a su pantalla de redacción. */
    #[Test]
    public function the_board_links_the_narrative_documents(): void
    {
        $groups = app(DocumentCatalogue::class)->forProject($this->project);

        $linked = 0;

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                if ($document['kind'] === 'narrative' && $document['state'] === DocumentCatalogue::STATE_READY) {
                    $this->assertNotNull(
                        $document['url'],
                        "«{$document['code']}» está listo y no lleva a ninguna pantalla.",
                    );
                    $linked++;
                }
            }
        }

        $this->assertGreaterThanOrEqual(20, $linked);
    }
}
