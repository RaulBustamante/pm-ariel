<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\DocumentIssue;
use App\Models\Project;
use App\Models\ProjectRecord;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Documents\AcceptanceRecord;
use App\Support\Documents\DocumentCatalogue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * Las actas de aceptación: la cuarta y última especie del catálogo.
 *
 * Lo que estas pruebas cuidan es **lo que hace que un acta valga algo**: que una
 * vez firmada no se pueda tocar. Un documento de aceptación editable no prueba
 * nada, y la tentación al construirlo es dejar «solo un campito» modificable
 * para corregir un dedo — que es exactamente por donde se pierde.
 */
final class AcceptanceRecordTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'ACT-1',
            'name' => 'Proyecto aceptado',
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
     * @param  array<string, mixed>  $extra
     */
    private function open(string $code = 'acceptance_signoff', array $extra = []): ProjectRecord
    {
        return app(AcceptanceRecord::class)->open($this->project, $code, [
            'subject' => 'El sistema de inventario en producción',
            'detail' => 'Incluye catálogo, movimientos y reportes.',
            'decision' => ProjectRecord::ACCEPTED,
            'accepted_by_name' => 'Jorge Medina',
            'accepted_by_role' => 'Gerente de almacén',
            'accepted_by_org' => 'Operaciones',
            'accepted_on' => '2026-08-17',
            ...$extra,
        ]);
    }

    /** Las dos actas del catálogo corren sobre el motor y tienen definición. */
    #[Test]
    public function every_record_document_runs_on_the_engine(): void
    {
        $engine = app(AcceptanceRecord::class);

        /** @var array<string, array{kind: string}> $catalogue */
        $catalogue = config('pmi_documents.catalogue');

        $covered = 0;

        foreach ($catalogue as $code => $entry) {
            if ($entry['kind'] !== 'record') {
                continue;
            }

            $this->assertTrue($engine->isRecord($code), "«{$code}» es un acta y no tiene definición.");
            $this->assertNotSame('#', (string) config("pmi_records.types.{$code}.prefix"));

            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "records.help_{$code}",
                    __("records.help_{$code}", [], $locale),
                    "Falta la ayuda de «{$code}» en {$locale}.",
                );
            }

            $covered++;
        }

        $this->assertGreaterThanOrEqual(2, $covered);
    }

    /** Las tres respuestas tienen texto en los dos idiomas. */
    #[Test]
    public function every_decision_has_text_in_both_languages(): void
    {
        foreach (app(AcceptanceRecord::class)->decisions() as $decision) {
            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "records.decision_{$decision}",
                    __("records.decision_{$decision}", [], $locale),
                    "Falta la respuesta «{$decision}» en {$locale}.",
                );
            }
        }
    }

    #[Test]
    public function a_record_is_opened_as_a_draft(): void
    {
        $record = $this->open();

        $this->assertSame('ACT-001', $record->reference());
        $this->assertFalse($record->isSigned());
        $this->assertNull($record->checksum);
    }

    /** Cada tipo lleva su propia numeración, como los registros. */
    #[Test]
    public function numbering_runs_separately_for_each_kind(): void
    {
        $signoff = $this->open();
        $delivery = $this->open('deliverable_acceptance_records');

        $this->assertSame('ACT-001', $signoff->reference());
        $this->assertSame('ACE-001', $delivery->reference());
    }

    /**
     * **La prueba que sostiene la especie entera: firmada no se toca.**
     *
     * La guarda vive en el modelo y no en el controlador a propósito. Si
     * dependiera de recordar comprobarlo en cada camino de escritura, el día que
     * alguien agregue uno nuevo el acta dejaría de ser inmutable sin que nada
     * avise.
     */
    #[Test]
    public function a_signed_record_cannot_be_edited(): void
    {
        $record = $this->open();
        $this->signIt($record);

        $this->expectException(RuntimeException::class);

        $record->update(['subject' => 'Otra cosa']);
    }

    /** Ni se borra. */
    #[Test]
    public function a_signed_record_cannot_be_deleted(): void
    {
        $record = $this->open();
        $this->signIt($record);

        $this->expectException(RuntimeException::class);

        $record->delete();
    }

    /** Y el motor lo dice antes, para poder contestarlo con una pantalla. */
    #[Test]
    public function the_engine_refuses_to_amend_a_signed_record(): void
    {
        $record = $this->open();
        $this->signIt($record);

        $this->expectException(RuntimeException::class);

        app(AcceptanceRecord::class)->amend($record, ['subject' => 'Otra cosa']);
    }

    /**
     * Firmar **archiva el PDF** con el motor del bloque 7.1: el acta queda con
     * número de versión, fecha y huella, y se encuentra dentro de un año tal
     * como se firmó.
     */
    #[Test]
    public function signing_archives_the_pdf_as_an_issued_version(): void
    {
        $record = $this->open();

        $this->actingAs($this->manager)
            ->post(route('projects.documents.record.sign', [$this->project, 'acceptance_signoff', $record]))
            ->assertRedirect();

        $record->refresh();

        $this->assertTrue($record->isSigned());
        $this->assertSame($record->fingerprint(), $record->checksum);
        $this->assertSame($this->manager->id, $record->signed_by);

        $issue = DocumentIssue::query()
            ->where('project_id', $this->project->id)
            ->where('document_code', 'acceptance_signoff')
            ->firstOrFail();

        $this->assertSame(1, $issue->version);
        $this->assertStringContainsString('ACT-001', $issue->title);
        Storage::disk('local')->assertExists($issue->stored_path);
    }

    /** Firmar dos veces no vuelve a archivar ni mueve la fecha. */
    #[Test]
    public function signing_twice_is_refused(): void
    {
        $record = $this->open();
        $this->signIt($record);

        $signedAt = $record->refresh()->signed_at;

        $this->actingAs($this->manager)
            ->post(route('projects.documents.record.sign', [$this->project, 'acceptance_signoff', $record]))
            ->assertRedirect();

        $this->assertEquals($signedAt, $record->refresh()->signed_at);
        $this->assertSame(1, DocumentIssue::query()->count());
    }

    /**
     * Aceptar con reservas sin decir cuáles se rechaza. Un acta que afirma que
     * hay condiciones y no nombra ninguna se discute igual que si no existiera.
     */
    #[Test]
    public function accepting_with_reservations_demands_the_reservations(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.record.store', [$this->project, 'acceptance_signoff']), [
                'subject' => 'El sistema',
                'decision' => ProjectRecord::ACCEPTED_WITH_RESERVATIONS,
                'accepted_by_name' => 'Jorge Medina',
                'accepted_on' => '2026-08-17',
            ])
            ->assertSessionHasErrors('reservations');

        $this->assertSame(0, ProjectRecord::query()->count());
    }

    /**
     * Y una aceptación limpia no conserva reservas: un texto de condiciones al
     * lado de un «aceptado» se contradice solo.
     */
    #[Test]
    public function a_clean_acceptance_keeps_no_reservations(): void
    {
        $record = $this->open('acceptance_signoff', [
            'decision' => ProjectRecord::ACCEPTED,
            'reservations' => 'Esto no debería quedarse.',
        ]);

        $this->assertNull($record->reservations);
    }

    /** El acta del proyecto entero no apunta a una tarea, aunque se le mande. */
    #[Test]
    public function the_project_signoff_does_not_link_a_deliverable(): void
    {
        $task = $this->task();

        $record = $this->open('acceptance_signoff', ['task_id' => $task->id]);

        $this->assertNull($record->task_id);
    }

    /** La de un entregable sí, y es lo que la hace rastreable hasta el plan. */
    #[Test]
    public function the_deliverable_record_links_the_task(): void
    {
        $task = $this->task();

        $record = $this->open('deliverable_acceptance_records', ['task_id' => $task->id]);

        $this->assertSame($task->id, $record->task_id);
    }

    /** Un entregable de otro proyecto se rechaza en la validación. */
    #[Test]
    public function a_deliverable_from_another_project_is_rejected(): void
    {
        $other = Project::query()->create([
            'code' => 'ACT-2',
            'name' => 'Otro',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $foreign = new Task;
        $foreign->fill([
            'project_id' => $other->id,
            'name' => 'De otro proyecto',
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);
        $foreign->save();

        $this->actingAs($this->manager)
            ->post(route('projects.documents.record.store', [$this->project, 'deliverable_acceptance_records']), [
                'subject' => 'Algo',
                'task_id' => $foreign->id,
                'decision' => ProjectRecord::ACCEPTED,
                'accepted_by_name' => 'Jorge Medina',
                'accepted_on' => '2026-08-17',
            ])
            ->assertSessionHasErrors('task_id');
    }

    /** Un borrador sí se puede corregir y tirar. */
    #[Test]
    public function a_draft_can_be_corrected_and_discarded(): void
    {
        $record = $this->open();

        $this->actingAs($this->manager)
            ->put(route('projects.documents.record.update', [$this->project, 'acceptance_signoff', $record]), [
                'subject' => 'Corregido',
                'decision' => ProjectRecord::ACCEPTED,
                'accepted_by_name' => 'Jorge Medina',
                'accepted_on' => '2026-08-17',
            ])
            ->assertRedirect();

        $this->assertSame('Corregido', $record->refresh()->subject);

        $this->actingAs($this->manager)
            ->delete(route('projects.documents.record.destroy', [$this->project, 'acceptance_signoff', $record]))
            ->assertRedirect();

        $this->assertSoftDeleted($record);
    }

    /** Editar una firmada no abre formulario: responde 404. */
    #[Test]
    public function a_signed_record_has_no_edit_screen(): void
    {
        $record = $this->open();
        $this->signIt($record);

        $this->actingAs($this->manager)
            ->get(route('projects.documents.record.edit', [$this->project, 'acceptance_signoff', $record]))
            ->assertNotFound();
    }

    #[Test]
    public function the_record_comes_out_as_a_pdf(): void
    {
        $record = $this->open();

        $response = $this->actingAs($this->manager)
            ->get(route('projects.documents.record.pdf', [$this->project, 'acceptance_signoff', $record]))
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    #[Test]
    public function the_screen_opens_and_explains_what_the_signature_is(): void
    {
        $this->open();

        $this->actingAs($this->manager)
            ->get(route('projects.documents.record', [$this->project, 'acceptance_signoff']))
            ->assertOk()
            ->assertSee(__('documents.doc_acceptance_signoff'))
            // El aviso de que esto no es una firma electrónica no es letra
            // chica: si desaparece, el sistema promete algo que no tiene.
            ->assertSee(__('records.sign_disclaimer'));
    }

    #[Test]
    public function an_invented_record_code_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents.record', [$this->project, 'acta_inventada']))
            ->assertNotFound();
    }

    #[Test]
    public function someone_without_write_access_cannot_open_a_record(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->post(route('projects.documents.record.store', [$this->project, 'acceptance_signoff']), [
                'subject' => 'Intruso',
                'decision' => ProjectRecord::ACCEPTED,
                'accepted_by_name' => 'Nadie',
                'accepted_on' => '2026-08-17',
            ])
            ->assertForbidden();
    }

    /** Las dos actas llevan a su pantalla desde el tablero. */
    #[Test]
    public function the_board_links_both_records(): void
    {
        $groups = app(DocumentCatalogue::class)->forProject($this->project);

        $found = 0;

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                if ($document['kind'] !== 'record') {
                    continue;
                }

                $this->assertSame(DocumentCatalogue::STATE_READY, $document['state']);
                $this->assertNotNull($document['url'], "«{$document['code']}» no lleva a ninguna pantalla.");
                $found++;
            }
        }

        $this->assertSame(2, $found);
    }

    /**
     * Firma por la ruta y **recarga el modelo**.
     *
     * Sin el `refresh()` el objeto de la prueba se queda con `signed_at` en
     * nulo —la firma pasó en otra petición— y la guarda del modelo lo dejaría
     * editar: no porque falle, sino porque estaría comprobando contra una foto
     * vieja. En la aplicación no ocurre, porque cada petición carga el renglón
     * de nuevo.
     */
    private function signIt(ProjectRecord $record): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.record.sign', [
                $this->project, $record->document_code, $record,
            ]))
            ->assertRedirect();

        $record->refresh();
    }

    private function task(): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => 'Módulo de inventario',
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);
        $task->save();

        return $task;
    }
}
