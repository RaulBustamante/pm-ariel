<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\Import\TaskImporter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El importador es la puerta de entrada: casi todos los proyectos de Ariel ya
 * viven en Excel, y pedir que se recapturen sesenta renglones a mano es la forma
 * más segura de que nadie use el sistema.
 */
final class TaskImportTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'IMP-1',
            'name' => 'Proyecto importado',
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

    private function csv(): string
    {
        return implode("\n", [
            'Nombre,Duracion,Nivel,Depende de',
            'Análisis,0,0,',
            'Entrevistas,3d,1,',
            'Documento,4d,1,2',
            'Construcción,0,0,',
            'Desarrollo,5d,1,3',
        ]);
    }

    // ------------------------------------------------------- Vista previa

    #[Test]
    public function the_preview_reads_the_file_without_writing_anything(): void
    {
        $result = app(TaskImporter::class)->forProject($this->project)->preview($this->csv());

        $this->assertCount(5, $result['rows']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(0, Task::query()->where('project_id', $this->project->id)->count());
    }

    /**
     * Excel en español exporta con punto y coma. Culpar al usuario por eso sería
     * culparlo por su configuración regional.
     */
    #[Test]
    public function semicolons_work_the_same_as_commas(): void
    {
        $result = app(TaskImporter::class)->forProject($this->project)->preview("Nombre;Duracion;Nivel\nUna;2d;0\nOtra;3d;0");

        $this->assertCount(2, $result['rows']);
        $this->assertSame([], $result['errors']);
    }

    #[Test]
    public function english_headers_are_understood_too(): void
    {
        $result = app(TaskImporter::class)->forProject($this->project)->preview("Name,Duration,Level\nOne,2d,0");

        $this->assertCount(1, $result['rows']);
    }

    #[Test]
    public function a_file_without_a_name_column_is_refused(): void
    {
        $result = app(TaskImporter::class)->forProject($this->project)->preview("Duracion,Nivel\n2d,0");

        $this->assertSame([], $result['rows']);
        $this->assertSame([__('import.no_name_column')], $result['errors']);
    }

    /**
     * Un renglón malo no tumba el archivo completo: se salta y se dice cuál.
     * Rechazar sesenta tareas por una celda mal escrita es desproporcionado.
     */
    #[Test]
    public function a_bad_row_is_skipped_and_reported_without_losing_the_rest(): void
    {
        $result = app(TaskImporter::class)->forProject($this->project)->preview(implode("\n", [
            'Nombre,Duracion,Nivel',
            'Buena,2d,0',
            'Mala,ayer por la tarde,0',
            'Otra buena,3d,0',
        ]));

        $this->assertCount(2, $result['rows']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('3', $result['errors'][0]);
    }

    // ------------------------------------------------------- Importación

    #[Test]
    public function the_import_creates_the_tasks_with_their_hierarchy(): void
    {
        $rows = app(TaskImporter::class)->forProject($this->project)->preview($this->csv())['rows'];

        app(TaskImporter::class)->forProject($this->project)->import($this->project, $rows);

        $tasks = Task::query()->where('project_id', $this->project->id)->orderBy('sort_order')->get();

        $this->assertCount(5, $tasks);

        $analisis = $tasks->firstWhere('name', 'Análisis');
        $entrevistas = $tasks->firstWhere('name', 'Entrevistas');
        $desarrollo = $tasks->firstWhere('name', 'Desarrollo');

        $this->assertNull($analisis?->parent_id);
        $this->assertSame($analisis?->id, $entrevistas?->parent_id);
        // «Construcción» abre un nivel 0 nuevo: Desarrollo cuelga de ella,
        // no de Análisis.
        $this->assertSame($tasks->firstWhere('name', 'Construcción')?->id, $desarrollo?->parent_id);
    }

    #[Test]
    public function durations_are_translated(): void
    {
        $rows = app(TaskImporter::class)->forProject($this->project)->preview($this->csv())['rows'];
        app(TaskImporter::class)->forProject($this->project)->import($this->project, $rows);

        $this->assertSame(
            3 * self::DAY,
            Task::query()->where('name', 'Entrevistas')->value('duration_minutes'),
        );
    }

    /**
     * Una fila puede depender de otra que viene más abajo en el archivo, así que
     * las ligas se hacen al final, cuando todas las tareas ya existen.
     */
    #[Test]
    public function dependencies_written_in_the_file_are_linked(): void
    {
        $rows = app(TaskImporter::class)->forProject($this->project)->preview($this->csv())['rows'];
        app(TaskImporter::class)->forProject($this->project)->import($this->project, $rows);

        $documento = Task::query()->where('name', 'Documento')->firstOrFail();

        $this->assertSame(1, TaskDependency::query()->where('successor_id', $documento->id)->count());
    }

    #[Test]
    public function importing_adds_to_what_already_exists(): void
    {
        $existing = new Task;
        $existing->fill([
            'project_id' => $this->project->id,
            'name' => 'Ya estaba',
            'duration_minutes' => self::DAY,
            'sort_order' => 0,
        ]);
        $existing->save();

        $rows = app(TaskImporter::class)->forProject($this->project)->preview($this->csv())['rows'];
        app(TaskImporter::class)->forProject($this->project)->import($this->project, $rows);

        $this->assertSame(6, Task::query()->where('project_id', $this->project->id)->count());
        $this->assertNotSoftDeleted($existing);
    }

    #[Test]
    public function replacing_removes_the_previous_plan(): void
    {
        $existing = new Task;
        $existing->fill([
            'project_id' => $this->project->id,
            'name' => 'Se va',
            'duration_minutes' => self::DAY,
            'sort_order' => 0,
        ]);
        $existing->save();

        $rows = app(TaskImporter::class)->forProject($this->project)->preview($this->csv())['rows'];
        app(TaskImporter::class)->forProject($this->project)->import($this->project, $rows, replace: true);

        $this->assertSoftDeleted($existing);
        $this->assertSame(5, Task::query()->where('project_id', $this->project->id)->count());
    }

    // ------------------------------------------------------------ Pantallas

    #[Test]
    public function the_preview_screen_shows_what_would_be_created(): void
    {
        $file = UploadedFile::fake()->createWithContent('plan.csv', $this->csv());

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.import.preview', $this->project), ['file' => $file])
            ->assertOk()
            ->assertSee('Entrevistas')
            ->assertSee(__('import.preview_title', ['count' => 5]));

        $this->assertSame(0, Task::query()->where('project_id', $this->project->id)->count());
    }

    #[Test]
    public function confirming_the_import_creates_the_tasks_and_recalculates(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.tasks.import.store', $this->project), ['payload' => $this->csv()])
            ->assertRedirect(route('projects.tasks.index', $this->project));

        $entrevistas = Task::query()->where('name', 'Entrevistas')->firstOrFail();

        $this->assertNotNull($entrevistas->early_start);
        $this->assertSame('1.1', $entrevistas->wbs_code);
    }

    #[Test]
    public function someone_without_write_access_cannot_import(): void
    {
        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($outsider)
            ->post(route('projects.tasks.import.store', $this->project), ['payload' => $this->csv()])
            ->assertForbidden();
    }
}
