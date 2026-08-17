<?php

declare(strict_types=1);

namespace Tests\Feature\Resources;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Scheduling\BaselineManager;
use App\Support\Costing\ProjectCosts;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los tres cortes de la pantalla de análisis: carga, horas y costo.
 */
final class AnalysisReportTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'ANL-1',
            'name' => 'Proyecto analizado',
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

    #[Test]
    public function the_screen_shows_the_three_cuts(): void
    {
        $this->work();

        $this->actingAs($this->manager)
            ->get(route('projects.analysis', $this->project))
            ->assertOk()
            ->assertSee(__('analysis.workload'))
            ->assertSee(__('analysis.hours'))
            ->assertSee(__('analysis.by_phase'));
    }

    /**
     * Sin línea base no se inventa una comparación: se dice que no hay contra
     * qué comparar. Presentar una desviación contra nada es peor que no
     * presentarla.
     */
    #[Test]
    public function without_a_baseline_it_says_so_instead_of_inventing_a_variance(): void
    {
        $this->work();

        $this->actingAs($this->manager)
            ->get(route('projects.analysis', $this->project))
            ->assertOk()
            ->assertSee(__('analysis.no_baseline'));
    }

    /**
     * La línea base congela el costo **completo**, no solo el capturado a mano.
     *
     * Hasta la Etapa 6 el costo de una tarea era la columna que alguien
     * tecleaba. Ahora la mayor parte sale de los recursos, y una línea base con
     * solo el costo fijo compararía dos cosas distintas.
     */
    #[Test]
    public function the_baseline_freezes_the_computed_cost_not_only_the_fixed_one(): void
    {
        $task = $this->work();

        $baseline = $this->actingAs($this->manager)
            ->app->make(BaselineManager::class)
            ->capture($this->project, 'Original');

        $costs = app(ProjectCosts::class)->for($this->project);

        $this->assertSame(
            round($costs['total'], 2),
            round((float) $baseline->total_cost, 2),
            'La línea base tiene que congelar el mismo total que muestra el reporte.',
        );

        // Y en concreto: la tarea tenía cero de costo fijo, así que un total
        // distinto de cero solo puede venir de los recursos.
        $this->assertSame(0.0, (float) $task->cost);
        $this->assertGreaterThan(0, (float) $baseline->total_cost);
    }

    /** Con línea base, la desviación se compara contra el compromiso original. */
    #[Test]
    public function the_variance_is_shown_against_the_baseline(): void
    {
        $task = $this->work();

        app(BaselineManager::class)->capture($this->project, 'Original');

        // Se encarece el plan: la tarea dura el doble.
        $task->update(['duration_minutes' => 1080]);

        $this->actingAs($this->manager)
            ->get(route('projects.analysis', $this->project))
            ->assertOk()
            ->assertSee(__('analysis.variance'))
            ->assertSee(__('analysis.baseline_cost'));
    }

    /**
     * El pico por semana es lo que decide si alguien está sobrecargado. El
     * promedio esconde la semana mala, que es justo la que hay que ver.
     */
    #[Test]
    public function the_workload_reports_the_weekly_peak(): void
    {
        $this->work();

        $workload = app(ProjectCosts::class)->workload($this->project);

        $this->assertCount(1, $workload['rows']);
        $this->assertSame(9.0, $workload['rows'][0]['peak']);
        $this->assertSame(100, $workload['rows'][0]['capacity']);
    }

    /**
     * Dos tareas de la misma persona en la misma semana **se suman**. Si no lo
     * hicieran, el histograma nunca mostraría una sobrecarga y el reporte
     * contradiría al aviso de sobreasignación.
     */
    #[Test]
    public function two_tasks_in_the_same_week_add_up_for_the_same_person(): void
    {
        $person = $this->person(500.0);

        foreach (['Una', 'Otra'] as $name) {
            $task = $this->task($name, 540);
            $task->forceFill(['early_start' => '2026-03-02 09:00'])->save();

            TaskAssignment::query()->create([
                'task_id' => $task->id,
                'resource_id' => $person->id,
                'units_percent' => 100,
            ]);
        }

        $workload = app(ProjectCosts::class)->workload($this->project);

        $this->assertSame(18.0, $workload['rows'][0]['peak']);
    }

    #[Test]
    public function someone_who_cannot_see_the_project_cannot_see_the_analysis(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::TEAM_MEMBER)->value('id'));

        $this->actingAs($stranger)
            ->get(route('projects.analysis', $this->project))
            ->assertForbidden();
    }

    // --- Armado -------------------------------------------------------------

    /** Una tarea de nueve horas con una persona a 500/h, sin costo fijo. */
    private function work(): Task
    {
        $task = $this->task('Montaje', 540);
        $task->forceFill(['early_start' => '2026-03-02 09:00'])->save();

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->person(500.0)->id,
            'units_percent' => 100,
        ]);

        return $task->refresh();
    }

    private function task(string $name, int $minutes): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $minutes,
            'sort_order' => 0,
            'cost' => 0,
        ]);
        $task->save();

        return $task;
    }

    private function person(float $rate): Resource
    {
        return Resource::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Luis Ortega',
            'type' => Resource::TYPE_PERSON,
            'capacity_percent' => 100,
            'cost_per_hour' => $rate,
        ]);
    }
}
