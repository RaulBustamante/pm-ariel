<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Project;
use App\Models\ProjectFinding;
use App\Services\Advisor\ProjectAdvisor;
use App\Support\Costing\ProjectCosts;
use DateTimeInterface;
use Illuminate\Support\Collection;

/**
 * Todos los proyectos en un renglón cada uno, y sus totales.
 *
 * La pregunta que contesta no es «¿cómo va este proyecto?» —para eso está el
 * tablero de cada uno— sino **«¿cómo vamos?»**, que es la que hace un director
 * y la que hasta ahora obligaba a abrir doce pantallas y sumar a mano.
 *
 * Todo sale de los mismos motores que las pantallas de detalle: el semáforo del
 * asesor, el costo de `ProjectCosts`, el avance de `ProjectDashboard`. Si la
 * cartera hiciera su propia cuenta, tarde o temprano diría un número distinto
 * del que dice el proyecto — y entonces habría que abrir el proyecto para saber
 * cuál creer, que es exactamente el trabajo que esta pantalla quita.
 */
final class Portfolio
{
    public function __construct(
        private readonly ProjectDashboard $dashboard,
        private readonly ProjectAdvisor $advisor,
        private readonly ProjectCosts $costs,
    ) {}

    /**
     * @param  Collection<int, Project>  $projects
     * @return array{
     *     rows: list<array{
     *         project: Project, light: string, progress: float, finish: ?DateTimeInterface,
     *         overdue: int, tasks: int, done: int, cost: float, earned: float, hours: float,
     *         alerts: int, owner: ?string
     *     }>,
     *     totals: array{projects: int<0, max>, tasks: int, overdue: int, cost: float, earned: float, hours: float, alerts: int<0, max>, late_projects: int<0, max>}
     * }
     */
    public function for(Collection $projects, bool $withCosts): array
    {
        $findings = ProjectFinding::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->get()
            ->groupBy('project_id');

        $rows = [];
        $totals = [
            'projects' => $projects->count(),
            'tasks' => 0,
            'overdue' => 0,
            'cost' => 0.0,
            'earned' => 0.0,
            'hours' => 0.0,
            'alerts' => 0,
            'late_projects' => 0,
        ];

        foreach ($projects as $project) {
            $kpis = $this->dashboard->kpis($project);
            $alerts = $findings->get($project->id, collect());

            // El costo solo se calcula si quien mira puede verlo. No es un
            // detalle de presentación: recorrer las asignaciones de doce
            // proyectos cuesta, y hacerlo para esconder el resultado sería
            // pagar el precio dos veces.
            $costs = $withCosts
                ? $this->costs->for($project)
                : ['total' => 0.0, 'earned' => 0.0, 'hours' => 0.0];

            $light = $this->advisor->light($alerts);

            $rows[] = [
                'project' => $project,
                'light' => $light,
                'progress' => (float) $kpis['progress'],
                'finish' => $kpis['finish'],
                'overdue' => (int) $kpis['overdue'],
                'tasks' => (int) $kpis['task_count'],
                'done' => (int) $kpis['done'],
                'cost' => (float) $costs['total'],
                'earned' => (float) $costs['earned'],
                'hours' => (float) $costs['hours'],
                'alerts' => $alerts->count(),
                'owner' => $project->owner?->name,
            ];

            $totals['tasks'] += (int) $kpis['task_count'];
            $totals['overdue'] += (int) $kpis['overdue'];
            $totals['cost'] += (float) $costs['total'];
            $totals['earned'] += (float) $costs['earned'];
            $totals['hours'] += (float) $costs['hours'];
            $totals['alerts'] += $alerts->count();

            if ($light !== 'green') {
                $totals['late_projects']++;
            }
        }

        // Lo que peor va, arriba. Ordenar por nombre o por fecha de alta pone en
        // el primer renglón el proyecto que no necesita atención, y quien abre
        // esto lo abre para encontrar el que sí.
        $weight = ['red' => 0, 'amber' => 1, 'green' => 2];

        usort($rows, function (array $a, array $b) use ($weight): int {
            return [$weight[$a['light']] ?? 3, -$a['overdue']]
                <=> [$weight[$b['light']] ?? 3, -$b['overdue']];
        });

        return ['rows' => $rows, 'totals' => $totals];
    }
}
