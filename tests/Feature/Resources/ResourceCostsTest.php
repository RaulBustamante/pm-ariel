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
use App\Support\Costing\ProjectCosts;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Costos de recursos: la pantalla, la captura y el reparto.
 *
 * Lo que se cuida sobre todo es que **cada tipo se asigne en su unidad**. Un
 * material no tiene jornada que repartir, así que un porcentaje aplicado a él
 * produce un número que no significa nada y que nadie podría auditar contra una
 * factura.
 */
final class ResourceCostsTest extends TestCase
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
            'code' => 'COST-1',
            'name' => 'Proyecto con costos',
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
    public function a_person_can_be_created_with_an_hourly_rate(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.resources.store', $this->project), [
                'name' => 'Luis Ortega',
                'type' => Resource::TYPE_PERSON,
                'capacity_percent' => 100,
                'cost_per_hour' => '450.50',
            ])
            ->assertRedirect(route('projects.resources.index', $this->project));

        $this->assertDatabaseHas('resources', ['name' => 'Luis Ortega', 'cost_per_hour' => '450.50']);
    }

    #[Test]
    public function a_material_can_be_created_with_a_unit_and_a_unit_cost(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.resources.store', $this->project), [
                'name' => 'Perfil de acero',
                'type' => Resource::TYPE_MATERIAL,
                'unit_of_measure' => 'kg',
                'cost_per_unit' => '85.50',
                'supplier' => 'Aceros del Bajío',
                'is_external' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('resources', [
            'name' => 'Perfil de acero',
            'unit_of_measure' => 'kg',
            'is_external' => 1,
        ]);
    }

    /**
     * Un costo por unidad sin unidad deja el reporte diciendo «300» sin decir de
     * qué: trescientos kilos, piezas o litros son tres presupuestos distintos.
     */
    #[Test]
    public function a_unit_cost_without_a_unit_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.resources.store', $this->project), [
                'name' => 'Cemento',
                'type' => Resource::TYPE_MATERIAL,
                'cost_per_unit' => '210',
            ])
            ->assertSessionHasErrors('unit_of_measure');
    }

    /**
     * La tarifa es opcional a propósito: hay que poder dar de alta al equipo el
     * lunes y conseguir los costos el jueves. Un costo inventado para poder
     * guardar es peor que uno ausente — el ausente se ve en el reporte.
     */
    #[Test]
    public function a_resource_can_be_created_without_any_rate(): void
    {
        $this->actingAs($this->manager)
            ->post(route('projects.resources.store', $this->project), [
                'name' => 'Sin tarifa todavía',
                'type' => Resource::TYPE_PERSON,
                'capacity_percent' => 100,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('resources', ['name' => 'Sin tarifa todavía', 'cost_per_hour' => null]);
    }

    // --- La regla que más importa -------------------------------------------

    #[Test]
    public function a_material_is_assigned_by_quantity_and_not_by_percentage(): void
    {
        $task = $this->task('Montaje', 540);
        $material = $this->material(85.50, 'kg');

        $this->actingAs($this->manager)
            ->post(route('projects.assignments.store', [$this->project, $task]), [
                'resource_id' => $material->id,
                'quantity' => '300',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $task->id,
            'resource_id' => $material->id,
            'quantity' => '300.000',
        ]);
    }

    /**
     * Un porcentaje enviado a un material **no se guarda**. La regla vive en el
     * controlador y no en el formulario: si viviera en la vista, una petición
     * armada a mano podría dejar horas-persona sobre un costal de cemento.
     */
    #[Test]
    public function a_percentage_sent_for_a_material_does_not_become_a_quantity(): void
    {
        $task = $this->task('Montaje', 540);
        $material = $this->material(85.50, 'kg');

        $this->actingAs($this->manager)
            ->post(route('projects.assignments.store', [$this->project, $task]), [
                'resource_id' => $material->id,
                'units_percent' => 100,
            ])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('task_assignments', ['resource_id' => $material->id]);
    }

    #[Test]
    public function a_person_still_needs_a_percentage(): void
    {
        $task = $this->task('Montaje', 540);
        $person = $this->person(500.0);

        $this->actingAs($this->manager)
            ->post(route('projects.assignments.store', [$this->project, $task]), [
                'resource_id' => $person->id,
                'quantity' => '300',
            ])
            ->assertSessionHasErrors('units_percent');
    }

    // --- El reparto ---------------------------------------------------------

    #[Test]
    public function the_project_cost_splits_labour_materials_and_fixed(): void
    {
        $task = $this->task('Montaje', 540, fixed: 12000.0);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->person(500.0)->id,
            'units_percent' => 100,
        ]);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->material(85.50, 'kg')->id,
            'units_percent' => 0,
            'quantity' => 100,
        ]);

        $costs = app(ProjectCosts::class)->for($this->project);

        // Nueve horas a 500 son 4,500; cien kilos a 85.50 son 8,550; más 12,000
        // capturados a mano.
        $this->assertSame(4500.0, $costs['labor']);
        $this->assertSame(8550.0, $costs['materials']);
        $this->assertSame(12000.0, $costs['fixed']);
        $this->assertSame(25050.0, $costs['total']);
    }

    /**
     * La suma por tipo tiene que cuadrar con el total. Si no cuadrara, quien lo
     * note dejaría de creerle a los dos números.
     */
    #[Test]
    public function the_split_by_type_adds_up_to_the_total(): void
    {
        $task = $this->task('Montaje', 540, fixed: 3000.0);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->person(500.0)->id,
            'units_percent' => 100,
        ]);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->material(10.0, 'pieza')->id,
            'units_percent' => 0,
            'quantity' => 25,
        ]);

        $costs = app(ProjectCosts::class)->for($this->project);

        $this->assertEqualsWithDelta(
            $costs['total'],
            array_sum(array_column($costs['by_type'], 'cost')),
            0.01,
            'La suma por tipo no cuadra con el total.',
        );
    }

    #[Test]
    public function the_screen_warns_about_resources_without_a_rate(): void
    {
        $task = $this->task('Montaje', 540);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->person(null)->id,
            'units_percent' => 100,
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.resources.index', $this->project))
            ->assertOk()
            ->assertSee(__('resources.cost_total'))
            // El aviso: un cero no se distingue de «es gratis».
            ->assertSee('Sin tarifa', escape: false);
    }

    #[Test]
    public function the_workload_leaves_materials_out(): void
    {
        $task = $this->task('Montaje', 540);
        $task->forceFill(['early_start' => '2026-03-02 09:00'])->save();

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->person(500.0)->id,
            'units_percent' => 100,
        ]);

        TaskAssignment::query()->create([
            'task_id' => $task->id,
            'resource_id' => $this->material(10.0, 'pieza')->id,
            'units_percent' => 0,
            'quantity' => 25,
        ]);

        $workload = app(ProjectCosts::class)->workload($this->project);

        // Un material en el histograma de carga sería el acero trabajando horas.
        $this->assertCount(1, $workload['rows']);
        $this->assertSame(9.0, $workload['rows'][0]['peak']);
    }

    #[Test]
    public function someone_without_write_access_cannot_create_a_resource(): void
    {
        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->post(route('projects.resources.store', $this->project), [
                'name' => 'Intruso',
                'type' => Resource::TYPE_PERSON,
            ])
            ->assertForbidden();
    }

    // --- Armado -------------------------------------------------------------

    private function task(string $name, int $minutes, float $fixed = 0.0): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $minutes,
            'sort_order' => 0,
            'cost' => $fixed,
        ]);
        $task->save();

        return $task;
    }

    private function person(?float $rate): Resource
    {
        return Resource::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Persona '.fake()->unique()->numberBetween(1, 9999),
            'type' => Resource::TYPE_PERSON,
            'capacity_percent' => 100,
            'cost_per_hour' => $rate,
        ]);
    }

    private function material(?float $unitCost, string $unit): Resource
    {
        return Resource::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Material '.fake()->unique()->numberBetween(1, 9999),
            'type' => Resource::TYPE_MATERIAL,
            'unit_of_measure' => $unit,
            'cost_per_unit' => $unitCost,
        ]);
    }
}
