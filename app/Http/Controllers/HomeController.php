<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFinding;
use App\Models\Role;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Support\Reporting\MyWeek;
use App\Support\Reporting\Portfolio;
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
        private readonly MyWeek $week,
        private readonly Portfolio $portfolio,
    ) {}

    public function index(VisibilityScope $visibility): View
    {
        /** @var User|null $viewer */
        $viewer = auth()->user();

        if ($viewer === null || ! $viewer->can('viewAny', Project::class)) {
            return view('dashboard', ['projects' => collect(), 'week' => null, 'portfolio' => null]);
        }

        // Las tareas se traen de una sola vez y ya filtradas. Los indicadores de
        // cada tarjeta se calculan sobre ellas; pedirlas por proyecto costaba
        // una consulta por tarjeta, y los resúmenes solo abultarían la memoria
        // porque su duración ya está contada en sus hijas.
        $query = Project::query()->with([
            'charter',
            'owner',
            'tasks' => fn ($tasks) => $tasks->where('is_summary', false),
        ]);

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

        // ¿Puede ver costos en **todos** los que alcanza a ver? El permiso es
        // por proyecto, y una columna de dinero que trae seis proyectos y
        // esconde otros seis suma mal sin decirlo. O están todos o no está la
        // columna.
        $withCosts = $projects->isNotEmpty()
            && $projects->every(fn (Project $project): bool => $viewer->can('viewCosts', $project));

        return view('dashboard', [
            // Todos los proyectos en un renglón cada uno. Las tarjetas
            // contestaban «¿cómo va este?» doce veces; esto contesta «¿cómo
            // vamos?», que es la pregunta de quien abre el inicio por la mañana
            // y tiene más de un proyecto encima.
            'portfolio' => $this->portfolio->for($projects, $withCosts),
            'withCosts' => $withCosts,

            // Lo mío de esta semana, cruzando todos los proyectos. Es la
            // pregunta con la que se abre el sistema en la mañana, y antes
            // obligaba a entrar proyecto por proyecto a armarla de memoria.
            'week' => $this->week->for($viewer, $projects),
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
