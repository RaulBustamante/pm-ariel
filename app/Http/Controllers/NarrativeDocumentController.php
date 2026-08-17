<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Contracts\Initiation\SuggestsContent;
use App\Models\Project;
use App\Support\Documents\NarrativeDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Redactar, guardar e imprimir cualquiera de los veinticinco documentos.
 *
 * Un solo controlador, porque son un solo tipo de cosa: un proyecto, un código
 * de documento y unas secciones de texto. Lo que cambia entre el plan de alcance
 * y el de costos es de qué se habla — y eso lo dice la configuración, no el
 * código.
 */
final class NarrativeDocumentController extends Controller
{
    public function __construct(
        private readonly NarrativeDocument $narrative,
    ) {}

    public function edit(Project $project, string $code): View
    {
        $this->authorize('view', $project);
        abort_unless($this->narrative->isNarrative($code), 404);

        $document = $this->narrative->of($project, $code);

        return view('documents.narrative', [
            'project' => $project,
            'code' => $code,
            'document' => $document,
            'sections' => $this->narrative->sections($code, $document),
            'missing' => $this->narrative->missing($code, $document),
        ]);
    }

    public function update(Request $request, Project $project, string $code): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($this->narrative->isNarrative($code), 404);

        // Cada sección es texto largo y nada es obligatorio: lo que falta se
        // señala en el tablero, no se impide guardar. El motor se encarga de
        // quedarse solo con las llaves que pertenecen a este documento.
        $request->validate([
            'sections' => ['array'],
            'sections.*' => ['nullable', 'string', 'max:20000'],
        ]);

        $this->narrative->save($project, $code, (array) $request->input('sections', []));

        return redirect()
            ->route('projects.documents.narrative', [$project, $code])
            ->with('status', __('sections.saved'));
    }

    /**
     * Un borrador para una sección.
     *
     * Reutiliza la misma costura del recorrido de inicio (D-018): si no hay
     * llave, si el proveedor falla o si se agotó la cuota, responde la plantilla
     * y la pantalla no se rompe. **Nunca se llama solo** — lo dispara este botón.
     */
    public function suggest(
        Project $project,
        string $code,
        string $section,
        SuggestsContent $suggestions,
    ): RedirectResponse {
        $this->authorize('update', $project);
        abort_unless($this->narrative->isNarrative($code), 404);

        $known = array_column($this->narrative->sections($code), 'key');
        abort_unless(in_array($section, $known, strict: true), 404);

        $draft = $suggestions->suggestNarrative($project, $section);

        if ($draft === null || trim($draft) === '') {
            return back()->with('warning', __('initiation.suggestion_empty'));
        }

        // Se guarda como cualquier otro texto: el borrador entra al campo y
        // queda a la vista para revisarlo. Presentarlo en un cuadro aparte
        // obligaría a copiarlo a mano, que es donde se pierde.
        $document = $this->narrative->of($project, $code);
        // Explicito y no `?->` a la izquierda de `??`: se distingue
        // <<no hay documento>> de <<el documento no tiene contenido>>, que son
        // dos cosas distintas aunque den el mismo arreglo vacio.
        $content = $document === null ? [] : ($document->content ?? []);
        $content[$section] = $draft;

        $this->narrative->save($project, $code, $content);

        return back()->with('status', __('initiation.suggestion_applied'));
    }

    /** El documento en PDF, con la misma portada que el resto de las salidas. */
    public function pdf(Project $project, string $code): Response
    {
        $this->authorize('view', $project);
        abort_unless($this->narrative->isNarrative($code), 404);

        $document = $this->narrative->of($project, $code);

        $pdf = Pdf::loadView('documents.narrative-pdf', [
            'project' => $project,
            'title' => __("documents.doc_{$code}"),
            'sections' => $this->narrative->sections($code, $document),
            'generatedAt' => now(),
        ])->setPaper('letter')->setOption('isRemoteEnabled', false);

        return $pdf->download("{$project->code}-{$code}.pdf");
    }
}
