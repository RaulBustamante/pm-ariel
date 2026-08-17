<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Documents\DocumentCatalogue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El catálogo de documentos del PMI.
 *
 * Lo que estas pruebas cuidan no es que se vea bonito: es que **el hueco siga
 * siendo visible**. La tentación, cuando el tablero enseña cuarenta y nueve
 * documentos sin construir, es recortar la lista a lo que ya existe para que se
 * vea completo. Un sistema así se ve terminado hasta que alguien pide el que
 * falta a media junta.
 */
final class DocumentCatalogueTest extends TestCase
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
            'code' => 'DOC-1',
            'name' => 'Proyecto documentado',
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

    #[Test]
    public function the_board_shows_the_five_process_groups(): void
    {
        $response = $this->actingAs($this->manager)
            ->get(route('projects.documents', $this->project))
            ->assertOk();

        foreach (DocumentCatalogue::GROUPS as $group) {
            $response->assertSee(__("documents.group_{$group}"));
        }
    }

    /**
     * Cada documento del catálogo tiene nombre en los dos idiomas. Sin esto, el
     * que se agregue mañana aparece en pantalla como `documents.doc_algo`.
     */
    #[Test]
    public function every_document_in_the_catalogue_has_a_name_in_both_languages(): void
    {
        /** @var array<string, mixed> $catalogue */
        $catalogue = config('pmi_documents.catalogue');

        $this->assertNotEmpty($catalogue);

        foreach (array_keys($catalogue) as $code) {
            foreach (['es', 'en'] as $locale) {
                $key = "documents.doc_{$code}";

                $this->assertNotSame(
                    $key,
                    __($key, [], $locale),
                    "Falta el nombre de «{$code}» en {$locale}.",
                );
            }
        }
    }

    /**
     * Las tres clasificaciones tienen que ser valores conocidos: un `state` mal
     * escrito pintaría el renglón sin distintivo y sin que nada falle.
     */
    #[Test]
    public function the_catalogue_only_uses_known_groups_kinds_and_states(): void
    {
        /** @var array<string, array{group: string, kind: string, state: string}> $catalogue */
        $catalogue = config('pmi_documents.catalogue');

        foreach ($catalogue as $code => $entry) {
            $this->assertContains($entry['group'], DocumentCatalogue::GROUPS, "Grupo desconocido en «{$code}».");
            $this->assertContains($entry['kind'], ['derived', 'narrative', 'log', 'record'], "Especie desconocida en «{$code}».");
            $this->assertContains($entry['state'], ['ready', 'partial', 'planned'], "Estado desconocido en «{$code}».");
        }
    }

    /**
     * Un documento marcado como emitible tiene que llevar a algún lado. Marcarlo
     * listo sin destino da un renglón verde con un botón que no existe, que es
     * peor que decir que falta.
     */
    #[Test]
    public function everything_marked_ready_actually_opens_something(): void
    {
        $groups = app(DocumentCatalogue::class)->forProject($this->project);

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                if ($document['state'] !== DocumentCatalogue::STATE_READY) {
                    continue;
                }

                $this->assertNotNull(
                    $document['url'],
                    "«{$document['code']}» está marcado como emitible y no lleva a ninguna pantalla.",
                );
            }
        }
    }

    /**
     * El catálogo no se recorta.
     *
     * Es la prueba que de verdad importa: sostiene la decisión de enseñar los
     * setenta, incluidos los que faltan. Si alguien borra los pendientes para
     * que el tablero se vea al 100 %, esto falla y obliga a decirlo en voz alta.
     */
    #[Test]
    public function the_catalogue_keeps_the_whole_pmi_set(): void
    {
        $coverage = app(DocumentCatalogue::class)->coverage();

        $this->assertGreaterThanOrEqual(70, $coverage['total']);
        $this->assertSame(
            $coverage['total'],
            $coverage['ready'] + $coverage['partial'] + $coverage['planned'],
            'Hay documentos en el catálogo que no caen en ninguno de los tres estados.',
        );
    }
}
