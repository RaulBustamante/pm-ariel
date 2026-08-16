<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Models\OrgUnit;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Role;
use App\Models\User;
use App\Support\Initiation\InitiationHealth;
use App\Support\Initiation\InitiationStarter;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * El listado de proyectos y su alta.
 *
 * El alta es mínima a propósito: nombre, clave y tipo. Todo lo demás se captura
 * en el recorrido de inicio, que es donde el usuario tiene el contexto para
 * responder. Un formulario de alta con veinte campos es la forma más rápida de
 * que alguien abandone antes de empezar.
 *
 * El CRUD completo de proyectos es del bloque 4.1; aquí solo está lo que la
 * Etapa 2 necesita para ser usable.
 */
final class ProjectController extends Controller
{
    public function index(VisibilityScope $visibility, InitiationHealth $health): View
    {
        $this->authorize('viewAny', Project::class);

        /** @var User $viewer */
        $viewer = auth()->user();

        $query = Project::query()->with(['charter', 'owner', 'orgUnit']);

        if (! $viewer->hasRole(Role::ADMIN) && ! $viewer->hasRole(Role::AUDITOR)) {
            $visibility->scopeProjects($query, $viewer);
        }

        $projects = $query->orderByDesc('id')->paginate(25);

        return view('projects.index', [
            'projects' => $projects,
            'health' => $health,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Project::class);

        return view('projects.create', $this->formOptions());
    }

    public function store(StoreProjectRequest $request, InitiationStarter $starter): RedirectResponse
    {
        /** @var User $owner */
        $owner = $request->user();

        $template = $request->filled('template_id')
            ? ProjectTemplate::query()->find($request->integer('template_id'))
            : null;

        $project = $starter->start(
            [
                'code' => $request->string('code')->value(),
                'name' => $request->string('name')->value(),
                'description' => $request->input('description'),
                'org_unit_id' => $request->input('org_unit_id'),
            ],
            $owner,
            $template,
        );

        return redirect()
            ->route('projects.initiation.justification', $project)
            ->with('status', __('initiation.created'));
    }

    /**
     * @return array{orgUnits: Collection<int, OrgUnit>, templates: Collection<int, ProjectTemplate>}
     */
    private function formOptions(): array
    {
        return [
            'orgUnits' => OrgUnit::query()->orderBy('name')->get(),
            'templates' => ProjectTemplate::query()->orderBy('sort_order')->orderBy('name')->get(),
        ];
    }
}
