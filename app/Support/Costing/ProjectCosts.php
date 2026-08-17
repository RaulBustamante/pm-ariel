<?php

declare(strict_types=1);

namespace App\Support\Costing;

use App\Models\Project;
use App\Models\Resource;
use Illuminate\Support\Collection;

/**
 * El costo del proyecto, agrupado por las tres preguntas que la gente hace.
 *
 * «¿Cuánto llevamos gastado?» no es una pregunta: son tres, y cada una se
 * contesta con un corte distinto de los mismos números.
 *
 *   por recurso   quién / qué se lleva el dinero
 *   por tipo      cuánto es gente, cuánto material, cuánto de fuera
 *   por fase      en qué parte del plan está el gasto
 *
 * Un solo total no contesta ninguna. Y presentarlo sin decir **cuánto de eso
 * está comprometido y cuánto ya se consumió** invita a leerlo como gasto real
 * cuando es plan: por eso cada corte trae el planeado y el devengado según el
 * avance capturado.
 */
final class ProjectCosts
{
    /**
     * @return array{
     *     total: float, earned: float, labor: float, materials: float, fixed: float,
     *     external: float, hours: float,
     *     by_resource: list<array{name: string, type: string, external: bool, cost: float, hours: float, unit: ?string}>,
     *     by_type: list<array{type: string, cost: float, share: float}>,
     *     by_phase: list<array{name: string, cost: float, earned: float, share: float}>,
     *     missing_rates: list<string>
     * }
     */
    public function for(Project $project): array
    {
        $tasks = $project->tasks()
            ->with(['assignments.resource', 'parent'])
            ->where('is_summary', false)
            ->get();

        $totals = ['labor' => 0.0, 'materials' => 0.0, 'fixed' => 0.0, 'total' => 0.0, 'hours' => 0.0];
        $earned = 0.0;
        $external = 0.0;

        /** @var array<int, array{name: string, type: string, external: bool, cost: float, hours: float, unit: ?string}> $byResource */
        $byResource = [];
        /** @var array<string, float> $byType */
        $byType = [];
        /** @var array<string, array{cost: float, earned: float}> $byPhase */
        $byPhase = [];
        /** @var array<int, string> $missing */
        $missing = [];

        foreach ($tasks as $task) {
            $cost = TaskCost::of($task);

            foreach (['labor', 'materials', 'fixed', 'total', 'hours'] as $key) {
                $totals[$key] += $cost[$key];
            }

            // Devengado: la parte del costo que corresponde al avance capturado.
            // No es lo pagado —de eso no sabe nada este sistema— sino lo que ya
            // se consumió del presupuesto según lo que se reporta hecho.
            $earned += $cost['total'] * ((float) $task->percent_complete / 100);

            // El nombre del paquete al que pertenece. Sin padre, la tarea es su
            // propia fase: mejor un renglón con su nombre que un «sin fase» que
            // no le dice nada a nadie.
            //
            // Se pregunta por la llave y no por la relación: la llave dice si
            // cuelga de un paquete, y sigue diciéndolo aunque el paquete esté
            // borrado en suave y la relación venga vacía.
            $parent = $task->parent_id === null ? null : $task->parent;
            $phase = (string) ($parent === null ? $task->name : $parent->name);
            $byPhase[$phase] ??= ['cost' => 0.0, 'earned' => 0.0];
            $byPhase[$phase]['cost'] += $cost['total'];
            $byPhase[$phase]['earned'] += $cost['total'] * ((float) $task->percent_complete / 100);

            // El costo fijo no pertenece a ningún recurso, pero sí a un tipo: si
            // se omitiera, la suma por tipo no cuadraría con el total y quien lo
            // note dejaría de creerle a los dos.
            if ($cost['fixed'] > 0) {
                $byType['fixed'] = ($byType['fixed'] ?? 0.0) + $cost['fixed'];
            }

            foreach ($task->assignments as $assignment) {
                $resource = $assignment->resource;

                if ($resource === null) {
                    continue;
                }

                $line = TaskCost::ofAssignment($assignment, $task);

                $byResource[$resource->id] ??= [
                    'name' => (string) $resource->name,
                    'type' => (string) $resource->type,
                    'external' => (bool) $resource->is_external,
                    'cost' => 0.0,
                    'hours' => 0.0,
                    'unit' => $resource->unit_of_measure,
                ];

                $byResource[$resource->id]['cost'] += $line['cost'];
                $byResource[$resource->id]['hours'] += $line['hours'];

                $byType[(string) $resource->type] = ($byType[(string) $resource->type] ?? 0.0) + $line['cost'];

                if ($resource->is_external) {
                    $external += $line['cost'];
                }
            }

            foreach (TaskCost::missingRates($task) as $resource) {
                $missing[$resource->id] = (string) $resource->name;
            }
        }

        $total = $totals['total'];

        return [
            'total' => round($total, 2),
            'earned' => round($earned, 2),
            'labor' => round($totals['labor'], 2),
            'materials' => round($totals['materials'], 2),
            'fixed' => round($totals['fixed'], 2),
            'external' => round($external, 2),
            'hours' => round($totals['hours'], 2),
            'by_resource' => $this->sorted($byResource),
            'by_type' => $this->shares($byType, $total),
            'by_phase' => $this->phases($byPhase, $total),
            'missing_rates' => array_values($missing),
        ];
    }

