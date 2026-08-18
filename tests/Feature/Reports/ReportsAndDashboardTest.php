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
use App\Support\Reporting\WeeklyReport;
use Carbon\CarbonImmutable;
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
     *
     * La razón que toca depende del día en que se corra la prueba —el proyecto
     * arranca el lunes de esta semana, así que el lunes todavía no va tarde y el
     * jueves sí—, y por eso **no se busca una frase**: se comprueba que salga
     * alguna de las que el tablero sabe dar. Enumerar solo dos de las cuatro es
     * lo que hacía que esta prueba pasara en fin de semana y fallara a media
     * semana, sin que el semáforo hubiera dejado de explicarse ni un día.
     */
    #[Test]
    public function the_light_explains_itself(): void
    {
        $this->task('Sin responsable');
        app(ProjectAdvisor::class)->analyze($this->project->refresh());

        $kpis = app(ProjectDashboard::class)->kpis($this->project->refresh());

        $response = $this->actingAs($this->manager)
            ->get(route('projects.dashboard', $this->project))
            ->assertOk();

        $content = $response->getContent() ?: '';

        // Las cuatro razones que el tablero sabe dar, con sus cifras reales:
        // así se comprueba la frase completa y no un pedazo que podría venir de
        // cualquier otra parte de la pantalla.
        $reasons = [
            __('dashboard.why_green'),
            __('dashboard.why_amber_generic'),
            __('dashboard.why_overdue', ['count' => $kpis['overdue']]),
            __('dashboard.why_behind', [
                'progress' => $kpis['progress'],
                'elapsed' => $kpis['elapsed_percent'],
            ]),
            'Sin responsable',
        ];

        // Alguna razón concreta, no solo el color.
        $this->assertTrue(
            array_filter($reasons, fn (string $reason): bool => str_contains($content, e($reason))) !== [],
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

        // Y cabe **entero**: un diagrama repartido en varias hojas deja de
        // servir para lo que uno lo abre, que es ver el proyecto de un golpe.
        $this->assertCount(1, $data['ganttImages']);
    }

    /**
     * Se comprime el renglón para que quepa, pero no sin límite: por debajo de
     * nueve píxeles el nombre deja de leerse impreso, y una hoja ilegible es
     * peor que dos legibles.
     */
    #[Test]
    public function a_project_too_big_to_fit_goes_back_to_several_sheets(): void
    {
        foreach (range(1, 90) as $index) {
            $this->task("Tarea {$index}");
        }

        $data = app(ProjectReportData::class)->for($this->project, complete: true);

        $this->assertGreaterThan(
            1,
            count($data['ganttImages']),
            'Con noventa tareas el renglón habría quedado por debajo del mínimo legible.',
        );
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

    /**
     * El documento sale en el idioma de quien lo genera.
     *
     * Ya funcionaba —los reportes usan las mismas claves que la interfaz y el
     * middleware fija el idioma desde la preferencia—, pero no había nada que lo
     * sostuviera. Un solo texto escrito a mano dentro de una plantilla y la
     * mitad del PDF queda en un idioma y la mitad en otro sin que falle nada.
     *
     * Se revisa el HTML de la plantilla y no los bytes del PDF: es lo mismo que
     * dompdf va a maquetar, y aquí sí se puede leer qué dice.
     */
    #[Test]
    public function every_document_follows_the_language_of_whoever_generates_it(): void
    {
        $this->task('Levantamiento');

        $views = [
            'reports.project-pdf' => fn (): array => app(ProjectReportData::class)->for($this->project),
            'reports.weekly-pdf' => fn (): array => [
                ...app(WeeklyReport::class)->for($this->project),
                'project' => $this->project,
                'digest' => [],
                'generatedAt' => now(),
                'focusChart' => null,
            ],
        ];

        foreach ($views as $view => $data) {
            app()->setLocale('es');
            $spanish = view($view, $data())->render();

            app()->setLocale('en');
            $english = view($view, $data())->render();

            $this->assertStringContainsString(__('reports.kpi_progress', [], 'es'), $spanish);
            $this->assertStringContainsString(__('reports.kpi_progress', [], 'en'), $english);

            $this->assertStringNotContainsString(
                __('reports.kpi_progress', [], 'es'),
                $english,
                "El documento {$view} dejó texto en español dentro de la versión en inglés.",
            );
        }

        app()->setLocale('es');
    }

    /**
     * El corte se manda al cierre del viernes. A esa hora, una tarea que vencía
     * hoy y sigue abierta va atrasada — comparando contra el inicio del día
     * quedaba fuera, y el documento salía diciendo que no hay nada tarde justo
     * el día en que se acaba de acumular.
     */
    #[Test]
    public function a_task_due_today_counts_as_late_at_the_close_of_the_day(): void
    {
        $task = $this->task('Revision con el area');
        $task->forceFill([
            'early_start' => now()->startOfDay()->setTime(9, 0),
            'early_finish' => now()->startOfDay()->setTime(18, 0),
            'percent_complete' => 40,
        ])->save();

        $report = app(WeeklyReport::class);

        $morning = $report->for($this->project, CarbonImmutable::now()->startOfDay()->setTime(9, 0));
        $this->assertFalse(
            $morning['late']->contains('id', $task->id),
            'A las nueve de la mañana una tarea que termina hoy a las seis todavía no está tarde.',
        );

        $closing = $report->for($this->project, CarbonImmutable::now()->startOfDay()->setTime(19, 0));
        $this->assertTrue(
            $closing['late']->contains('id', $task->id),
            'Al cierre del día, una tarea que vencía hoy y sigue abierta sí está tarde.',
        );
    }

    /**
     * Las cuatro listas no se traslapan. Un renglón que sale dos veces obliga a
     * quien lee a compararlas, y entonces deja de ser un resumen.
     */
    #[Test]
    public function no_task_appears_in_two_lists_of_the_weekly_report(): void
    {
        foreach (range(1, 12) as $index) {
            $this->task("Tarea {$index}");
        }

        $week = app(WeeklyReport::class)->for($this->project);

        $ids = collect([$week['closed'], $week['late'], $week['doing'], $week['next']])
            ->flatMap(fn ($list) => $list->pluck('id'))
            ->all();

        $this->assertSame(
            count($ids),
            count(array_unique($ids)),
            'Una misma tarea salió en dos de las cuatro listas del corte semanal.',
        );
    }

    /**
     * Las columnas existían desde la Etapa 3 y nadie las escribía. Sin ellas el
     * corte no puede distinguir lo que se cerró de verdad de lo que estaba
     * planeado cerrarse.
     */
    #[Test]
    public function recording_progress_stamps_the_real_dates(): void
    {
        $task = $this->task('Levantamiento');

        $this->assertNull($task->actual_start);

        $task->update(['percent_complete' => 50]);
        $this->assertNotNull($task->refresh()->actual_start);
        $this->assertNull($task->actual_finish);

        $task->update(['percent_complete' => 100]);
        $this->assertNotNull($task->refresh()->actual_finish);

        // Reabrirla borra el cierre: dejarlo puesto haría que el corte de la
        // semana que entra siguiera presumiendo como terminado algo que no lo está.
        $task->update(['percent_complete' => 70]);
        $this->assertNull($task->refresh()->actual_finish);
    }
}
