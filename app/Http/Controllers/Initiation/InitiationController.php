<?php

declare(strict_types=1);

namespace App\Http\Controllers\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Initiation\UpdateCharterRequest;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\User;
use App\Support\Initiation\InitiationHealth;
use App\Support\Initiation\InitiationStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * El recorrido de inicio, paso a paso.
 *
 * Guardar y avanzar son la misma acción: cada paso persiste al pasar al
 * siguiente, y `current_step` recuerda dónde se quedó. Nadie termina esto de una
 * sentada, y perder lo capturado a la mitad es la razón número uno por la que
 * un documento de inicio nunca se termina.
 */
final class InitiationController extends Controller
{
    public function __construct(
        private readonly InitiationHealth $health,
        private readonly SuggestsContent $suggestions,
    ) {}

    /** El resumen: dónde va el recorrido y qué falta. */
    public function overview(Project $project): View
    {
        $this->authorize('view', $project);

        $project->loadMissing(['charter.sponsor', 'charter.template', 'stakeholders', 'risks.responses', 'owner']);

        return view('initiation.overview', [
            'project' => $project,
            'charter' => $this->charterFor($project),
            'findings' => $this->health->findings($project),
            'light' => $this->health->light($project),
            'completion' => $this->health->completion($project),
        ]);
    }

    public function step(Project $project, string $step): View
    {
        $this->authorize('view', $project);

        $current = $this->resolveStep($step);

        $project->loadMissing(['charter.sponsor', 'charter.template', 'stakeholders', 'risks.responses']);

        return view("initiation.steps.{$current->value}", [
            'project' => $project,
            'charter' => $this->charterFor($project),
            'step' => $current,
            'findings' => $this->health->findingsFor($project, $current),
            'completion' => $this->health->completion($project),
            'canSuggest' => $this->suggestions->isAvailable($project),
            'members' => $this->assignableUsers($project),
        ]);
    }

    /**
     * Guarda el paso y avanza. Si el paso era el último, lleva al resumen: ahí
     * es donde se ve el semáforo completo y qué falta cerrar.
     */
    public function update(UpdateCharterRequest $request, Project $project, string $step): RedirectResponse
    {
        $current = $this->resolveStep($step);
        $charter = $this->charterFor($project);

        $charter->fill($request->validated());
        $charter->markCompleted($current->value);

        $next = $current->next();
        $charter->current_step = ($next ?? $current)->value;
        $charter->save();

        if ($request->input('action') === 'exit' || $next === null) {
            // Fin del recorrido: al resumen, que es donde se ve el semáforo
            // completo y qué falta cerrar.
            return redirect()
                ->route('projects.initiation.overview', $project)
                ->with('status', __('common.saved'));
        }

        return redirect()->route($next->route(), $project);
    }

    /**
     * Propone un borrador para un campo de texto. No guarda nada: lo devuelve a
     * la pantalla para que el usuario lo lea, lo corrija y decida.
     */
    public function suggest(Project $project, string $step, string $field): RedirectResponse
    {
        $this->authorize('update', $project);

        $current = $this->resolveStep($step);

        $draft = $this->suggestions->suggestNarrative($project, $field);

        if ($draft === null) {
            return back()->with('warning', __('initiation.suggestion_empty'));
        }

        return back()
            ->withInput([$field => $draft])
            ->with('status', __('initiation.suggestion_ready'))
            ->with('suggested_field', $field)
            ->with('suggested_step', $current->value);
    }

    private function charterFor(Project $project): ProjectCharter
    {
        // Un proyecto creado antes de esta etapa puede no tener acta. Crearla al
        // vuelo es preferible a una pantalla rota por un dato que falta.
        return $project->charter ?? ProjectCharter::query()->create([
            'project_id' => $project->id,
            'current_step' => InitiationStep::Justification->value,
            'completed_steps' => [],
        ]);
    }

    private function resolveStep(string $step): InitiationStep
    {
        return InitiationStep::tryFrom($step) ?? abort(404);
    }

    /**
     * Quién puede quedar como patrocinador o como responsable de un riesgo: los
     * miembros del proyecto y su dueño. Ofrecer a toda la empresa en una lista
     * desplegable no ayuda a nadie.
     *
     * @return Collection<int, User>
     */
    private function assignableUsers(Project $project): Collection
    {
        $project->loadMissing(['members', 'owner']);

        return $project->members
            ->concat([$project->owner])
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->values();
    }
}
