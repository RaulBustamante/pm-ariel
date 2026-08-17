<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\DocumentIssue;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

/**
 * El archivo de versiones emitidas.
 *
 * Contesta «qué le mandé a Jorge hace tres semanas», que es lo que el sistema no
 * podía responder: volvía a generar el documento con los datos de hoy, que ya
 * son otros.
 */
final class DocumentIssueTest extends TestCase
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
            'code' => 'ISS-1',
            'name' => 'Proyecto con documentos',
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

        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => 'Levantamiento',
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);
        $task->save();
    }

    #[Test]
    public function issuing_the_weekly_status_stores_a_frozen_pdf(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']))
            ->assertRedirect();

        $issue = DocumentIssue::query()->firstOrFail();

        $this->assertSame(1, $issue->version);
        $this->assertSame('project_status_report', $issue->document_code);
        $this->assertSame($this->manager->id, $issue->issued_by);
        $this->assertGreaterThan(0, $issue->byte_size);

        Storage::disk('local')->assertExists($issue->stored_path);
    }

    /** La segunda emisión es la v2, no otra v1. */
    #[Test]
    public function versions_increase_per_document(): void
    {
        $this->actingAs($this->manager);

        $this->post(route('projects.documents.issue', [$this->project, 'weekly']));
        $this->post(route('projects.documents.issue', [$this->project, 'weekly']));
        $this->post(route('projects.documents.issue', [$this->project, 'complete']));

        $this->assertSame([1, 2], DocumentIssue::query()
            ->where('document_code', 'project_status_report')
            ->orderBy('version')->pluck('version')->all());

        // Otro documento lleva su propia numeración: la del corte semanal no lo
        // empuja a la v3.
        $this->assertSame(1, DocumentIssue::query()
            ->where('document_code', 'project_management_plan')->value('version'));
    }

    /**
     * Toda la utilidad del archivo viene de que no se pueda tocar. Un archivo
     * que se edita después no prueba nada.
     */
    #[Test]
    public function an_issued_version_cannot_be_edited(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $issue = DocumentIssue::query()->firstOrFail();

        $this->expectException(RuntimeException::class);
        $issue->update(['title' => 'Otro título']);
    }

    #[Test]
    public function an_issued_version_cannot_be_deleted(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $this->expectException(RuntimeException::class);
        DocumentIssue::query()->firstOrFail()->delete();
    }

    /**
     * Las cifras de portada se congelan con la versión: dejan encontrar la
     * correcta sin abrir siete PDF, y sobreviven aunque algún día se depuren
     * los binarios viejos.
     */
    #[Test]
    public function the_headline_figures_are_frozen_with_the_version(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $summary = DocumentIssue::query()->firstOrFail()->summary;

        $this->assertIsArray($summary);
        $this->assertArrayHasKey('progress', $summary);
        $this->assertArrayHasKey('late', $summary);
        $this->assertArrayHasKey('week_from', $summary);
    }

    #[Test]
    public function the_stored_pdf_can_be_downloaded_again(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $issue = DocumentIssue::query()->firstOrFail();

        $this->actingAs($this->manager)
            ->get(route('projects.documents.download', [$this->project, $issue]))
            ->assertOk();
    }

    /**
     * Una versión de otro proyecto no se alcanza aunque se tenga el enlace: es
     * la misma regla que protege los adjuntos.
     */
    #[Test]
    public function a_version_from_another_project_is_not_reachable(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $issue = DocumentIssue::query()->firstOrFail();

        $other = Project::query()->create([
            'code' => 'ISS-2',
            'name' => 'Otro proyecto',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);
        $other->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->actingAs($this->manager)
            ->get(route('projects.documents.download', [$other, $issue]))
            ->assertNotFound();
    }

    #[Test]
    public function someone_without_write_access_cannot_issue(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']))
            ->assertForbidden();
    }

    #[Test]
    public function an_unknown_document_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'inventado']))
            ->assertNotFound();
    }

    #[Test]
    public function the_documents_screen_lists_what_has_been_issued(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']));

        $this->actingAs($this->manager)
            ->get(route('projects.documents', $this->project))
            ->assertOk()
            ->assertSee(__('documents.issued_versions'))
            ->assertSee('v1');
    }

    #[Test]
    public function the_empty_state_explains_why_it_matters(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.documents', $this->project))
            ->assertOk()
            ->assertSee(__('documents.issued_empty'));
    }
}
