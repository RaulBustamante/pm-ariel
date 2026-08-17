<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\ProjectFinding;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Initiation\InitiationStep;
use App\Support\Reporting\FindingDigest;
use App\Support\Reporting\ProjectDashboard;
use App\Support\Reporting\ProjectReportData;
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

        // Los datos los arma la misma clase que usa el controlador. Copiarlos
        // aquí a mano hacía que la prueba se rompiera por llaves faltantes cada
        // vez que el reporte crecía, y —peor— que revisara una versión que ya no
        // era la que se manda a dirección.
        $html = view(
            'reports.project-pdf',
            app(ProjectReportData::class)->for($this->project),
        )->render();

        $this->assertStringContainsString('Levantamiento de requerimientos', $html);
        $this->assertStringContainsString('REP-1', $html);
        $this->assertStringContainsString('Duele el cierre mensual.', $html);
        // El resumen de arriba: quien recibe esto mira los números y decide si
        // le preocupa antes de leer nada.
        $this->assertStringContainsString(__('reports.kpi_progress'), $html);
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

    /**
     * dompdf **ignora en silencio** un `<svg>` escrito dentro del HTML: la hoja
     * sale en blanco y el archivo se genera sin un solo error. Por eso el
     * diagrama se incrusta como imagen `data:`, y por eso esto se comprueba
     * contando lo que de verdad quedó dibujado en el PDF en vez de buscar la
     * etiqueta en el HTML —que estaba ahí incluso cuando no se dibujaba nada.
     */
    #[Test]
    public function the_complete_pdf_really_draws_the_gantt(): void
    {
        $this->task('Levantamiento de requerimientos');
        $this->task('Construccion');

        $simple = $this->actingAs($this->manager)
            ->get(route('projects.reports.pdf', $this->project))->getContent() ?: '';

        $completo = $this->actingAs($this->manager)
            ->get(route('projects.reports.complete', $this->project))->getContent() ?: '';

        $this->assertStringStartsWith('%PDF-', $completo);
        $this->assertGreaterThan(
            $this->rectanglesDrawnIn($simple),
            $this->rectanglesDrawnIn($completo),
            'El PDF completo no dibujó ni un rectángulo más que el simple: el diagrama no salió.',
        );
    }

    #[Test]
    public function the_complete_pdf_is_reachable_from_the_dashboard(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk()
            ->assertSee(route('projects.reports.complete', $this->project), escape: false);
    }

    /**
     * Veinticinco avisos de la misma regla salían como veinticinco párrafos
     * idénticos, cada uno con su explicación completa repetida: tres páginas
     * para un solo hecho. Un director no lee eso, lo cierra.
     */
    #[Test]
    public function repeated_findings_collapse_into_one_line(): void
    {
        $digest = (new FindingDigest)->group(collect([
            new ProjectFinding(['rule' => 'task.critical_without_owner', 'severity' => 'warning', 'message' => 'a', 'why' => 'porque']),
            new ProjectFinding(['rule' => 'task.critical_without_owner', 'severity' => 'warning', 'message' => 'b', 'why' => 'porque']),
            new ProjectFinding(['rule' => 'task.critical_without_owner', 'severity' => 'warning', 'message' => 'c', 'why' => 'porque']),
        ]));

        $this->assertCount(1, $digest);
        $this->assertSame(3, $digest[0]['count']);
    }

    /**
     * Lo que amenaza la entrega va primero. Un documento que empieza por lo
     * menor entrena a quien lo recibe a hojearlo.
     */
    #[Test]
    public function the_gravest_finding_leads_the_report(): void
    {
        $digest = (new FindingDigest)->group(collect([
            new ProjectFinding(['rule' => 'task.critical_without_owner', 'severity' => ProjectFinding::SEVERITY_WARNING, 'message' => 'a', 'why' => 'x']),
            new ProjectFinding(['rule' => 'resource.overallocated', 'severity' => ProjectFinding::SEVERITY_CRITICAL, 'message' => 'b', 'why' => 'y']),
        ]));

        $this->assertSame(ProjectFinding::SEVERITY_CRITICAL, $digest[0]['severity']);
    }

    /** Cuenta los rellenos de rectángulo dentro de los flujos del PDF. */
    private function rectanglesDrawnIn(string $pdf): int
    {
        $total = 0;

        if (preg_match_all('/stream(.*?)endstream/s', $pdf, $matches)) {
            foreach ($matches[1] as $chunk) {
                $raw = @gzuncompress(ltrim($chunk, '

')) ?: $chunk;
                $total += substr_count($raw, ' re');
            }
        }

        return $total;
    }

    /**
     * dompdf fija la orientación **una sola vez para todo el documento**, así
     * que no existe la hoja apaisada intercalada. El diagrama va girado 90°
     * sobre la hoja vertical y se lee volteando el papel.
     *
     * Lo que se comprueba es que quepa: girado, el alto natural del dibujo pasa
     * a ser el ancho que ocupa en la hoja, y si alguien sube los renglones por
     * página el diagrama se sale por el canto sin que nada falle.
     */
    #[Test]
    public function the_gantt_sheet_is_rotated_and_fits_the_page(): void
    {
        foreach (range(1, 30) as $index) {
            $this->task("Tarea {$index}");
        }

        $data = app(ProjectReportData::class)->for($this->project, complete: true);

        $svg = base64_decode(substr(
            (string) $data['ganttImages'][0],
            strlen('data:image/svg+xml;base64,'),
        ), true);

        $this->assertIsString($svg);
        $this->assertStringContainsString('rotate(90)', $svg, 'El diagrama del PDF dejó de ir girado.');

        preg_match('/<svg[^>]*width="([\d.]+)"[^>]*height="([\d.]+)"/', $svg, $size);

        $width = (float) $size[1];
        $height = (float) $size[2];

        $this->assertLessThan($height, $width, 'Girado, el dibujo tiene que quedar más alto que ancho.');

        // Carta vertical menos los márgenes de la plantilla (16 mm y 22/20 mm),
        // en píxeles a 96 ppp, que es como dompdf los convierte.
        $this->assertLessThanOrEqual(695, $width, 'El diagrama girado se sale por el canto de la hoja.');
        $this->assertLessThanOrEqual(897, $height, 'El diagrama girado no cabe a lo alto de la hoja.');
    }

    /**
     * Girado el dibujo, una columna de nombres en HTML al lado dejaría de
     * alinearse con sus barras: tienen que ir dentro del SVG.
     */
    #[Test]
    public function the_rotated_sheet_carries_the_task_names_inside_the_drawing(): void
    {
        // Corto a propósito: los nombres se recortan a lo que cabe en la
        // columna, y la prueba no debe depender de dónde queda ese corte.
        $this->task('Levantamiento');

        $data = app(ProjectReportData::class)->for($this->project, complete: true);

        $svg = base64_decode(substr(
            (string) $data['ganttImages'][0],
            strlen('data:image/svg+xml;base64,'),
        ), true);

        $this->assertStringContainsString('Levantamiento', (string) $svg);
    }
}
