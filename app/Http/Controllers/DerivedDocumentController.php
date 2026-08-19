<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Documents\DerivedDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * Los documentos que se generan solos, sobre un solo controlador.
 *
 * No hay formulario y no lo va a haber: un derivado que saliera vacío no se
 * arregla capturándolo aquí, se arregla capturando el dato en su pantalla. Por
 * eso, cuando no hay renglones, esta pantalla **dice de dónde debería salir** en
 * vez de ofrecer teclearlo por segunda vez en otro lado — que es como dos
 * lugares acaban guardando lo mismo y discrepando.
 */
final class DerivedDocumentController extends Controller
{
    public function __construct(
        private readonly DerivedDocument $derived,
    ) {}

    public function show(Project $project, string $code): View
    {
        $this->authorize('view', $project);
        abort_unless($this->derived->handles($code), 404);

        return view('documents.derived', [
            'project' => $project,
            'code' => $code,
            'columns' => $this->derived->columns($code),
            'rows' => $this->derived->rows($project, $code),
            'derived' => $this->derived,
        ]);
    }

    public function pdf(Project $project, string $code): Response
    {
        $this->authorize('view', $project);
        abort_unless($this->derived->handles($code), 404);

        $pdf = Pdf::loadView('documents.derived-pdf', [
            'project' => $project,
            'code' => $code,
            'title' => __("documents.doc_{$code}"),
            'columns' => $this->derived->columns($code),
            'rows' => $this->derived->rows($project, $code),
            'derived' => $this->derived,
            'generatedAt' => now(),
        ])->setPaper('letter', $this->derived->isLandscape($code) ? 'landscape' : 'portrait')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download("{$project->code}-{$code}.pdf");
    }
}
