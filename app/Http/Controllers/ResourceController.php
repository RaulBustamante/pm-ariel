<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreResourceRequest;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Costing\ProjectCosts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

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

    /**
     * La pantalla de recursos: quien trabaja, que se consume, y cuanto cuesta.
     *
     * Vivia dentro del panel de avisos, que es donde nacio en la Etapa 4 porque
     * lo unico que hacia falta entonces era detectar sobreasignacion. Con costo
     * por hora, materiales y proveedor deja de ser un apendice: es el dato del
     * que sale todo el reporte de costos.
     */
    public function index(Project $project, ProjectCosts $costs): View
    {
        $this->authorize('view', $project);

        return $this->screen($project, $costs, null);
    }

    public function store(StoreResourceRequest $request, Project $project): RedirectResponse
    {
        $resource = new Resource;
        $resource->fill([...$request->validated(), 'project_id' => $project->id]);
        $resource->save();

        $this->advisor->analyze($project);

        return redirect()
            ->route('projects.resources.index', $project)
            ->with('status', __('resources.created'));
    }

    public function edit(Project $project, Resource $resource, ProjectCosts $costs): View
    {
        $this->authorize('update', $project);
        abort_unless($resource->project_id === $project->id, 404);

        return $this->screen($project, $costs, $resource);
    }

    public function update(StoreResourceRequest $request, Project $project, Resource $resource): RedirectResponse
    {
        abort_unless($resource->project_id === $project->id, 404);

        $resource->update($request->validated());

        $this->advisor->analyze($project);

        return redirect()
            ->route('projects.resources.index', $project)
            ->with('status', __('resources.updated'));
    }

    private function screen(Project $project, ProjectCosts $costs, ?Resource $editing): View
    {
        return view('resources.index', [
            'project' => $project,
            'resources' => $costs->resourcesOf($project),
            'costs' => $costs->for($project),
            'candidates' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'resource' => $editing,
        ]);
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
            'units_percent' => ['nullable', 'integer', 'between:1,500'],
            'quantity' => ['nullable', 'numeric', 'min:0.001', 'max:9999999'],
        ]);

        /** @var resource $resource */
        $resource = Resource::query()->findOrFail($data['resource_id']);

        /*
        | Cada tipo se asigna en su unidad, y **el formulario no decide cual**.
        |
        | Un material no tiene jornada que repartir: pedirle un porcentaje daria
        | un numero que no significa nada y que nadie podria auditar contra una
        | factura. Y una persona no se pide <<en piezas>>.
        |
        | Se resuelve aqui y no en la vista porque es una regla del dominio: si
        | viviera en el formulario, una peticion armada a mano --o un formulario
        | futuro-- podria guardar una cantidad de horas-persona sin que nada lo
        | detuviera.
        */
        // `??` y no acceso directo: `validate()` **no devuelve la llave** cuando
        // el campo no viajó en la petición, y un formulario que esconde el que no
        // aplica no lo manda. Sin esto, asignar un material reventaba con
        // «Undefined array key» en vez de guardar.
        $quantity = $data['quantity'] ?? null;
        $units = $data['units_percent'] ?? null;

        if ($resource->isMaterial()) {
            if ($quantity === null) {
                return back()->withErrors(['quantity' => __('resources.quantity_required')]);
            }

            $values = ['quantity' => $quantity, 'units_percent' => 0];
        } else {
            if ($units === null) {
                return back()->withErrors(['units_percent' => __('resources.units_required')]);
            }

            $values = ['units_percent' => $units, 'quantity' => null];
        }

        TaskAssignment::query()->updateOrCreate(
            ['task_id' => $task->id, 'resource_id' => $resource->id],
            $values,
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
