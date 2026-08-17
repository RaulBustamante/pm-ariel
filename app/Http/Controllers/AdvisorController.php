<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectFinding;
use App\Models\Resource;
use App\Services\Advisor\ProjectAdvisor;
use App\Support\Scheduling\TaskFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El panel de avisos y la vista de carga.
 *
 * La revisión se dispara al abrir la pantalla y con el botón. No se corre en
 * cada guardado de tarea porque recorre todas las asignaciones del proyecto, y
 * una pantalla que se siente lenta al escribir es una pantalla que la gente deja
 * de usar — que es la peor forma de que un aviso no llegue.
 */
final class AdvisorController extends Controller
{
    public function __construct(
        private readonly ProjectAdvisor $advisor,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $findings = ProjectFinding::query()
            ->where('project_id', $project->id)
            ->with(['task', 'resource'])
            ->get()
            // Lo grave primero: quien abre esto quiere saber qué se está
            // quemando, no leer una lista en orden de captura.
            ->sortBy(fn (ProjectFinding $finding): int => array_search(
                $finding->severity,
                ProjectFinding::SEVERITY_ORDER,
                strict: true,
            ) ?: 0)
            ->values();

        return view('advisor.show', [
            'project' => $project,
            'findings' => $findings,
            'light' => $this->advisor->light($findings),
            'workload' => $this->advisor->workload($project),
            'lastCheck' => $findings->first()?->detected_at,
            'filter' => TaskFilter::fromRequest($request),
            'resources' => Resource::query()->where('project_id', $project->id)->orderBy('name')->get(),
        ]);
    }

    public function analyze(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        $this->advisor->analyze($project);

        return redirect()
            ->route('projects.advisor', $project)
            ->with('status', __('advisor.analyzed'));
    }
}
