<?php

declare(strict_types=1);

namespace App\Http\Controllers\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Initiation\StoreRiskRequest;
use App\Http\Requests\Initiation\StoreRiskResponseRequest;
use App\Models\Project;
use App\Models\Risk;
use App\Models\RiskResponse;
use Illuminate\Http\RedirectResponse;

final class RiskController extends Controller
{
    public function store(StoreRiskRequest $request, Project $project): RedirectResponse
    {
        $risk = new Risk;
        $risk->fill($request->validated());
        $risk->project_id = $project->id;
        $risk->code = Risk::nextCodeFor($project);
        $risk->source = 'manual';
        $risk->save();

        return back()->with('status', __('initiation.risk_created'));
    }

    public function update(StoreRiskRequest $request, Project $project, Risk $risk): RedirectResponse
    {
        $this->authorizeBelongs($project, $risk);

        $risk->update($request->validated());

        return redirect()
            ->route('projects.initiation.risks', $project)
            ->with('status', __('initiation.risk_updated'));
    }

    public function destroy(Project $project, Risk $risk): RedirectResponse
    {
        // La pertenencia primero, los permisos después. Al revés, un riesgo de
        // otro proyecto contesta 403 y uno inexistente 404, y esa diferencia
        // basta para averiguar qué identificadores existen probando uno por uno.
        $this->authorizeBelongs($project, $risk);
        $this->authorize('delete', $risk);

        $risk->delete();

        return back()->with('status', __('initiation.risk_deleted'));
    }

    /**
     * Precarga los riesgos típicos del tipo de proyecto. Se salta los que ya
     * estén puestos desde el catálogo, para que oprimir dos veces no duplique.
     */
    public function suggest(Project $project, SuggestsContent $suggestions): RedirectResponse
    {
        $this->authorize('update', $project);

        $existingKeys = $project->risks->pluck('catalog_key')->filter()->all();
        $existingText = $project->risks->pluck('description')
            ->map(fn (string $text): string => mb_strtolower(trim($text)))
            ->all();

        $added = 0;

        foreach ($suggestions->suggestRisks($project) as $suggested) {
            $description = trim((string) ($suggested['description'] ?? ''));
            $key = $suggested['key'] ?? null;

            if ($description === '') {
                continue;
            }

            if ($key !== null && in_array($key, $existingKeys, strict: true)) {
                continue;
            }

            if (in_array(mb_strtolower($description), $existingText, strict: true)) {
                continue;
            }

            Risk::query()->create([
                'project_id' => $project->id,
                'code' => Risk::nextCodeFor($project),
                'category' => $suggested['category'] ?? null,
                'description' => $description,
                'cause' => $suggested['cause'] ?? null,
                'effect' => $suggested['effect'] ?? null,
                'probability' => (int) ($suggested['probability'] ?? 3),
                'impact' => (int) ($suggested['impact'] ?? 3),
                'kind' => $suggested['kind'] ?? Risk::KIND_THREAT,
                'source' => 'catalog',
                'catalog_key' => $key,
            ]);

            $added++;
        }

        return back()->with(
            'status',
            $added === 0
                ? __('initiation.suggestions_none')
                : __('initiation.suggestions_added', ['count' => $added]),
        );
    }

    public function storeResponse(StoreRiskResponseRequest $request, Project $project, Risk $risk): RedirectResponse
    {
        $this->authorizeBelongs($project, $risk);

        $response = new RiskResponse;
        $response->fill($request->validated());
        $response->risk_id = $risk->id;
        $response->save();

        // Registrar una respuesta mueve el riesgo de "identificado" a "con
        // respuesta en marcha". Pedirle al usuario que además cambie el estado a
        // mano solo genera registros que se contradicen entre sí.
        if ($risk->status === Risk::STATUS_IDENTIFIED) {
            $risk->update(['status' => Risk::STATUS_RESPONDING]);
        }

        return back()->with('status', __('initiation.response_created'));
    }

    public function destroyResponse(Project $project, Risk $risk, RiskResponse $response): RedirectResponse
    {
        $this->authorizeBelongs($project, $risk);
        abort_unless($response->risk_id === $risk->id, 404);
        $this->authorize('update', $risk);

        $response->delete();

        return back()->with('status', __('initiation.response_deleted'));
    }

    /**
     * La ruta trae proyecto y riesgo por separado. Sin esto, cambiar el número
     * en la barra de direcciones editaría el riesgo de otro proyecto — uno al
     * que sí se tiene acceso, así que la Policy no lo detendría.
     */
    private function authorizeBelongs(Project $project, Risk $risk): void
    {
        abort_unless($risk->project_id === $project->id, 404);
    }
}
