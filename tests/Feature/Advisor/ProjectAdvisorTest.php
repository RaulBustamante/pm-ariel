<?php

declare(strict_types=1);

namespace Tests\Feature\Advisor;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectFinding;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\ProjectScheduler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El motor de avisos **detecta y explica; no propone la jugada** (D-017).
 * Estas pruebas cuidan las dos mitades: que detecte lo que debe, y que cada
 * hallazgo traiga su causa en texto legible.
 */
final class ProjectAdvisorTest extends TestCase
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

        // Arranque **en el futuro**, calculado desde hoy. Con una fecha fija el
        // plan se vuelve viejo con el paso del tiempo y la regla de «vencida sin
        // avance» empieza a dispararse en pruebas que no van de eso: la suite
        // pasaría hoy y fallaría sola en unos meses.
        $this->project = Project::query()->create([
            'code' => 'ADV-1',
            'name' => 'Proyecto vigilado',
            'owner_id' => $this->manager->id,
            'planned_start' => now()->addWeek()->startOfWeek()->setTime(9, 0),
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

        return $task;
    }

    private function resource(string $name, int $capacity = 100, ?string $email = null): Resource
    {
        $resource = new Resource;
        $resource->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'capacity_percent' => $capacity,
            'email' => $email,
        ]);
        $resource->save();

        return $resource;
    }

    private function assign(Task $task, Resource $resource, int $units = 100): void
    {
        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $resource->id,
            'units_percent' => $units,
        ]);
    }

    private function analyze(): Collection
    {
        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return app(ProjectAdvisor::class)->analyze($this->project->refresh());
    }

    /**
     * @param  Collection<int, ProjectFinding>  $findings
     * @return list<string>
     */
    private function rules(Collection $findings): array
    {
        return $findings->pluck('rule')->all();
    }

    // ------------------------------------------------------- Sobreasignación

    #[Test]
    public function two_overlapping_tasks_at_full_time_flag_the_person(): void
    {
        $ana = $this->resource('Ana');

        // Las dos arrancan el lunes: se traslapan de principio a fin.
        $this->assign($this->task('Levantamiento'), $ana);
        $this->assign($this->task('Pruebas'), $ana);

        $findings = $this->analyze();

        $this->assertContains('resource.overallocated', $this->rules($findings));

        $finding = $findings->firstWhere('rule', 'resource.overallocated');
        $this->assertStringContainsString('200', $finding->message);
        $this->assertStringContainsString('Ana', $finding->message);
        $this->assertTrue($finding->isCritical());
    }

    /**
     * Dos tareas donde una termina justo cuando la otra empieza no compiten por
     * la misma hora de nadie. Marcarlas sería el falso positivo que hace que la
     * gente deje de leer los avisos.
     */
    #[Test]
    public function consecutive_tasks_do_not_flag_anyone(): void
    {
        $ana = $this->resource('Ana');

        $first = $this->task('Primera');
        $second = $this->task('Segunda');

        $this->assign($first, $ana);
        $this->assign($second, $ana);

        // Se encadenan: la segunda arranca cuando la primera cierra.
        TaskDependency::query()->create([
            'project_id' => $this->project->id,
            'predecessor_id' => $first->id,
            'successor_id' => $second->id,
            'type' => 'FS',
        ]);

        $this->assertNotContains('resource.overallocated', $this->rules($this->analyze()));
    }

    #[Test]
    public function two_half_time_overlapping_tasks_are_fine(): void
    {
        $ana = $this->resource('Ana');

        $this->assign($this->task('Una'), $ana, units: 50);
        $this->assign($this->task('Otra'), $ana, units: 50);

        $this->assertNotContains('resource.overallocated', $this->rules($this->analyze()));
    }

    // ----------------------------------------------------- Persona repetida

    #[Test]
    public function the_same_name_twice_is_flagged(): void
    {
        $this->resource('Ana López');
        $this->resource('ana lópez');

        $findings = $this->analyze();

        $this->assertContains('resource.duplicated', $this->rules($findings));
    }

    #[Test]
    public function the_same_email_under_different_names_is_flagged(): void
    {
        $this->resource('Ana L.', email: 'ana@ariel.example');
        $this->resource('Ana López', email: 'ana@ariel.example');

        $this->assertContains('resource.duplicated_email', $this->rules($this->analyze()));
    }

    // ------------------------------------------------------- Otras reglas

    #[Test]
    public function a_critical_task_without_an_owner_is_flagged(): void
    {
        $this->task('Sin dueño');

        $findings = $this->analyze();

        $this->assertContains('task.critical_without_owner', $this->rules($findings));
    }

    #[Test]
    public function a_critical_task_with_an_owner_is_not_flagged(): void
    {
        $this->task('Con dueño', attributes: ['owner_id' => $this->manager->id]);

        $this->assertNotContains('task.critical_without_owner', $this->rules($this->analyze()));
    }

    #[Test]
    public function negative_float_against_a_committed_date_is_critical(): void
    {
        $this->task('Larga', days: 5);
        $this->task('Comprometida', days: 3, attributes: [
            'constraint_type' => 'FNLT',
            'constraint_date' => '2026-01-06 18:00',
        ]);

        $findings = $this->analyze();

        $this->assertContains('task.negative_float', $this->rules($findings));
        $this->assertTrue($findings->firstWhere('rule', 'task.negative_float')?->isCritical());
    }

    /**
     * Un hito que no depende de nada se queda pegado al arranque del proyecto y
     * afirma que la entrega ocurre el primer día.
     */
    #[Test]
    public function a_milestone_that_depends_on_nothing_is_flagged(): void
    {
        $this->task('Entrega final', days: 0);

        $this->assertContains('milestone.without_predecessors', $this->rules($this->analyze()));
    }

    #[Test]
    public function a_milestone_with_a_predecessor_is_not_flagged(): void
    {
        $work = $this->task('Trabajo', days: 3);
        $milestone = $this->task('Entrega final', days: 0);

        TaskDependency::query()->create([
            'project_id' => $this->project->id,
            'predecessor_id' => $work->id,
            'successor_id' => $milestone->id,
            'type' => 'FS',
        ]);

        $this->assertNotContains('milestone.without_predecessors', $this->rules($this->analyze()));
    }

    /**
     * Una tarea que debió terminar y sigue en cero. La fecha se pone en el
     * pasado a propósito, que es lo que la regla busca.
     */
    #[Test]
    public function an_overdue_task_with_no_progress_is_flagged(): void
    {
        $this->project->update(['planned_start' => now()->subMonth()->startOfWeek()->setTime(9, 0)]);

        $this->task('Se pasó de fecha', days: 1, attributes: ['owner_id' => $this->manager->id]);

        $this->assertContains('task.overdue_without_progress', $this->rules($this->analyze()));
    }

    // ------------------------------------------------------- Comportamiento

    /**
     * Un hallazgo corregido que sigue en pantalla enseña a la gente a ignorar
     * el panel entero.
     */
    #[Test]
    public function findings_are_replaced_and_not_accumulated(): void
    {
        $task = $this->task('Sin dueño');

        $this->analyze();
        $first = ProjectFinding::query()->where('project_id', $this->project->id)->count();

        $task->update(['owner_id' => $this->manager->id]);
        $this->analyze();

        $this->assertGreaterThan(0, $first);
        $this->assertNotContains(
            'task.critical_without_owner',
            ProjectFinding::query()->where('project_id', $this->project->id)->pluck('rule')->all(),
        );
    }

    /** El compromiso de D-017, verificado: ninguna regla propone la jugada. */
    #[Test]
    public function no_finding_proposes_an_action(): void
    {
        $ana = $this->resource('Ana');
        $this->assign($this->task('Una'), $ana);
        $this->assign($this->task('Otra'), $ana);

        foreach ($this->analyze() as $finding) {
            $this->assertNull($finding->suggested_action, "La regla {$finding->rule} no debe proponer acción.");
        }
    }

    #[Test]
    public function every_finding_explains_its_cause(): void
    {
        $ana = $this->resource('Ana');
        $this->assign($this->task('Una'), $ana);
        $this->assign($this->task('Otra'), $ana);

        $findings = $this->analyze();

        $this->assertNotEmpty($findings);

        foreach ($findings as $finding) {
            $this->assertNotSame('', trim((string) $finding->why));
            // Una clave sin traducir llegaría a la pantalla como "advisor.algo".
            $this->assertStringNotContainsString('advisor.', (string) $finding->why);
            $this->assertStringNotContainsString('advisor.', (string) $finding->message);
        }
    }

    #[Test]
    public function a_healthy_plan_shows_green(): void
    {
        $this->task('Única', attributes: ['owner_id' => $this->manager->id]);

        $findings = $this->analyze();

        $this->assertSame('green', app(ProjectAdvisor::class)->light($findings));
    }

    #[Test]
    public function the_panel_renders_with_its_findings(): void
    {
        $ana = $this->resource('Ana');
        $this->assign($this->task('Una'), $ana);
        $this->assign($this->task('Otra'), $ana);
        $this->analyze();

        $this->actingAs($this->manager)
            ->get(route('projects.advisor', $this->project))
            ->assertOk()
            ->assertSee('Ana')
            ->assertSee(__('advisor.why_no_suggestion'));
    }

    #[Test]
    public function assigning_a_resource_rechecks_the_plan_right_away(): void
    {
        $ana = $this->resource('Ana');
        $first = $this->task('Una');
        $second = $this->task('Otra');

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        $this->actingAs($this->manager)->post(route('projects.assignments.store', [$this->project, $first]), [
            'resource_id' => $ana->id,
            'units_percent' => 100,
        ]);

        $this->actingAs($this->manager)->post(route('projects.assignments.store', [$this->project, $second]), [
            'resource_id' => $ana->id,
            'units_percent' => 100,
        ])->assertRedirect();

        $this->assertContains(
            'resource.overallocated',
            ProjectFinding::query()->where('project_id', $this->project->id)->pluck('rule')->all(),
        );
    }
}
