<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\TaskOutliner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Recursos del proyecto y a qué tarea está asignado cada uno.
 *
 * Cada cambio vuelve a revisar el plan: una asignación que provoca una
 * sobreasignación debe avisar en el momento, no la próxima vez que alguien abra
 * el panel por casualidad.
 */
final class ResourceController extends Controller
{
    public function __construct(
        private readonly ProjectAdvisor $advisor,
        private readonly TaskOutliner $outliner,
    ) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'role_title' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'capacity_percent' => ['required', 'integer', 'between:1,500'],
            'type' => ['nullable', Rule::in([Resource::TYPE_PERSON, Resource::TYPE_EQUIPMENT, Resource::TYPE_VENDOR])],
            'user_id' => ['nullable', 'integer', Rule::exists('users', 'id')->withoutTrashed()],
        ]);

        $resource = new Resource;
        $resource->fill([...$data, 'project_id' => $project->id]);
        $resource->save();

        $this->advisor->analyze($project);

        return back()->with('status', __('resources.created'));
    }

    public function destroy(Project $project, Resource $resource): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($resource->project_id === $project->id, 404);

        $resource->delete();

        $this->advisor->analyze($project);

        return back()->with('status', __('resources.deleted'));
    }

    public function assign(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        $data = $request->validate([
            'resource_id' => [
                'required', 'integer',
                Rule::exists('resources', 'id')->where('project_id', $project->id)->withoutTrashed(),
            ],
            // Se permite pasar de 100: alguien puede dedicarle horas extra a una
            // tarea. Lo que no se permite es que nadie se entere, y de eso se
            // encarga el aviso de sobreasignación.
            'units_percent' => ['required', 'integer', 'between:1,500'],
        ]);

        TaskAssignment::query()->updateOrCreate(
            ['task_id' => $task->id, 'resource_id' => $data['resource_id']],
            ['units_percent' => $data['units_percent']],
        );

        $this->advisor->analyze($project);

        return back()->with('status', __('resources.assigned'));
    }

    public function unassign(Project $project, Task $task, Resource $resource): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);
        abort_unless($resource->project_id === $project->id, 404);

        TaskAssignment::query()
            ->where('task_id', $task->id)
            ->where('resource_id', $resource->id)
            ->delete();

        $this->advisor->analyze($project);

        return back()->with('status', __('resources.unassigned'));
    }
}
