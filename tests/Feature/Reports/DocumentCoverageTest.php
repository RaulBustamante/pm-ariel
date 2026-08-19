<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Support\Documents\DocumentCoverage;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Qué documentos deberían existir ya, según en qué fase va el proyecto.
 *
 * El tablero dice qué existe, y desde que el catálogo está completo eso es
 * siempre setenta. La pregunta que queda es la del proyecto: **de los que ya se
 * le debieron haber emitido, cuáles faltan**.
 *
 * Lo que estas pruebas cuidan es que el aviso **no se encienda antes de tiempo**.
 * Reclamarle el informe de cierre a un proyecto que arranca lo deja encendido
 * desde el primer día, y un aviso siempre encendido deja de leerse en una
 * semana — momento en el que también se deja de leer cuando sí importa.
 */
final class DocumentCoverageTest extends TestCase
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
            'code' => 'FAS-1',
            'name' => 'Proyecto por fases',
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

    private function task(string $name, int $percent = 0, int $days = 2): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $days * self::DAY,
            'percent_complete' => $percent,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
        ]);
        $task->save();

        return $task;
    }

    /**
     * La fase sale del avance y **no se captura**. Un campo de fase es algo que
     * alguien tiene que acordarse de mover, y en cuanto se queda atrás miente en
     * la dirección más peligrosa: diciendo que todo está en orden.
     */
    #[Test]
    public function the_phase_is_derived_from_progress(): void
    {
        $coverage = app(DocumentCoverage::class);

        $this->assertSame('initiating', $coverage->phaseOf($this->project));

        $task = $this->task('Sola');

        $task->update(['percent_complete' => 5]);
        $this->assertSame('planning', $coverage->phaseOf($this->project->refresh()));

        $task->update(['percent_complete' => 50]);
        $this->assertSame('executing', $coverage->phaseOf($this->project->refresh()));

        $task->update(['percent_complete' => 90]);
        $this->assertSame('monitoring', $coverage->phaseOf($this->project->refresh()));

        $task->update(['percent_complete' => 100]);
        $this->assertSame('closing', $coverage->phaseOf($this->project->refresh()));
    }

    /** Y se pondera por duración, como en todas partes. */
    #[Test]
    public function the_phase_weighs_by_duration(): void
    {
        $this->task('Corta', 100, 1);
        $this->task('Larga', 0, 99);

        // Promediando a secas serían 50 % —ejecución—; ponderado es 1 %.
        $this->assertSame('planning', app(DocumentCoverage::class)->phaseOf($this->project->refresh()));
    }

    /**
     * **El aviso no se enciende antes de tiempo.** Un proyecto que arranca no
     * debe nada todavía.
     */
    #[Test]
    public function a_project_that_just_started_owes_nothing(): void
    {
        $result = app(DocumentCoverage::class)->for($this->project);

        $this->assertSame('initiating', $result['phase']);
        // Solo lo de inicio cuenta como faltante: el acta y los interesados.
        $this->assertSame(2, $result['missing']);
        $this->assertNotSame([], $result['expected']);
    }

    /** Y uno en ejecución sí debe lo de las fases por las que ya pasó. */
    #[Test]
    public function a_running_project_owes_the_earlier_phases(): void
    {
        $this->task('Sola', 50);

        $result = app(DocumentCoverage::class)->for($this->project->refresh());

        $this->assertSame('executing', $result['phase']);
        // Inicio (2) + planeacion (2) + ejecucion (1), y nada de cierre ni
        // de monitoreo: el proyecto no ha llegado a esas fases.
        $this->assertSame(5, $result['missing']);
    }

    /** Emitir un documento lo descuenta. */
    #[Test]
    public function issuing_a_document_clears_it_from_the_list(): void
    {
        $this->task('Sola');

        $before = app(DocumentCoverage::class)->for($this->project)['missing'];

        $this->actingAs($this->manager)
            ->post(route('projects.documents.issue', [$this->project, 'weekly']))
            ->assertRedirect();

        $after = app(DocumentCoverage::class)->for($this->project->refresh());

        // El corte semanal se archiva como <<informe de estado>>: es el codigo
        // que de verdad escribe DocumentIssueController, no el que uno supondria
        // leyendo el catalogo.
        $issued = collect($after['expected'])->firstWhere('code', 'project_status_report');

        $this->assertTrue($issued['issued']);
        $this->assertLessThanOrEqual($before, $after['missing']);
    }

    #[Test]
    public function the_board_shows_the_phase_and_what_is_missing(): void
    {
        $this->task('Sola', 50);

        $this->actingAs($this->manager)
            ->get(route('projects.documents', $this->project))
            ->assertOk()
            ->assertSee(__('documents.phase_title'))
            ->assertSee(__('documents.group_executing'))
            ->assertSee(__('documents.phase_help'));
    }

    #[Test]
    public function the_phase_texts_read_in_both_languages(): void
    {
        foreach (['phase_title', 'phase_now', 'phase_missing', 'phase_complete', 'phase_help'] as $key) {
            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "documents.{$key}",
                    __("documents.{$key}", [], $locale),
                    "Falta «{$key}» en {$locale}.",
                );
            }
        }
    }
}
