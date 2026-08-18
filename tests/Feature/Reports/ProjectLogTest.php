<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectLogEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\Documents\DocumentCatalogue;
use App\Support\Documents\ProjectLog;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El motor de los catorce registros que crecen durante el proyecto.
 *
 * Lo que se cuida aquí no es una pantalla: es que **catorce documentos
 * funcionen con una sola tabla**. Si el motor deja de ser genérico —si alguien
 * mete un caso especial para las incidencias— el bloque entero vuelve a costar
 * catorce pantallas, catorce migraciones y catorce lugares donde arreglar el
 * mismo defecto.
 */
final class ProjectLogTest extends TestCase
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
            'code' => 'LOG-1',
            'name' => 'Proyecto registrado',
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
     * La prueba que sostiene la decisión: **los catorce están sobre el motor**.
     *
     * Lo que impide es que alguien marque un registro como emitible sin darle
     * definición —lo que abriría una pantalla sin estados posibles— o que
     * construya la pantalla número quince a mano.
     */
    #[Test]
    public function every_log_document_runs_on_the_engine(): void
    {
        $engine = app(ProjectLog::class);

        /** @var array<string, array{kind: string}> $catalogue */
        $catalogue = config('pmi_documents.catalogue');

        $covered = 0;

        foreach ($catalogue as $code => $entry) {
            if ($entry['kind'] !== 'log') {
                continue;
            }

            $this->assertTrue(
                $engine->isLog($code),
                "«{$code}» es un registro y no tiene definición en config/pmi_logs.php.",
            );

            $this->assertNotSame(
                [],
                $engine->statuses($code),
                "«{$code}» abriría una pantalla sin un solo estado posible.",
            );

            $covered++;
        }

        $this->assertGreaterThanOrEqual(14, $covered, 'Se esperaban al menos catorce registros sobre el motor.');
    }

    /**
     * Cada estado y cada ayuda tienen texto en los dos idiomas. Sin esto, el
     * que se agregue mañana aparece en pantalla como `logs.status_algo`.
     */
    #[Test]
    public function every_status_and_help_has_text_in_both_languages(): void
    {
        $engine = app(ProjectLog::class);

        /** @var array<string, mixed> $types */
        $types = config('pmi_logs.types');

        foreach (array_keys($types) as $code) {
            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "logs.help_{$code}",
                    __("logs.help_{$code}", [], $locale),
                    "Falta la ayuda de «{$code}» en {$locale}.",
                );

                foreach ($engine->statuses($code) as $status) {
                    $this->assertNotSame(
                        "logs.status_{$status}",
                        __("logs.status_{$status}", [], $locale),
                        "Falta el estado «{$status}» de «{$code}» en {$locale}.",
                    );
                }
            }
        }

        /** @var list<string> $priorities */
        $priorities = config('pmi_logs.priorities');

        foreach ($priorities as $priority) {
            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "logs.priority_{$priority}",
                    __("logs.priority_{$priority}", [], $locale),
                    "Falta la prioridad «{$priority}» en {$locale}.",
                );
            }
        }
    }

    /**
     * Los estados que cierran tienen que pertenecer al juego. Un `closed` mal
     * escrito no falla: simplemente deja de contar como cerrado, y el tablero
     * enseña pendientes que no lo son.
     */
    #[Test]
    public function the_closing_statuses_belong_to_their_own_set(): void
    {
        /** @var array<string, array{values: list<string>, closed: list<string>}> $sets */
        $sets = config('pmi_logs.statuses');

        foreach ($sets as $set => $definition) {
            $this->assertNotSame([], $definition['values'], "El juego «{$set}» no tiene estados.");

            foreach ($definition['closed'] as $status) {
                $this->assertContains(
                    $status,
                    $definition['values'],
                    "«{$status}» cierra el juego «{$set}» pero no es uno de sus estados.",
                );
            }

            // Un juego sin ningún estado de cierre contaría todo como abierto
            // para siempre, y el contador de pendientes dejaria de servir.
            $this->assertNotSame([], $definition['closed'], "El juego «{$set}» no tiene forma de cerrarse.");
        }
    }

    #[Test]
    public function an_entry_can_be_recorded_and_read_back(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.log.store', [$this->project, 'issue_log']), [
                'occurred_on' => '2026-08-17',
                'title' => 'El proveedor no entrego el material',
                'detail' => 'Quedo de entregar el viernes.',
                'status' => 'open',
                'priority' => 'high',
            ])
            ->assertRedirect();

        $entry = ProjectLogEntry::query()->firstOrFail();

        $this->assertSame('issue_log', $entry->document_code);
        $this->assertSame('El proveedor no entrego el material', $entry->title);
        $this->assertSame('high', $entry->priority);
        $this->assertSame('INC-001', $entry->reference());
    }

    /**
     * El número se asigna por proyecto **y por tipo**. Que la primera incidencia
     * y la primera decisión sean las dos la número uno es justo lo que hace que
     * el número sirva para citar algo en una junta.
     */
    #[Test]
    public function numbering_runs_separately_for_each_register(): void
    {
        $engine = app(ProjectLog::class);

        $first = $engine->record($this->project, 'issue_log', ['title' => 'Una', 'status' => 'open']);
        $second = $engine->record($this->project, 'issue_log', ['title' => 'Otra', 'status' => 'open']);
        $decision = $engine->record($this->project, 'decision_log', ['title' => 'Se decide', 'status' => 'decided']);

        $this->assertSame('INC-001', $first->reference());
        $this->assertSame('INC-002', $second->reference());
        $this->assertSame('DEC-001', $decision->reference());
    }

    /**
     * Un número borrado **no se reutiliza**. Si INC-004 se citó en un correo,
     * que el siguiente renglón se llame INC-004 es peor que que el número falte.
     */
    #[Test]
    public function a_deleted_number_is_never_handed_out_again(): void
    {
        $engine = app(ProjectLog::class);

        $first = $engine->record($this->project, 'issue_log', ['title' => 'Una', 'status' => 'open']);

        $this->actingAs($this->manager)
            ->delete(route('projects.documents.log.destroy', [$this->project, 'issue_log', $first]))
            ->assertRedirect();

        $next = $engine->record($this->project, 'issue_log', ['title' => 'La siguiente', 'status' => 'open']);

        $this->assertSame('INC-002', $next->reference());
        $this->assertSoftDeleted($first);
    }

    /**
     * Los campos que no aplican al tipo se descartan. Filtrarlo en el motor y no
     * confiar en el formulario impide que una petición armada a mano le ponga
     * prioridad y fecha compromiso a una minuta.
     */
    #[Test]
    public function fields_that_do_not_belong_to_the_register_are_discarded(): void
    {
        $entry = app(ProjectLog::class)->record($this->project, 'meeting_minutes', [
            'title' => 'Junta de arranque',
            'status' => 'issued',
            'priority' => 'critical',
            'due_on' => '2026-09-01',
            'outcome' => 'No aplica a una minuta.',
        ]);

        $this->assertNull($entry->priority);
        $this->assertNull($entry->due_on);
        $this->assertNull($entry->outcome);
    }

    /**
     * Un estado que no pertenece al juego del registro se rechaza en la
     * validación: un «rechazado» en una minuta pintaría un renglón sin
     * distintivo y sin que nada falle.
     */
    #[Test]
    public function a_status_from_another_register_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.log.store', [$this->project, 'meeting_minutes']), [
                'occurred_on' => '2026-08-17',
                'title' => 'Junta',
                'status' => 'rejected',
            ])
            ->assertSessionHasErrors('status');

        $this->assertSame(0, ProjectLogEntry::query()->count());
    }

    /** Se cuenta lo abierto sobre el registro completo, no sobre lo filtrado. */
    #[Test]
    public function the_summary_counts_what_is_still_open(): void
    {
        $engine = app(ProjectLog::class);

        $engine->record($this->project, 'issue_log', ['title' => 'Abierta', 'status' => 'open']);
        $engine->record($this->project, 'issue_log', ['title' => 'En proceso', 'status' => 'in_progress']);
        $engine->record($this->project, 'issue_log', ['title' => 'Cerrada', 'status' => 'closed']);
        $engine->record($this->project, 'issue_log', [
            'title' => 'Vencida',
            'status' => 'open',
            'due_on' => '2020-01-01',
        ]);

        $summary = $engine->summary($this->project, 'issue_log');

        $this->assertSame(4, $summary['total']);
        $this->assertSame(3, $summary['open']);
        $this->assertSame(1, $summary['overdue']);
    }

    /**
     * Un registro sin fecha compromiso nunca reporta vencidos. Contar como
     * vencido algo que no tiene fecha entrena a la gente a ignorar el aviso.
     */
    #[Test]
    public function a_register_without_due_dates_never_reports_overdue(): void
    {
        $engine = app(ProjectLog::class);

        $engine->record($this->project, 'lessons_learned_register', [
            'title' => 'Se aprendio algo',
            'status' => 'captured',
            'due_on' => '2020-01-01',
        ]);

        $this->assertSame(0, $engine->summary($this->project, 'lessons_learned_register')['overdue']);
    }

    #[Test]
    public function the_filter_narrows_by_status_and_text(): void
    {
        $engine = app(ProjectLog::class);

        $engine->record($this->project, 'issue_log', ['title' => 'Falta material', 'status' => 'open']);
        $engine->record($this->project, 'issue_log', ['title' => 'Falta personal', 'status' => 'closed']);

        $this->assertCount(1, $engine->entries($this->project, 'issue_log', ['status' => 'open']));
        $this->assertCount(1, $engine->entries($this->project, 'issue_log', ['q' => 'material']));
        $this->assertCount(2, $engine->entries($this->project, 'issue_log', []));
    }

    /** Se puede corregir lo capturado, pero el número no se mueve. */
    #[Test]
    public function an_entry_can_be_amended_without_changing_its_number(): void
    {
        $entry = app(ProjectLog::class)->record($this->project, 'issue_log', [
            'title' => 'Con error de dedo',
            'status' => 'open',
        ]);

        $this->actingAs($this->manager)
            ->put(route('projects.documents.log.update', [$this->project, 'issue_log', $entry]), [
                'occurred_on' => '2026-08-14',
                'title' => 'Corregida',
                'status' => 'resolved',
                'outcome' => 'El proveedor entrego el lunes.',
            ])
            ->assertRedirect();

        $entry->refresh();

        $this->assertSame('Corregida', $entry->title);
        $this->assertSame('resolved', $entry->status);
        $this->assertSame(1, $entry->sequence);
        $this->assertSame('INC-001', $entry->reference());
    }

    #[Test]
    public function the_register_comes_out_as_a_pdf(): void
    {
        app(ProjectLog::class)->record($this->project, 'decision_log', [
            'title' => 'Se decide arrancar sin el segundo proveedor',
            'status' => 'decided',
        ]);

        $response = $this->actingAs($this->manager)
            ->get(route('projects.documents.log.pdf', [$this->project, 'decision_log']))
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    #[Test]
    public function an_invented_register_code_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.log', [$this->project, 'registro_inventado']))
            ->assertNotFound();
    }

    /** Un documento que se redacta no es un registro: no tiene esta pantalla. */
    #[Test]
    public function a_narrative_document_has_no_log_screen(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.log', [$this->project, 'scope_management_plan']))
            ->assertNotFound();
    }

    /**
     * Un renglón de otro registro responde 404 y no 403: que exista es
     * exactamente lo que no se quiere confirmar.
     */
    #[Test]
    public function an_entry_from_another_register_is_not_found(): void
    {
        $entry = app(ProjectLog::class)->record($this->project, 'decision_log', [
            'title' => 'De otro registro',
            'status' => 'decided',
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.documents.log.edit', [$this->project, 'issue_log', $entry]))
            ->assertNotFound();
    }

    #[Test]
    public function someone_without_write_access_cannot_record(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->post(route('projects.documents.log.store', [$this->project, 'issue_log']), [
                'occurred_on' => '2026-08-17',
                'title' => 'Intruso',
                'status' => 'open',
            ])
            ->assertForbidden();
    }

    /** El tablero enlaza los catorce a su pantalla. */
    #[Test]
    public function the_board_links_the_fourteen_registers(): void
    {
        $groups = app(DocumentCatalogue::class)->forProject($this->project);

        $linked = 0;

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                if ($document['kind'] !== 'log') {
                    continue;
                }

                $this->assertSame(DocumentCatalogue::STATE_READY, $document['state']);
                $this->assertNotNull(
                    $document['url'],
                    "«{$document['code']}» es un registro y no lleva a ninguna pantalla.",
                );

                $linked++;
            }
        }

        $this->assertGreaterThanOrEqual(14, $linked);
    }

    /** La pantalla abre y dice qué se anota ahí. */
    #[Test]
    public function the_screen_opens_and_explains_what_belongs_in_it(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.log', [$this->project, 'issue_log']))
            ->assertOk()
            ->assertSee(__('documents.doc_issue_log'))
            ->assertSee(__('logs.help_issue_log'));
    }
}
