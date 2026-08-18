<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreLogEntryRequest;
use App\Models\Project;
use App\Models\ProjectLogEntry;
use App\Models\User;
use App\Support\Documents\ProjectLog;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Los catorce registros del PMI, sobre un solo controlador.
 *
 * Incidencias, decisiones, cambios, lecciones, minutas, acciones, supuestos,
 * mediciones. Un proyecto, un código de registro y una lista que crece: lo que
 * cambia entre una incidencia y una lección lo dice la configuración, no el
 * código.
 *
 * El alta vive en la misma pantalla que la lista, y no detrás de un botón que
 * lleve a otra. Un registro se llena a media junta o no se llena, y cada
 * pantalla intermedia es una excusa para anotarlo en una libreta.
 */
final class ProjectLogController extends Controller
{
    public function __construct(
        private readonly ProjectLog $log,
    ) {}

    public function index(Request $request, Project $project, string $code): View
    {
        $this->authorize('view', $project);
        abort_unless($this->log->isLog($code), 404);

        return $this->screen($project, $code, $request, null);
    }

    public function store(StoreLogEntryRequest $request, Project $project, string $code): RedirectResponse
    {
        abort_unless($this->log->isLog($code), 404);

        $entry = $this->log->record($project, $code, $request->validated());

        return redirect()
            ->route('projects.documents.log', [$project, $code])
            ->with('status', __('logs.recorded', ['reference' => $entry->reference()]));
    }

    public function edit(Request $request, Project $project, string $code, ProjectLogEntry $entry): View
    {
        $this->authorize('update', $project);
        abort_unless($this->log->isLog($code), 404);
        $this->assertBelongs($project, $code, $entry);

        return $this->screen($project, $code, $request, $entry);
    }

    public function update(
        StoreLogEntryRequest $request,
        Project $project,
        string $code,
        ProjectLogEntry $entry,
    ): RedirectResponse {
        abort_unless($this->log->isLog($code), 404);
        $this->assertBelongs($project, $code, $entry);

        $this->log->amend($entry, $request->validated());

        return redirect()
            ->route('projects.documents.log', [$project, $code])
            ->with('status', __('logs.amended', ['reference' => $entry->reference()]));
    }

    public function destroy(Project $project, string $code, ProjectLogEntry $entry): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($this->log->isLog($code), 404);
        $this->assertBelongs($project, $code, $entry);

        // Borrado en suave, y el número **no se reutiliza**: si INC-004 se citó
        // en un correo, que el siguiente renglón se llame INC-004 es peor que
        // que el número falte.
        $entry->delete();

        return back()->with('status', __('logs.deleted'));
    }

    /** El registro completo en PDF, con la misma portada que el resto. */
    public function pdf(Request $request, Project $project, string $code): Response
    {
        $this->authorize('view', $project);
        abort_unless($this->log->isLog($code), 404);

        // Sale **lo que se está viendo**, filtros incluidos. Un PDF que ignora
        // el filtro obliga a explicar de viva voz por qué trae renglones que la
        // pantalla no enseñaba.
        $pdf = Pdf::loadView('documents.log-pdf', [
            'project' => $project,
            'code' => $code,
            'title' => __("documents.doc_{$code}"),
            'entries' => $this->log->entries($project, $code, $this->filters($request)),
            'summary' => $this->log->summary($project, $code),
            'fields' => $this->log->fields($code),
            'closed' => $this->log->closedStatuses($code),
            'generatedAt' => now(),
        ])->setPaper('letter', 'landscape')->setOption('isRemoteEnabled', false);

        return $pdf->download("{$project->code}-{$code}.pdf");
    }

    private function screen(Project $project, string $code, Request $request, ?ProjectLogEntry $editing): View
    {
        $filters = $this->filters($request);

        return view('documents.log', [
            'project' => $project,
            'code' => $code,
            'entries' => $this->log->entries($project, $code, $filters),
            'summary' => $this->log->summary($project, $code),
            'statuses' => $this->log->statuses($code),
            'closed' => $this->log->closedStatuses($code),
            'fields' => $this->log->fields($code),
            'priorities' => (array) config('pmi_logs.priorities', []),
            'candidates' => User::query()->where('is_active', true)->orderBy('name')->get(),
            'entry' => $editing,
            'filters' => $filters,
        ]);
    }

    /**
     * @return array{status: ?string, owner: ?int, q: ?string}
     */
    private function filters(Request $request): array
    {
        $owner = $request->query('owner');

        return [
            'status' => $request->string('status')->value() ?: null,
            'owner' => is_numeric($owner) ? (int) $owner : null,
            'q' => $request->string('q')->value() ?: null,
        ];
    }

    /**
     * Un renglón de otro proyecto o de otro registro responde 404 y no 403: que
     * exista es exactamente lo que no se quiere confirmar.
     */
    private function assertBelongs(Project $project, string $code, ProjectLogEntry $entry): void
    {
        abort_unless(
            $entry->project_id === $project->id && $entry->document_code === $code,
            404,
        );
    }
}
