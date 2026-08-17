<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\ProjectScheduler;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Initiation\InitiationStep;
use App\Support\Reporting\ProjectDashboard;
use App\Support\Scheduling\DurationParser;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ReportsAndDashboardTest extends TestCase
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
            'code' => 'REP-1',
            'name' => 'Proyecto reportable',
            'owner_id' => $this->manager->id,
            'planned_start' => now()->startOfWeek()->setTime(9, 0),
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

        ProjectCharter::query()->create([
            'project_id' => $this->project->id,
            'problem_statement' => 'Duele el cierre mensual.',
            'objectives' => 'Que deje de doler.',
            'current_step' => InitiationStep::Justification->value,
            'completed_steps' => [],
        ]);

        $this->project->refresh();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(string $name, int $days = 2, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $days * self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return $task->refresh();
    }

    // ------------------------------------------------------------------ PDF

    #[Test]
    public function the_pdf_downloads_and_is_a_real_pdf(): void
    {
        $this->task('Levantamiento');

        $response = $this->actingAs($this->manager)
            ->get(route('projects.reports.pdf', $this->project))
            ->assertOk();

        $this->assertSame('application/pdf', $response->headers->get('content-type'));
        // La firma del formato: si no empieza así, no es un PDF por más que la
        // extensión lo diga.
        $this->assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    #[Test]
    public function the_pdf_carries_the_project_and_its_tasks(): void
    {
        $this->task('Levantamiento de requerimientos');

        $html = view('reports.project-pdf', [
            'project' => $this->project,
            'charter' => $this->project->charter,
            'tasks' => app(TaskOutliner::class)->outline($this->project),
            'durations' => new DurationParser,
            'findings' => collect(),
            'light' => 'green',
            'lastRun' => $this->project->scheduleRuns()->first(),
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Levantamiento de requerimientos', $html);
        $this->assertStringContainsString('REP-1', $html);
        $this->assertStringContainsString('Duele el cierre mensual.', $html);
        // Numeración de página: un reporte largo sin números no se puede citar.
        $this->assertStringContainsString('counter(page)', $html);
    }

    #[Test]
    public function the_gantt_print_page_paginates(): void
    {
        foreach (range(1, 40) as $index) {
            $this->task("Tarea {$index}", 1);
        }

        $this->actingAs($this->manager)
            ->get(route('projects.reports.gantt', $this->project))
            ->assertOk()
            // 40 tareas en bloques de 28: dos hojas.
            ->assertSee('2 '.mb_strtolower(__('reports.pages')));
    }

    // ------------------------------------------------------------------ CSV

    /**
     * Excel en español abre un CSV de comas metiendo todo en la primera
     * columna, y sin BOM se come los acentos. Exportar algo que hay que reparar
     * a mano no es exportar.
     */
    #[Test]
    public function the_csv_opens_clean_in_spanish_excel(): void
    {
        $this->task('Análisis de requerimientos');

        $response = $this->actingAs($this->manager)
            ->get(route('projects.reports.csv', $this->project))
            ->assertOk();

        $body = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $body, 'Falta el BOM.');
        $this->assertStringContainsString(';', $body, 'Falta el punto y coma.');
        $this->assertStringContainsString('Análisis de requerimientos', $body);
    }

    #[Test]
    public function the_csv_keeps_the_hierarchy_as_indentation(): void
    {
        $package = $this->task('Paquete', 1);
        $this->task('Hija', 1, ['parent_id' => $package->id]);

        $body = $this->actingAs($this->manager)
            ->get(route('projects.reports.csv', $this->project))
            ->streamedContent();

        $this->assertStringContainsString('    Hija', $body);
    }

    // ------------------------------------------------------------- Tablero

    #[Test]
    public function the_progress_is_weighted_by_duration(): void
    {
        // Una de un día al 100 % y otra de nueve días en cero. Contar tareas
        // daría 50 %; ponderar por duración da 10 %.
        $this->task('Corta', 1, ['percent_complete' => 100]);
        $this->task('Larga', 9);

        $kpis = app(ProjectDashboard::class)->kpis($this->project->refresh());

        $this->assertSame(10.0, $kpis['progress']);
        $this->assertSame(1, $kpis['done']);
        $this->assertSame(1, $kpis['todo']);
    }

    #[Test]
    public function the_dashboard_renders_with_its_numbers(): void
    {
        $this->task('Levantamiento');
        app(ProjectAdvisor::class)->analyze($this->project->refresh());

        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk()
            ->assertSee(__('dashboard.progress'))
            ->assertSee(__('dashboard.s_curve'));
    }

    /**
     * El semáforo dice por qué está en ese color. Uno que solo se pinta obliga a
     * preguntarle a alguien.
     */
    #[Test]
    public function the_light_explains_itself(): void
    {
        $this->task('Sin responsable');
        app(ProjectAdvisor::class)->analyze($this->project->refresh());

        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk();

        $content = $response->getContent() ?: '';

        // Alguna razón concreta, no solo el color.
        $this->assertTrue(
            str_contains($content, __('dashboard.why_green'))
            || str_contains($content, 'Sin responsable')
            || str_contains($content, __('dashboard.why_amber_generic')),
            'El semáforo debe decir por qué.',
        );
    }

    /**
     * Dibujar la curva real hacia el futuro sería afirmar un avance que todavía
     * no ocurre.
     */
    #[Test]
    public function the_actual_curve_stops_at_today(): void
    {
        $this->task('Larga', 40);

        $curve = app(ProjectDashboard::class)->sCurve($this->project->refresh());

        $this->assertNotEmpty($curve['labels']);
        $this->assertLessThan(count($curve['planned']), count($curve['actual']));
    }

    #[Test]
    public function a_project_without_tasks_does_not_break_the_dashboard(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk()
            ->assertSee(__('dashboard.no_data'));
    }

    #[Test]
    public function someone_outside_the_project_cannot_download_the_reports(): void
    {
        $this->task('Confidencial');

        $outsider = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $outsider->roles()->attach(Role::query()->where('name', Role::VIEWER)->value('id'));

        foreach (['projects.reports.pdf', 'projects.reports.csv', 'projects.dashboard'] as $route) {
            $this->actingAs($outsider)->get(route($route, $this->project))->assertForbidden();
        }
    }
}
