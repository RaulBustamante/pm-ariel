<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Attachment;
use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Tres reglas, y las tres son de seguridad: lista blanca de extensiones, nombre
 * en disco generado por el sistema, y descarga que comprueba permisos.
 */
final class AttachmentTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    private Task $task;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'ATT-1',
            'name' => 'Proyecto con archivos',
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

        $this->task = new Task;
        $this->task->fill([
            'project_id' => $this->project->id,
            'name' => 'Con archivos',
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);
        $this->task->save();

        $this->project->refresh();
    }

    #[Test]
    public function a_document_is_stored_and_listed(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.attachments.store', [$this->project, $this->task]), [
                'file' => UploadedFile::fake()->create('requerimientos.pdf', 120, 'application/pdf'),
            ])
            ->assertRedirect();

        $attachment = Attachment::query()->firstOrFail();

        $this->assertSame('requerimientos.pdf', $attachment->original_name);
        Storage::disk('local')->assertExists($attachment->stored_path);
    }

    /**
     * El nombre en disco no guarda relación con el que trae el archivo. Guardar
     * el original como ruta permite escribir fuera de la carpeta con «../».
     */
    #[Test]
    public function the_stored_name_is_generated_and_not_the_original(): void
    {
        $this->actingAs($this->manager)->post(route('projects.attachments.store', [$this->project, $this->task]), [
            'file' => UploadedFile::fake()->create('../../etc/passwd.pdf', 10, 'application/pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();

        $this->assertStringNotContainsString('..', $attachment->stored_path);
        $this->assertStringNotContainsString('passwd', $attachment->stored_path);
        $this->assertStringStartsWith("attachments/{$this->project->id}/", $attachment->stored_path);
    }

    /**
     * Lista blanca y no lista negra: una lista negra siempre olvida algo.
     */
    #[Test]
    public function an_executable_extension_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.attachments.store', [$this->project, $this->task]), [
                'file' => UploadedFile::fake()->create('shell.php', 5, 'application/x-php'),
            ])
            ->assertSessionHasErrors('file');

        $this->assertSame(0, Attachment::query()->count());
    }

    #[Test]
    public function a_file_over_the_limit_is_refused(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.attachments.store', [$this->project, $this->task]), [
                'file' => UploadedFile::fake()->create('enorme.pdf', 30000, 'application/pdf'),
            ])
            ->assertSessionHasErrors('file');
    }

    #[Test]
    public function a_member_can_download_it(): void
    {
        $this->actingAs($this->manager)->post(route('projects.attachments.store', [$this->project, $this->task]), [
            'file' => UploadedFile::fake()->create('plan.pdf', 10, 'application/pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();

        $this->actingAs($this->manager)
            ->get(route('projects.attachments.download', [$this->project, $attachment]))
            ->assertOk()
            ->assertDownload('plan.pdf');
    }

    /**
     * Un archivo alcanzable por su dirección es un archivo que cualquiera con el
     * enlace puede abrir, tenga acceso al proyecto o no.
     */
    #[Test]
    public function someone_outside_the_project_cannot_download_it(): void
    {
        $this->actingAs($this->manager)->post(route('projects.attachments.store', [$this->project, $this->task]), [
            'file' => UploadedFile::fake()->create('confidencial.pdf', 10, 'application/pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();

        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::VIEWER)->value('id'));

        $this->actingAs($outsider)
            ->get(route('projects.attachments.download', [$this->project, $attachment]))
            ->assertForbidden();
    }

    #[Test]
    public function deleting_removes_the_file_from_disk(): void
    {
        $this->actingAs($this->manager)->post(route('projects.attachments.store', [$this->project, $this->task]), [
            'file' => UploadedFile::fake()->create('temporal.pdf', 10, 'application/pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();
        $path = $attachment->stored_path;

        $this->actingAs($this->manager)
            ->delete(route('projects.attachments.destroy', [$this->project, $attachment]))
            ->assertRedirect();

        Storage::disk('local')->assertMissing($path);
        $this->assertSoftDeleted($attachment);
    }

    #[Test]
    public function an_attachment_from_another_project_is_not_reachable(): void
    {
        $this->actingAs($this->manager)->post(route('projects.attachments.store', [$this->project, $this->task]), [
            'file' => UploadedFile::fake()->create('mio.pdf', 10, 'application/pdf'),
        ]);

        $attachment = Attachment::query()->firstOrFail();

        $other = Project::query()->create(['code' => 'OTRO-A', 'name' => 'Otro', 'owner_id' => $this->manager->id]);
        $other->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        $this->actingAs($this->manager)
            ->get(route('projects.attachments.download', [$other, $attachment]))
            ->assertNotFound();
    }
}
