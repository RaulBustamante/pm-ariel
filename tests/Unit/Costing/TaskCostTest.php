<?php

declare(strict_types=1);

namespace Tests\Unit\Costing;

use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Support\Costing\TaskCost;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * El costo de una tarea, contra números calculados a mano.
 *
 * No toca la base: se arman modelos en memoria con sus relaciones puestas, igual
 * que el motor de programación. Eso es lo que permite comprobar la aritmética
 * contra un caso resuelto en papel en vez de contra lo que el propio código
 * calculó la vez anterior.
 */
final class TaskCostTest extends TestCase
{
    /**
     * Nueve horas al 100 % a 500 la hora son 4,500. Es el caso base y el que
     * fija la unidad: `duration_minutes` son **minutos de trabajo**, no de
     * calendario, así que un día de jornada de 9 h son 540.
     */
    #[Test]
    public function labour_is_hours_times_rate(): void
    {
        $task = $this->task(540);
        $this->assign($task, $this->person(500.0), units: 100);

        $cost = TaskCost::of($task);

        $this->assertSame(4500.0, $cost['labor']);
        $this->assertSame(9.0, $cost['hours']);
        $this->assertSame(4500.0, $cost['total']);
    }

    /**
     * Al 50 % pone la mitad de las horas, y por tanto la mitad del costo. Es la
     * diferencia entre la duración de la tarea y las horas que alguien dedica —
     * confundirlas duplica el presupuesto de medio equipo.
     */
    #[Test]
    public function half_time_costs_half(): void
    {
        $task = $this->task(540);
        $this->assign($task, $this->person(500.0), units: 50);

        $cost = TaskCost::of($task);

        $this->assertSame(2250.0, $cost['labor']);
        $this->assertSame(4.5, $cost['hours']);
    }

    /**
     * La sobreasignación **se refleja en el costo**, no se recorta.
     *
     * Alguien al 200 % cuesta el doble. Topar el cálculo en 100 % daría un
     * presupuesto que se ve sano justo donde el plan está mal, y el aviso de
     * sobreasignación quedaría contradicho por los números.
     */
    #[Test]
    public function overallocation_shows_up_in_the_cost(): void
    {
        $task = $this->task(540);
        $this->assign($task, $this->person(500.0), units: 200);

        $this->assertSame(9000.0, TaskCost::of($task)['labor']);
    }

    /** Cantidad × costo unitario, y sin horas de por medio. */
    #[Test]
    public function material_is_quantity_times_unit_cost(): void
    {
        $task = $this->task(540);
        $this->assign($task, $this->material(85.5, 'kg'), quantity: 300.0);

        $cost = TaskCost::of($task);

        $this->assertSame(25650.0, $cost['materials']);
        // El material no aporta horas: si aportara, el histograma de carga
        // mostraría al acero trabajando.
        $this->assertSame(0.0, $cost['hours']);
        $this->assertSame(0.0, $cost['labor']);
    }

    /**
     * Un material cuesta lo mismo si la tarea dura tres días o seis. Es lo que
     * lo distingue de la mano de obra, y por eso son dos fórmulas.
     */
    #[Test]
    public function material_does_not_change_with_the_duration(): void
    {
        $short = $this->task(540);
        $this->assign($short, $this->material(85.5, 'kg'), quantity: 300.0);

        $long = $this->task(540 * 6);
        $this->assign($long, $this->material(85.5, 'kg'), quantity: 300.0);

        $this->assertSame(
            TaskCost::of($short)['materials'],
            TaskCost::of($long)['materials'],
        );
    }

    /**
     * El costo fijo capturado a mano **se suma**, no se reemplaza.
     *
     * Si el calculado lo sobrescribiera, el primer recálculo borraría en
     * silencio un dato que alguien puso a propósito —un permiso, un flete.
     */
    #[Test]
    public function the_hand_entered_cost_is_added_not_replaced(): void
    {
        $task = $this->task(540, fixed: 12000.0);
        $this->assign($task, $this->person(500.0), units: 100);
        $this->assign($task, $this->material(85.5, 'kg'), quantity: 100.0);

        $cost = TaskCost::of($task);

        $this->assertSame(12000.0, $cost['fixed']);
        $this->assertSame(4500.0, $cost['labor']);
        $this->assertSame(8550.0, $cost['materials']);
        $this->assertSame(25050.0, $cost['total']);
    }

    /**
     * Un resumen no cuesta: su costo es el de sus hijas. Contarlo aquí
     * duplicaría el proyecto entero al sumar.
     */
    #[Test]
    public function a_summary_costs_nothing_of_its_own(): void
    {
        $task = $this->task(540, fixed: 9999.0);
        $task->is_summary = true;
        $this->assign($task, $this->person(500.0), units: 100);

        $this->assertSame(0.0, TaskCost::of($task)['total']);
    }

    /**
     * Un recurso sin tarifa aporta cero, y un cero no se distingue de «es
     * gratis». Se reportan para que la pantalla pueda decir que el total está
     * incompleto.
     */
    #[Test]
    public function resources_without_a_rate_are_reported(): void
    {
        $task = $this->task(540);
        $this->assign($task, $this->person(500.0), units: 100);
        $this->assign($task, $this->person(null), units: 100);

        $missing = TaskCost::missingRates($task);

        $this->assertCount(1, $missing);
        $this->assertSame(4500.0, TaskCost::of($task)['labor']);
    }

    /** Un material sin costo unitario también cuenta como hueco. */
    #[Test]
    public function material_without_a_unit_cost_is_reported_too(): void
    {
        $task = $this->task(540);
        $this->assign($task, $this->material(null, 'kg'), quantity: 50.0);

        $this->assertCount(1, TaskCost::missingRates($task));
    }

    // --- Armado en memoria -------------------------------------------------

    private function task(int $minutes, float $fixed = 0.0): Task
    {
        $task = new Task;
        $task->duration_minutes = $minutes;
        $task->cost = $fixed;
        $task->is_summary = false;
        $task->setRelation('assignments', collect());

        return $task;
    }

    private function person(?float $rate): Resource
    {
        $resource = new Resource;
        $resource->id = random_int(1, 100000);
        $resource->name = 'Persona';
        $resource->type = Resource::TYPE_PERSON;
        $resource->cost_per_hour = $rate;

        return $resource;
    }

    private function material(?float $unitCost, string $unit): Resource
    {
        $resource = new Resource;
        $resource->id = random_int(100001, 200000);
        $resource->name = 'Material';
        $resource->type = Resource::TYPE_MATERIAL;
        $resource->cost_per_unit = $unitCost;
        $resource->unit_of_measure = $unit;

        return $resource;
    }

    private function assign(Task $task, Resource $resource, int $units = 0, ?float $quantity = null): void
    {
        $assignment = new TaskAssignment;
        $assignment->units_percent = $units;
        $assignment->quantity = $quantity;
        $assignment->setRelation('resource', $resource);

        $task->setRelation('assignments', $task->assignments->push($assignment));
    }
}
