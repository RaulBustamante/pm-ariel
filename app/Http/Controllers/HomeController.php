<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFinding;
use App\Models\Role;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Support\Reporting\ProjectDashboard;
use App\Support\Visibility\VisibilityScope;
use Illuminate\View\View;

/**
 * La pantalla de inicio: cómo va cada proyecto que alcanzo a ver.
 *
 * Responde la pregunta con la que alguien abre el sistema por la mañana —«¿voy
 * bien?»— sin que tenga que entrar proyecto por proyecto a averiguarlo.
 */
final class HomeController extends Controller
{
    public function __construct(
        private readonly ProjectDashboard $dashboard,
        private readonly ProjectAdvisor $advisor,
    ) {}

    public function index(VisibilityScope $visibility): View
    {
        /** @var User|null $viewer */
        $viewer = auth()->user();

        if ($viewer === null || ! $viewer->can('viewAny', Project::class)) {
            return view('dashboard', ['projects' => collect()]);
        }

        $query = Project::query()->with(['charter']);

        if (! $viewer->hasRole(Role::ADMIN) && ! $viewer->hasRole(Role::AUDITOR)) {
            $visibility->scopeProjects($query, $viewer);
        }

        // Se acota el número: un inicio que recorre doscientos proyectos para
        // pintar tarjetas tarda más que cualquiera de las pantallas de detalle,
        // y quien abre esto no está buscando los doscientos.
        $projects = $query->orderByDesc('id')->limit(12)->get();

        $findings = ProjectFinding::query()
            ->whereIn('project_id', $projects->pluck('id'))
            ->get()
            ->groupBy('project_id');

        return view('dashboard', [
            'projects' => $projects->map(function (Project $project) use ($findings): array {
                $kpis = $this->dashboard->kpis($project);

                return [
                    'project' => $project,
                    'progress' => $kpis['progress'],
                    'finish' => $kpis['finish'],
                    'overdue' => $kpis['overdue'],
                    'light' => $this->advisor->light($findings->get($project->id, collect())),
                ];
            }),
        ]);
    }
}
