<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\OrgUnit;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Role;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Initiation\InitiationHealth;
use App\Support\Initiation\InitiationStarter;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
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
     * Los ajustes del proyecto: datos, arranque, miembros y calendario.
     *
     * Cambiar la fecha de arranque o el calendario **recalcula el plan entero**.
     * Sin eso, el proyecto diría que empieza en marzo mientras sus tareas siguen
     * con las fechas de enero, y nadie notaría la contradicción hasta imprimirlo.
     */
    public function edit(Project $project): View
    {
        $this->authorize('update', $project);

        return view('projects.edit', [
            ...$this->formOptions(),
            'project' => $project->load(['members', 'calendars']),
            'candidates' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'baselines' => $project->baselines()->with('capturedBy')->get(),
        ]);
    }

    public function update(UpdateProjectRequest $request, Project $project, ProjectScheduler $scheduler): RedirectResponse
    {
        $before = [
            'start' => $project->planned_start?->format('Y-m-d H:i'),
        ];

        $project->update($request->validated());

        if ($before['start'] !== $project->refresh()->planned_start?->format('Y-m-d H:i')) {
            $scheduler->reschedule($project);

            return redirect()
                ->route('projects.edit', $project)
                ->with('status', __('projects.updated_and_rescheduled'));
        }

        return redirect()->route('projects.edit', $project)->with('status', __('projects.updated'));
    }

    /** Alta y baja de miembros. Ser miembro es lo que da escritura (regla 2). */
    public function addMember(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
            'project_role' => ['required', Rule::in([Project::ROLE_MANAGER, Project::ROLE_MEMBER, Project::ROLE_VIEWER])],
        ]);

        $project->members()->syncWithoutDetaching([
            $data['user_id'] => ['project_role' => $data['project_role']],
        ]);

        return back()->with('status', __('projects.member_added'));
    }

    public function removeMember(Project $project, User $user): RedirectResponse
    {
        $this->authorize('update', $project);

        // Quitar al dueño lo dejaría sin poder editar su propio proyecto, y
        // recuperarlo exigiría a un administrador. Se niega y se dice por qué.
        if ($project->owner_id === $user->id) {
            return back()->with('warning', __('projects.cannot_remove_owner'));
        }

        $project->members()->detach($user->id);

        return back()->with('status', __('projects.member_removed'));
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
