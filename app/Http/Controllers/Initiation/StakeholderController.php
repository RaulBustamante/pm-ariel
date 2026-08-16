<?php

declare(strict_types=1);

namespace App\Http\Controllers\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Initiation\StoreStakeholderRequest;
use App\Models\Project;
use App\Models\Stakeholder;
use Illuminate\Http\RedirectResponse;

final class StakeholderController extends Controller
{
    public function store(StoreStakeholderRequest $request, Project $project, SuggestsContent $suggestions): RedirectResponse
    {
        $stakeholder = new Stakeholder;
        $stakeholder->fill($request->validated());
        $stakeholder->project_id = $project->id;

        // Si el usuario no escribió estrategia, se llena con la del cuadrante.
        // Es la sugerencia hecha valor por omisión: nadie tiene que pedirla, y
        // quien no esté de acuerdo la cambia.
        if (blank($stakeholder->engagement_strategy)) {
            $stakeholder->engagement_strategy = $suggestions->suggestEngagementStrategy($stakeholder);
        }

        $stakeholder->save();

        return back()->with('status', __('initiation.stakeholder_created'));
    }

    public function update(StoreStakeholderRequest $request, Project $project, Stakeholder $stakeholder): RedirectResponse
    {
        $this->authorizeBelongs($project, $stakeholder);

        $stakeholder->update($request->validated());

        return redirect()
            ->route('projects.initiation.stakeholders', $project)
            ->with('status', __('initiation.stakeholder_updated'));
    }

    public function destroy(Project $project, Stakeholder $stakeholder): RedirectResponse
    {
        $this->authorize('delete', $stakeholder);
        $this->authorizeBelongs($project, $stakeholder);

        $stakeholder->delete();

        return back()->with('status', __('initiation.stakeholder_deleted'));
    }

    /**
     * Precarga los interesados típicos del tipo de proyecto. Se agregan sin
     * pisar lo que ya existe: se salta cualquiera cuyo nombre ya esté puesto.
     */
    public function suggest(Project $project, SuggestsContent $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $existing = $project->stakeholders->pluck('name')
            ->map(fn (string $name): string => mb_strtolower($name))
            ->all();

        $added = 0;

        foreach ($suggestions->suggestStakeholders($project) as $suggested) {
            $name = (string) ($suggested['name'] ?? '');

            if ($name === '' || in_array(mb_strtolower($name), $existing, strict: true)) {
                continue;
            }

            $stakeholder = new Stakeholder;
            $stakeholder->fill([
                'name' => $name,
                'role_title' => $suggested['role_title'] ?? null,
                'organization' => $suggested['organization'] ?? null,
                'power' => (int) ($suggested['power'] ?? 3),
                'interest' => (int) ($suggested['interest'] ?? 3),
            ]);
            $stakeholder->project_id = $project->id;
            $stakeholder->engagement_strategy = $suggestions->suggestEngagementStrategy($stakeholder);
            $stakeholder->save();

            $added++;
        }

        return back()->with(
            'status',
            $added === 0
                ? __('initiation.suggestions_none')
                : __('initiation.suggestions_added', ['count' => $added]),
        );
    }

    /**
     * La ruta trae proyecto e interesado por separado. Sin esta comprobación,
     * cambiar el número en la barra de direcciones editaría el interesado de
     * otro proyecto — uno al que sí se tiene acceso, así que la Policy pasaría.
     */
    private function authorizeBelongs(Project $project, Stakeholder $stakeholder): void
    {
        abort_unless($stakeholder->project_id === $project->id, 404);
    }
}
