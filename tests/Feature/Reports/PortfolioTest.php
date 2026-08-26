<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\AuditLog;
use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Reporting\Portfolio;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La cartera en el inicio, y quién tocó cada cosa.
 *
 * Lo que cuida la primera mitad es que **los totales salgan de los mismos
 * motores que las pantallas de detalle**. Si la cartera hiciera su propia
 * cuenta, tarde o temprano diría un número distinto del que dice el proyecto, y
 * entonces habría que abrir el proyecto para saber a cuál creerle — que es
 * justo el trabajo que esta pantalla quita.
 */
final class PortfolioTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));
    }

    private function project(string $code, int $tasks = 2, int $percent = 0): Project
    {
        $project = Project::query()->create([
            'code' => $code,
            'name' => "Proyecto {$code}",
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $project->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        for ($i = 0; $i < $tasks; $i++) {
            $task = new Task;
            $task->fill([
                'project_id' => $project->id,
                'name' => "Tarea {$i} de {$code}",
                'duration_minutes' => 2 * self::DAY,
                'cost' => 1000,
                'percent_complete' => $percent,
                'sort_order' => $i,
            ]);
            $task->save();
        }

        app(ProjectScheduler::class)->reschedule($project->refresh());

        return $project->refresh();
    }

    #[Test]
    public function the_home_lists_every_project_on_one_row(): void
    {
        $this->project('CAR-1');
        $this->project('CAR-2');

        $this->actingAs($this->manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('portfolio.title'))
            ->assertSee('CAR-1')
            ->assertSee('CAR-2')
            ->assertSee(__('portfolio.total_projects'));
    }

    /**
     * Lo que peor va, arriba. Ordenar por nombre o por fecha de alta pone en el
     * primer renglón el proyecto que no necesita atención, y quien abre esto lo
     * abre para encontrar el que sí.
     */
    #[Test]
    public function the_worst_project_comes_first(): void
    {
        $healthy = $this->project('SANO', 1, 100);
        $troubled = $this->project('MAL', 1);

        // Una tarea vencida y sin responsable en el que va mal.
        Task::query()->where('project_id', $troubled->id)->update([
            'early_finish' => now()->subMonth(),
            'late_finish' => now()->subMonth(),
        ]);

        $portfolio = app(Portfolio::class)
            ->for(collect([$healthy, $troubled]), withCosts: true);

        $codes = array_map(fn (array $row): string => (string) $row['project']->code, $portfolio['rows']);

        $this->assertSame('MAL', $codes[0], 'El proyecto que necesita atención tiene que salir primero.');
    }

    /** Los totales son la suma de los renglones, no una cuenta aparte. */
    #[Test]
    public function the_totals_add_up_the_rows(): void
    {
        $first = $this->project('SUM-1', 2);
        $second = $this->project('SUM-2', 3);

        $portfolio = app(Portfolio::class)
            ->for(collect([$first, $second]), withCosts: true);

        $this->assertSame(2, $portfolio['totals']['projects']);
        $this->assertSame(5, $portfolio['totals']['tasks']);
        $this->assertSame(
            array_sum(array_map(fn (array $r): float => $r['cost'], $portfolio['rows'])),
            $portfolio['totals']['cost'],
        );
    }

    /**
     * **Sin permiso de costos, la columna no está** — y tampoco se calcula. No
     * es presentación: recorrer las asignaciones de doce proyectos cuesta, y
     * hacerlo para esconder el resultado sería pagar el precio dos veces.
     */
    #[Test]
    public function someone_without_the_costs_permission_sees_no_money(): void
    {
        $project = $this->project('COS-1');

        $reader = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $reader->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));
        $project->members()->attach($reader->id, ['project_role' => Project::ROLE_MEMBER]);

        $response = $this->actingAs($reader)->get(route('dashboard'))->assertOk();

        $content = $response->getContent() ?: '';

        $this->assertStringNotContainsString(__('portfolio.total_cost'), $content);
        $this->assertStringContainsString(__('portfolio.title'), $content);
    }

    #[Test]
    public function the_portfolio_reads_in_both_languages(): void
    {
        foreach (['title', 'help', 'total_projects', 'total_cost', 'weight'] as $key) {
            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "portfolio.{$key}",
                    __("portfolio.{$key}", [], $locale),
                    "Falta «{$key}» en {$locale}.",
                );
            }
        }
    }

    #[Test]
    public function the_home_and_team_view_show_only_the_reporting_line_activities(): void
    {
        $project = $this->project('EQP-1', 0);
        $report = User::factory()->create(['name' => 'Rodrigo Equipo', 'is_active' => true, 'must_change_password' => false]);
        $outsider = User::factory()->create(['name' => 'Persona Externa', 'is_active' => true, 'must_change_password' => false]);

        DB::table('user_hierarchy')->insert([
            'manager_id' => $this->manager->id,
            'subordinate_id' => $report->id,
            'effective_from' => now()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        app(\App\Support\Visibility\VisibilityScope::class)->flush();

        $reportTask = new Task;
        $reportTask->fill([
            'project_id' => $project->id,
            'name' => 'Actividad de Rodrigo',
            'owner_id' => $report->id,
            'duration_minutes' => self::DAY,
            'sort_order' => 1,
        ]);
        $reportTask->forceFill(['early_start' => now(), 'early_finish' => now()->addDay()])->save();

        $outsideTask = new Task;
        $outsideTask->fill([
            'project_id' => $project->id,
            'name' => 'Actividad externa',
            'owner_id' => $outsider->id,
            'duration_minutes' => self::DAY,
            'sort_order' => 2,
        ]);
        $outsideTask->forceFill(['early_start' => now(), 'early_finish' => now()->addDay()])->save();

        $this->actingAs($this->manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('team.dashboard_title'))
            ->assertSee('Actividad de Rodrigo')
            ->assertDontSee('Actividad externa');

        $this->actingAs($this->manager)
            ->get(route('team-activities.index'))
            ->assertOk()
            ->assertSee('Rodrigo Equipo')
            ->assertSee('Actividad de Rodrigo')
            ->assertDontSee('Actividad externa');
    }

    // ------------------------------------------------- Quién tocó qué

    /**
     * **`created_by` y `updated_by` no se llenaban en ninguna tabla.**
     *
     * Cuatro modelos decían en su docblock «lo llena RecordsAudit» y no era
     * cierto: las columnas existían en cinco tablas y nadie las escribía nunca.
     * No fallaba nada — la pantalla de un documento narrativo llevaba desde la
     * Etapa 7 diciendo «Actualizado el 16/08 por —», que se lee como un dato que
     * falta y no como un sistema roto.
     */
    #[Test]
    public function the_team_block_remains_visible_without_reporting_line_activities(): void
    {
        $this->project('EQP-EMPTY', 0);

        $this->actingAs($this->manager)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('team.dashboard_title'))
            ->assertSee(__('team.empty_open'));
    }

    #[Test]
    public function the_system_records_who_created_and_who_touched_each_row(): void
    {
        $project = $this->project('AUT-1');

        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$project, 'scope_management_plan']), [
                'sections' => ['approach' => 'Lo escribió alguien.'],
            ]);

        $document = ProjectDocument::query()->firstOrFail();

        $this->assertSame($this->manager->id, $document->created_by);
        $this->assertSame($this->manager->id, $document->updated_by);
    }

    /** Y quien lo toca después queda como el último, sin borrar al primero. */
    #[Test]
    public function a_second_editor_does_not_erase_the_first(): void
    {
        $project = $this->project('AUT-2');

        $other = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $other->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$project, 'scope_management_plan']), [
                'sections' => ['approach' => 'Primera versión.'],
            ]);

        $this->actingAs($other)
            ->put(route('projects.documents.narrative.update', [$project, 'scope_management_plan']), [
                'sections' => ['approach' => 'Segunda versión.'],
            ]);

        $document = ProjectDocument::query()->firstOrFail();

        $this->assertSame($this->manager->id, $document->created_by);
        $this->assertSame($other->id, $document->updated_by);
    }

    /**
     * Y no ensucia la bitácora: quién tocó algo no es un evento de negocio
     * aparte del cambio que provocó.
     */
    #[Test]
    public function the_authorship_columns_do_not_show_up_as_changes(): void
    {
        $project = $this->project('AUT-3');

        $this->actingAs($this->manager)
            ->put(route('projects.documents.narrative.update', [$project, 'scope_management_plan']), [
                'sections' => ['approach' => 'Una.'],
            ]);

        $entry = AuditLog::query()
            ->where('auditable_type', ProjectDocument::class)
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayNotHasKey('created_by', $entry->new_values ?? []);
        $this->assertArrayNotHasKey('updated_by', $entry->new_values ?? []);
    }
}
