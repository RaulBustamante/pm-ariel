<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Los requisitos del proyecto y su matriz de trazabilidad.
 *
 * Es la única pieza de la Etapa 7 que necesitó datos nuevos. «Documentación de
 * requisitos» ya existía como documento que se redacta, y sirve para explicarle
 * el alcance a una persona; lo que no se puede hacer con un párrafo es contestar
 * **«¿qué tarea entrega este requisito?»** y, al revés, «¿este trabajo de dónde
 * salió?».
 *
 * La pantalla enseña las dos direcciones, y sobre todo **los dos huecos**: lo
 * que se pidió y nadie construye, y lo que se está construyendo sin que nadie lo
 * haya pedido.
 */
final class RequirementController extends Controller
{
    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        $requirements = ProjectRequirement::query()
            ->with('task')
            ->where('project_id', $project->id)
            ->orderBy('sequence')
            ->get();

        $deliverables = Task::query()
            ->where('project_id', $project->id)
            ->where('is_summary', false)
            ->orderBy('sort_order')
            ->get();

        $linked = $requirements->pluck('task_id')->filter()->unique();

        return view('requirements.index', [
            'project' => $project,
            'requirements' => $requirements,
            'deliverables' => $deliverables,
            // Lo que se pidió y nadie construye.
            'orphans' => $requirements->filter(fn (ProjectRequirement $r): bool => $r->isOrphan())->values(),
            // Y lo contrario, que casi nunca se busca y suele ser más caro: se
            // está construyendo algo que nadie pidió.
            'unrequested' => $deliverables->reject(fn (Task $t): bool => $linked->contains($t->id))->values(),
            'priorities' => ProjectRequirement::PRIORITIES,
            'statuses' => ProjectRequirement::STATUSES,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $this->validated($request, $project);

        // El número se asigna con bloqueo, como el de los registros y las actas:
        // dos personas capturando a la vez leerían el mismo «va en la 7».
        DB::transaction(function () use ($project, $data): void {
            $last = ProjectRequirement::query()
                ->withTrashed()
                ->where('project_id', $project->id)
                ->lockForUpdate()
                ->max('sequence');

            ProjectRequirement::query()->create([
                'project_id' => $project->id,
                'sequence' => ((int) $last) + 1,
                'description' => $data['description'],
                'origin' => $data['origin'] ?? null,
                'priority' => $data['priority'],
                'category' => $data['category'] ?? null,
                'task_id' => $data['task_id'] ?? null,
                'acceptance_criteria' => $data['acceptance_criteria'] ?? null,
                'status' => $data['status'],
            ]);
        });

        return back()->with('status', __('requirements.saved'));
    }

    public function update(Request $request, Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);

        $requirement->update($this->validated($request, $project));

        return back()->with('status', __('requirements.saved'));
    }

    public function destroy(Project $project, ProjectRequirement $requirement): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($requirement->project_id === $project->id, 404);

        $requirement->delete();

        return back()->with('status', __('requirements.deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Project $project): array
    {
        return $request->validate([
            'description' => ['required', 'string', 'max:500'],
            'origin' => ['nullable', 'string', 'max:255'],
            'priority' => ['required', Rule::in(ProjectRequirement::PRIORITIES)],
            'category' => ['nullable', 'string', 'max:40'],
            // La tarea tiene que ser **de este proyecto**: sin acotarlo, un
            // identificador escrito a mano ligaría el requisito al plan de otro.
            'task_id' => [
                'nullable', 'integer',
                Rule::exists('tasks', 'id')->where('project_id', $project->id)->withoutTrashed(),
            ],
            'acceptance_criteria' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(ProjectRequirement::STATUSES)],
        ]);
    }
}
