<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFinding;
use App\Services\Advisor\ProjectAdvisor;
use App\Support\Reporting\ProjectDashboard;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * El tablero del proyecto: los números que se llevan a una junta.
 *
 * El semáforo dice **por qué está en ese color y qué haría falta para
 * cambiarlo**. Un semáforo que solo se pinta obliga a preguntarle a alguien, y
 * ese alguien acaba siendo el mismo que ya sabía la respuesta.
 */
final class DashboardController extends Controller
{
    public function __construct(
        private readonly ProjectDashboard $dashboard,
        private readonly ProjectAdvisor $advisor,
    ) {}

    public function show(Project $project): View
    {
        $this->authorize('view', $project);

        $findings = ProjectFinding::query()
            ->where('project_id', $project->id)
            ->with(['task', 'resource'])
            ->get();

        $kpis = $this->dashboard->kpis($project);
        $light = $this->advisor->light($findings);

        return view('reports.dashboard', [
            'project' => $project,
            'kpis' => $kpis,
            'curve' => $this->dashboard->sCurve($project),
            'light' => $light,
            'reasons' => $this->reasons($light, $kpis, $findings),
            'findings' => $findings->take(5),
        ]);
    }

    /**
     * Por qué el semáforo está así, en frases que alguien pueda repetir.
     *
     * @param  array<string, mixed>  $kpis
     * @param  Collection<int, ProjectFinding>  $findings
     * @return list<string>
     */
    private function reasons(string $light, array $kpis, $findings): array
    {
        if ($light === 'green' && $kpis['overdue'] === 0) {
            return [__('dashboard.why_green')];
        }

        $reasons = [];

        if ($kpis['overdue'] > 0) {
            $reasons[] = __('dashboard.why_overdue', ['count' => $kpis['overdue']]);
        }

        // El avance contra el tiempo transcurrido es la comparación que más
        // rápido delata un proyecto que va tarde, y casi nunca se muestra.
        if ($kpis['elapsed_percent'] - $kpis['progress'] > 10) {
            $reasons[] = __('dashboard.why_behind', [
                'progress' => $kpis['progress'],
                'elapsed' => $kpis['elapsed_percent'],
            ]);
        }

        foreach ($findings->where('severity', ProjectFinding::SEVERITY_CRITICAL)->take(3) as $finding) {
            $reasons[] = $finding->message;
        }

        return $reasons === [] ? [__('dashboard.why_amber_generic')] : $reasons;
    }
}
