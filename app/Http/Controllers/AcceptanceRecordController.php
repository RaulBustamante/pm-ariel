<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreAcceptanceRecordRequest;
use App\Models\Project;
use App\Models\ProjectRecord;
use App\Models\Task;
use App\Support\Documents\AcceptanceRecord;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;
use RuntimeException;

/**
 * Las actas de aceptación, sobre un solo controlador.
 *
 * La de un entregable y la del proyecto entero: mismo formulario, misma lista,
 * misma firma. Lo que cambia —el prefijo del número y si apunta a una tarea del
 * plan— lo dice la configuración.
 *
 * **Firmar es el único acto que no se puede deshacer aquí.** Congela el acta y
 * archiva su PDF, así que la pantalla lo pide con confirmación y lo dice antes,
 * no después.
 */
final class AcceptanceRecordController extends Controller
{
    public function __construct(
        private readonly AcceptanceRecord $records,
    ) {}

    public function index(Project $project, string $code): View
    {
        $this->authorize('view', $project);
        abort_unless($this->records->isRecord($code), 404);

        return $this->screen($project, $code, null);
    }

    public function store(StoreAcceptanceRecordRequest $request, Project $project, string $code): RedirectResponse
    {
        abort_unless($this->records->isRecord($code), 404);

        $record = $this->records->open($project, $code, $request->validated());

        return redirect()
            ->route('projects.documents.record', [$project, $code])
            ->with('status', __('records.opened', ['reference' => $record->reference()]));
    }

    public function edit(Project $project, string $code, ProjectRecord $record): View
    {
        $this->authorize('update', $project);
        abort_unless($this->records->isRecord($code), 404);
        $this->assertBelongs($project, $code, $record);

        // Una firmada no se edita. Se responde 404 y no un formulario de solo
        // lectura: un formulario que no guarda es una invitación a escribir algo
        // que se va a perder.
        abort_if($record->isSigned(), 404);

        return $this->screen($project, $code, $record);
    }

    public function update(
        StoreAcceptanceRecordRequest $request,
        Project $project,
        string $code,
        ProjectRecord $record,
    ): RedirectResponse {
        abort_unless($this->records->isRecord($code), 404);
        $this->assertBelongs($project, $code, $record);

        try {
            $this->records->amend($record, $request->validated());
        } catch (RuntimeException) {
            return back()->with('warning', __('records.already_signed'));
        }

        return redirect()
            ->route('projects.documents.record', [$project, $code])
            ->with('status', __('records.amended', ['reference' => $record->reference()]));
    }

    /**
     * Firmar: congela el acta y archiva su PDF con el motor del bloque 7.1.
     *
     * A partir de aquí el renglón es inmutable —lo hace cumplir el modelo, no
     * este controlador— y el documento queda con su número de versión, su fecha
     * y su huella.
     */
    public function sign(Project $project, string $code, ProjectRecord $record): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($this->records->isRecord($code), 404);
        $this->assertBelongs($project, $code, $record);

        try {
            $this->records->sign($record, fn (ProjectRecord $signed): string => $this->render($project, $signed));
        } catch (RuntimeException) {
            return back()->with('warning', __('records.already_signed'));
        }

        return redirect()
            ->route('projects.documents.record', [$project, $code])
            ->with('status', __('records.signed', ['reference' => $record->reference()]));
    }

    /** Un borrador se puede tirar. Una firmada no: el modelo lo rechaza. */
    public function destroy(Project $project, string $code, ProjectRecord $record): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($this->records->isRecord($code), 404);
        $this->assertBelongs($project, $code, $record);

        if ($record->isSigned()) {
            return back()->with('warning', __('records.signed_cannot_be_deleted'));
        }

        $record->delete();

        return back()->with('status', __('records.deleted'));
    }

    public function pdf(Project $project, string $code, ProjectRecord $record): Response
    {
        $this->authorize('view', $project);
        abort_unless($this->records->isRecord($code), 404);
        $this->assertBelongs($project, $code, $record);

        return new Response($this->render($project, $record), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$project->code.'-'.$record->reference().'.pdf"',
        ]);
    }

    /** El PDF del acta. Lo usan la descarga y la firma, y tiene que ser el mismo. */
    private function render(Project $project, ProjectRecord $record): string
    {
        return Pdf::loadView('documents.record-pdf', [
            'project' => $project,
            'record' => $record,
            'title' => __("documents.doc_{$record->document_code}"),
            'generatedAt' => now(),
        ])->setPaper('letter')->setOption('isRemoteEnabled', false)->output();
    }

    private function screen(Project $project, string $code, ?ProjectRecord $editing): View
    {
        return view('documents.record', [
            'project' => $project,
            'code' => $code,
            'records' => $this->records->all($project, $code),
            'summary' => $this->records->summary($project, $code),
            'decisions' => $this->records->decisions(),
            'linksDeliverable' => $this->records->linksDeliverable($code),
            // Solo lo que de verdad se entrega: un resumen no es un entregable,
            // es el encabezado de varios.
            'deliverables' => $this->records->linksDeliverable($code)
                ? Task::query()
                    ->where('project_id', $project->id)
                    ->where('is_summary', false)
                    ->orderBy('sort_order')
                    ->get()
                : new Collection,
            'record' => $editing,
        ]);
    }

    private function assertBelongs(Project $project, string $code, ProjectRecord $record): void
    {
        abort_unless(
            $record->project_id === $project->id && $record->document_code === $code,
            404,
        );
    }
}