    /**
     * Las horas de cada recurso por semana, para el histograma de carga.
     *
     * Los materiales no salen: no tienen horas, y meterlos con cero solo agrega
     * renglones vacíos a una gráfica que se lee por altura.
     *
     * @return array{weeks: list<string>, rows: list<array{name: string, capacity: int, hours: list<float>, peak: float}>}
     */
    public function workload(Project $project): array
    {
        $tasks = $project->tasks()
            ->with(['assignments.resource'])
            ->where('is_summary', false)
            ->whereNotNull('early_start')
            ->orderBy('early_start')
            ->get();

        /** @var array<string, bool> $weekSet */
        $weekSet = [];
        /** @var array<int, array{name: string, capacity: int, hours: array<string, float>}> $rows */
        $rows = [];

        foreach ($tasks as $task) {
            $week = $task->early_start?->copy()->startOfWeek()->format('Y-m-d');

            if ($week === null) {
                continue;
            }

            $weekSet[$week] = true;

            foreach ($task->assignments as $assignment) {
                $resource = $assignment->resource;

                if ($resource === null || $resource->isMaterial()) {
                    continue;
                }

                $line = TaskCost::ofAssignment($assignment, $task);

                $rows[$resource->id] ??= [
                    'name' => (string) $resource->name,
                    'capacity' => (int) $resource->capacity_percent,
                    'hours' => [],
                ];

                $rows[$resource->id]['hours'][$week] = ($rows[$resource->id]['hours'][$week] ?? 0.0) + $line['hours'];
            }
        }

        $weeks = array_keys($weekSet);
        sort($weeks);

        $out = [];

        foreach ($rows as $row) {
            $series = array_map(fn (string $week): float => round($row['hours'][$week] ?? 0.0, 1), $weeks);

            $out[] = [
                'name' => $row['name'],
                'capacity' => $row['capacity'],
                'hours' => $series,
                // El pico es lo que decide si alguien está sobrecargado, y es lo
                // que ordena la tabla: el promedio esconde la semana mala.
                'peak' => $series === [] ? 0.0 : max($series),
            ];
        }

        usort($out, fn (array $a, array $b): int => $b['peak'] <=> $a['peak']);

        return ['weeks' => $weeks, 'rows' => $out];
    }

    /**
     * @param  array<int, array{name: string, type: string, external: bool, cost: float, hours: float, unit: ?string}>  $byResource
     * @return list<array{name: string, type: string, external: bool, cost: float, hours: float, unit: ?string}>
     */
    private function sorted(array $byResource): array
    {
        $rows = array_map(
            fn (array $row): array => [...$row, 'cost' => round($row['cost'], 2), 'hours' => round($row['hours'], 2)],
            array_values($byResource),
        );

        usort($rows, fn (array $a, array $b): int => $b['cost'] <=> $a['cost']);

        return $rows;
    }

    /**
     * @param  array<string, float>  $byType
     * @return list<array{type: string, cost: float, share: float}>
     */
    private function shares(array $byType, float $total): array
    {
        $rows = [];

        foreach ($byType as $type => $cost) {
            $rows[] = [
                'type' => $type,
                'cost' => round($cost, 2),
                'share' => $total > 0 ? round($cost / $total * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['cost'] <=> $a['cost']);

        return $rows;
    }

    /**
     * @param  array<string, array{cost: float, earned: float}>  $byPhase
     * @return list<array{name: string, cost: float, earned: float, share: float}>
     */
    private function phases(array $byPhase, float $total): array
    {
        $rows = [];

        foreach ($byPhase as $name => $figures) {
            $rows[] = [
                'name' => $name,
                'cost' => round($figures['cost'], 2),
                'earned' => round($figures['earned'], 2),
                'share' => $total > 0 ? round($figures['cost'] / $total * 100, 1) : 0.0,
            ];
        }

        usort($rows, fn (array $a, array $b): int => $b['cost'] <=> $a['cost']);

        return $rows;
    }

    /**
     * Los recursos del proyecto, con lo que ya se les asignó. Alimenta la
     * pantalla de recursos.
     *
     * @return Collection<int, resource>
     */
    public function resourcesOf(Project $project): Collection
    {
        return $project->resources()
            ->withCount('assignments')
            ->orderBy('type')
            ->orderBy('name')
            ->get();
    }
}
