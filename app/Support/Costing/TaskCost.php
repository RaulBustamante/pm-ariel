<?php

declare(strict_types=1);

namespace App\Support\Costing;

use App\Models\Task;
use App\Models\TaskAssignment;

/**
 * Lo que cuesta una tarea, desglosado en las tres cosas de las que se compone.
 *
 * **Mano de obra** es horas × tarifa, y las horas no son las del calendario sino
 * las que de verdad dedica cada quien: una tarea de tres días con alguien al
 * 50 % son trece horas y media, no veintisiete. Se calcula sobre
 * `duration_minutes`, que el motor ya expresa en **minutos de trabajo** —esa es
 * la razón por la que toda la aritmética del sistema está en esa unidad y no en
 * días de calendario.
 *
 * **Materiales** es cantidad × costo unitario. No lleva horas de por medio: el
 * acero cuesta lo mismo si la tarea dura tres días o seis.
 *
 * **Fijo** es la columna `cost` que ya existía: lo que alguien capturó a mano
 * porque no sale de ningún recurso —un permiso, un flete, una licencia—. Se
 * conserva tal cual y **se suma**, no se reemplaza: si el costo calculado
 * sobrescribiera lo capturado, el primer recálculo borraría en silencio un dato
 * que alguien puso a propósito.
 *
 * La clase no consulta la base. Recibe la tarea con sus asignaciones ya cargadas
 * y devuelve números, igual que el motor de programación — así se puede probar
 * contra casos resueltos a mano y calcular un escenario hipotético sin escribir.
 */
final class TaskCost
{
    /** Minutos en una hora. Nombrado para que la división se lea. */
    private const MINUTES_PER_HOUR = 60;

    /**
     * @return array{labor: float, materials: float, fixed: float, total: float, hours: float}
     */
    public static function of(Task $task): array
    {
        // Un resumen no cuesta: su costo es el de sus hijas, y contarlo aquí
        // duplicaría todo el proyecto al sumar.
        if ($task->is_summary) {
            return self::nothing();
        }

        $hours = (float) $task->duration_minutes / self::MINUTES_PER_HOUR;

        $labor = 0.0;
        $materials = 0.0;
        $workedHours = 0.0;

        foreach ($task->assignments as $assignment) {
            $resource = $assignment->resource;

            if ($resource === null) {
                continue;
            }

            if ($resource->isMaterial()) {
                $materials += (float) ($assignment->quantity ?? 0) * (float) ($resource->cost_per_unit ?? 0);

                continue;
            }

            // Las horas de esta persona en esta tarea. Al 50 % pone la mitad; al
            // 200 % —que el asesor marca como sobreasignación— pone el doble, y
            // el costo lo refleja en vez de esconderlo.
            $share = (float) $assignment->units_percent / 100;
            $workedHours += $hours * $share;
            $labor += $hours * $share * (float) ($resource->cost_per_hour ?? 0);
        }

        $fixed = (float) $task->cost;

        return [
            'labor' => round($labor, 2),
            'materials' => round($materials, 2),
            'fixed' => round($fixed, 2),
            'total' => round($labor + $materials + $fixed, 2),
            'hours' => round($workedHours, 2),
        ];
    }

    /**
     * El costo de una asignación suelta, para pintarlo renglón por renglón.
     *
     * @return array{cost: float, hours: float}
     */
    public static function ofAssignment(TaskAssignment $assignment, Task $task): array
    {
        $resource = $assignment->resource;

        if ($resource === null) {
            return ['cost' => 0.0, 'hours' => 0.0];
        }

        if ($resource->isMaterial()) {
            return [
                'cost' => round((float) ($assignment->quantity ?? 0) * (float) ($resource->cost_per_unit ?? 0), 2),
                'hours' => 0.0,
            ];
        }

        $hours = ((float) $task->duration_minutes / self::MINUTES_PER_HOUR)
            * ((float) $assignment->units_percent / 100);

        return [
            'cost' => round($hours * (float) ($resource->cost_per_hour ?? 0), 2),
            'hours' => round($hours, 2),
        ];
    }

    /**
     * ¿Se puede confiar en el total?
     *
     * Un recurso sin tarifa aporta cero al costo, y un cero es indistinguible de
     * «es gratis». Quien lea un reporte de costos tiene derecho a saber que hay
     * huecos, así que se cuentan y la pantalla lo dice en vez de presentar un
     * total que parece completo.
     *
     * @return list<\App\Models\Resource>
     */
    public static function missingRates(Task $task): array
    {
        $missing = [];

        foreach ($task->assignments as $assignment) {
            $resource = $assignment->resource;

            if ($resource === null) {
                continue;
            }

            $rate = $resource->isMaterial() ? $resource->cost_per_unit : $resource->cost_per_hour;

            if ($rate === null || (float) $rate <= 0) {
                $missing[] = $resource;
            }
        }

        return $missing;
    }

    /**
     * @return array{labor: float, materials: float, fixed: float, total: float, hours: float}
     */
    private static function nothing(): array
    {
        return ['labor' => 0.0, 'materials' => 0.0, 'fixed' => 0.0, 'total' => 0.0, 'hours' => 0.0];
    }
}
