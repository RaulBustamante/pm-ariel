<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\BaselineManager;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Costing\EarnedValue;
use App\Support\Documents\DocumentCatalogue;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Valor ganado.
 *
 * Lo que estas pruebas cuidan es que los índices **puedan dar mala noticia**. La
 * tentación al construir esto es deducir el costo real del avance —«si va al
 * 40 %, se habrá gastado el 40 %»—, y eso da un CPI de 1.00 en todos los
 * proyectos para siempre. Un indicador que nunca puede salir mal no es un
 * indicador: es un adorno que hace creer que el costo está bajo control.
 */
final class EarnedValueTest extends TestCase
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
            'code' => 'EVM-1',
            'name' => 'Proyecto medido',
            'owner_id' => $this->manager->id,
            'planned_start' => Carbon::parse('2026-03-02 09:00'),
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
     * Dos tareas de dos días, con costo fijo, y una línea base encima. Es el
     * caso mínimo que se puede resolver a mano y contra el que se comprueban las
     * fórmulas.
     */
    private function twoTasks(float $cost = 1000.0): void
    {
        foreach (['Primera', 'Segunda'] as $index => $name) {
            $task = new Task;
            $task->fill([
                'project_id' => $this->project->id,
                'name' => $name,
                'duration_minutes' => 2 * self::DAY,
                'cost' => $cost,
                'sort_order' => $index,
            ]);
            $task->save();
        }

        app(ProjectScheduler::class)->reschedule($this->project->refresh());
        app(BaselineManager::class)->capture($this->project, 'Aprobada', 'Al arrancar.');
    }

    /**
     * La prueba que sostiene todo lo demás: con el proyecto terminado a la
     * mitad y el gasto capturado, los tres números salen como se resuelven a
     * mano.
     */
    #[Test]
    public function the_three_figures_come_out_as_they_do_on_paper(): void
    {
        $this->twoTasks();

        // La primera terminada y costó lo presupuestado; la segunda sin arrancar.
        $first = Task::query()->where('name', 'Primera')->firstOrFail();
        $first->update(['percent_complete' => 100, 'actual_cost' => 1000]);

        // Fecha de corte muy posterior: el plan entero debería estar ganado.
        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertSame(2000.0, $evm['bac']);
        $this->assertSame(2000.0, $evm['pv']);   // las dos debieron terminar
        $this->assertSame(1000.0, $evm['ev']);   // solo una se hizo
        $this->assertSame(1000.0, $evm['ac']);
        $this->assertSame(1.0, $evm['cpi']);     // costó lo que valía
        $this->assertSame(0.5, $evm['spi']);     // se hizo la mitad de lo debido
        $this->assertSame(0.0, $evm['cv']);
        $this->assertSame(-1000.0, $evm['sv']);
    }

    /**
     * **El índice de costo puede dar mala noticia.** Si esto deja de fallar
     * cuando se gasta de más, el indicador se volvió un adorno.
     */
    #[Test]
    public function overspending_shows_up_as_a_cost_index_below_one(): void
    {
        $this->twoTasks();

        $first = Task::query()->where('name', 'Primera')->firstOrFail();
        $first->update(['percent_complete' => 100, 'actual_cost' => 1500]);

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertNotNull($evm['cpi']);
        $this->assertLessThan(1.0, $evm['cpi']);
        $this->assertSame(-500.0, $evm['cv']);

        // Y el pronóstico lo lleva hasta el final: al ritmo de hoy, el proyecto
        // de 2,000 acaba en 3,000 y se pasa por 1,000.
        $this->assertSame(3000.0, $evm['eac']);
        $this->assertSame(-1000.0, $evm['vac']);
        $this->assertSame(1500.0, $evm['etc']);
    }

    /**
     * Sin costo real capturado, **los índices que dependen de él no se
     * inventan**. Un CPI deducido del avance daría 1.00 siempre.
     */
    #[Test]
    public function without_captured_actuals_the_cost_indices_are_not_computed(): void
    {
        $this->twoTasks();

        Task::query()->where('name', 'Primera')->firstOrFail()->update(['percent_complete' => 100]);

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertNull($evm['ac']);
        $this->assertNull($evm['cpi']);
        $this->assertNull($evm['cv']);
        $this->assertNull($evm['eac']);
        $this->assertNull($evm['etc']);
        $this->assertNull($evm['vac']);
        $this->assertNull($evm['tcpi']);

        // El cronograma sí: no depende del gasto.
        $this->assertNotNull($evm['spi']);
        $this->assertSame(1, $evm['missing_actuals']);
    }

    /**
     * Con el gasto capturado a medias tampoco se calcula. Con la mitad del
     * gasto, el índice de costo saldría espléndido por la sencilla razón de que
     * falta la otra mitad.
     */
    #[Test]
    public function half_captured_actuals_are_not_good_enough(): void
    {
        $this->twoTasks();

        Task::query()->where('name', 'Primera')->firstOrFail()
            ->update(['percent_complete' => 100, 'actual_cost' => 1000]);
        Task::query()->where('name', 'Segunda')->firstOrFail()
            ->update(['percent_complete' => 50]);

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertSame(2, $evm['started_tasks']);
        $this->assertSame(1, $evm['missing_actuals']);
        $this->assertNull($evm['cpi']);
    }

    /**
     * Solo se echa en falta el costo de lo que **ya arrancó**. Pedirlo de una
     * tarea que no ha empezado convertiría el aviso en ruido permanente.
     */
    #[Test]
    public function tasks_that_have_not_started_are_not_missing_anything(): void
    {
        $this->twoTasks();

        Task::query()->where('name', 'Primera')->firstOrFail()
            ->update(['percent_complete' => 100, 'actual_cost' => 900]);

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertSame(1, $evm['started_tasks']);
        $this->assertSame(0, $evm['missing_actuals']);
        $this->assertSame(900.0, $evm['ac']);
    }

    /**
     * El valor planeado se mide contra la **línea base**, no contra el plan de
     * hoy. Si se midiera contra el plan vigente, reprogramar borraría el atraso
     * y el SPI daría 1.00 para siempre.
     */
    #[Test]
    public function the_planned_value_is_measured_against_the_baseline(): void
    {
        $this->twoTasks();

        // Se alarga el plan después de congelar: la línea base sigue diciendo
        // que a fin de año todo debía estar hecho.
        Task::query()->where('name', 'Segunda')->firstOrFail()
            ->update(['duration_minutes' => 200 * self::DAY]);

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertTrue($evm['has_baseline']);
        $this->assertSame(2000.0, $evm['pv']);
        $this->assertSame(0.0, $evm['ev']);
        $this->assertSame(-2000.0, $evm['sv']);
    }

    /**
     * Antes de que arranque nada, el valor planeado es cero: nada se debía
     * todavía. Es lo que impide que un proyecto que aún no empieza aparezca
     * atrasado el primer día.
     */
    #[Test]
    public function nothing_is_owed_before_the_project_starts(): void
    {
        $this->twoTasks();

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-01-01'));

        $this->assertSame(0.0, $evm['pv']);
        $this->assertSame(0.0, $evm['ev']);
        $this->assertSame(0.0, $evm['sv']);
        $this->assertSame(0, $evm['started_tasks']);
    }

    /** Una tarea que nació después de la línea base sí cuenta en el presupuesto. */
    #[Test]
    public function work_added_after_the_baseline_still_counts(): void
    {
        $this->twoTasks();

        $extra = new Task;
        $extra->fill([
            'project_id' => $this->project->id,
            'name' => 'La que se metió a media obra',
            'duration_minutes' => self::DAY,
            'cost' => 500,
            'sort_order' => 9,
        ]);
        $extra->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertSame(2500.0, $evm['bac']);
    }

    /** Un proyecto sin línea base lo dice, en vez de callarlo. */
    #[Test]
    public function a_project_without_a_baseline_says_so(): void
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => 'Sola',
            'duration_minutes' => self::DAY,
            'cost' => 100,
            'sort_order' => 0,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $evm = app(EarnedValue::class)->for($this->project, Carbon::parse('2026-12-31'));

        $this->assertFalse($evm['has_baseline']);
        $this->assertNull($evm['baseline_name']);
        $this->assertSame(100.0, $evm['bac']);
    }

    #[Test]
    public function the_screen_opens_and_shows_the_three_figures(): void
    {
        $this->twoTasks();

        $this->actingAs($this->manager)
            ->get(route('projects.earned-value', $this->project))
            ->assertOk()
            ->assertSee(__('evm.pv'))
            ->assertSee(__('evm.ev'))
            ->assertSee(__('evm.ac'));
    }

    /** La fecha de corte se puede mover: los índices de hoy no explican ayer. */
    #[Test]
    public function the_status_date_can_be_moved(): void
    {
        $this->twoTasks();

        $this->actingAs($this->manager)
            ->get(route('projects.earned-value', [$this->project, 'at' => '2026-01-01']))
            ->assertOk()
            ->assertSee('2026-01-01');
    }

    /** Una fecha ilegible cae en hoy, no revienta. */
    #[Test]
    public function an_unreadable_status_date_falls_back_to_today(): void
    {
        $this->twoTasks();

        $this->actingAs($this->manager)
            ->get(route('projects.earned-value', [$this->project, 'at' => 'el-martes-pasado']))
            ->assertOk();
    }

    #[Test]
    public function the_report_comes_out_as_a_pdf(): void
    {
        $this->twoTasks();

        $response = $this->actingAs($this->manager)
            ->get(route('projects.earned-value.pdf', $this->project))
            ->assertOk();

        $this->assertStringStartsWith('%PDF-', $response->getContent() ?: '');
    }

    /** El costo real se captura en la tarea, junto al avance. */
    #[Test]
    public function the_actual_cost_is_captured_on_the_task(): void
    {
        $this->twoTasks();

        $task = Task::query()->where('name', 'Primera')->firstOrFail();

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => $task->name,
                'duration' => '2d',
                'percent_complete' => 100,
                'actual_cost' => 1234.56,
            ])
            ->assertRedirect();

        $this->assertSame('1234.56', $task->refresh()->actual_cost);
    }

    /**
     * Vaciar el campo lo deja en nulo, no en cero. «Todavía no lo sé» y «salió
     * gratis» son dos cosas distintas, y de esa diferencia depende que el
     * informe sepa si puede calcular el índice de costo.
     */
    #[Test]
    public function clearing_the_actual_cost_leaves_it_unknown_not_zero(): void
    {
        $this->twoTasks();

        $task = Task::query()->where('name', 'Primera')->firstOrFail();
        $task->update(['actual_cost' => 500]);

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => $task->name,
                'duration' => '2d',
                'actual_cost' => '',
            ]);

        $this->assertNull($task->refresh()->actual_cost);
    }

    /** Los dos documentos del catálogo llevan a esta pantalla. */
    #[Test]
    public function the_board_links_the_earned_value_documents(): void
    {
        $groups = app(DocumentCatalogue::class)->forProject($this->project);

        $found = [];

        foreach ($groups as $documents) {
            foreach ($documents as $document) {
                if (in_array($document['code'], ['earned_value_report', 'cost_forecasts'], true)) {
                    $this->assertSame(DocumentCatalogue::STATE_READY, $document['state']);
                    $this->assertNotNull($document['url'], "«{$document['code']}» no lleva a ninguna pantalla.");
                    $found[] = $document['code'];
                }
            }
        }

        $this->assertCount(2, $found);
    }

    #[Test]
    public function someone_outside_the_project_cannot_see_the_numbers(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->get(route('projects.earned-value', $this->project))
            ->assertForbidden();
    }
}
