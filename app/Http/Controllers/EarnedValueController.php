<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Costing\EarnedValue;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * El informe de valor ganado y el pronóstico de costos.
 *
 * Dos documentos del catálogo sobre la misma pantalla, porque son el mismo
 * cálculo leído de dos maneras: los índices contestan «¿cómo vamos?» y los
 * pronósticos, «¿en cuánto va a acabar esto?».
 *
 * La fecha de corte se puede mover. No es un adorno: los índices de hoy no
 * sirven para explicar una junta de hace tres semanas, y volver a calcularlos a
 * la fecha de aquella junta es la única forma honesta de decir qué se sabía
 * entonces.
 */
final class EarnedValueController extends Controller
{
    public function __construct(
        private readonly EarnedValue $earnedValue,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $statusDate = $this->statusDate($request);

        return view('costing.earned-value', [
            'project' => $project,
            'evm' => $this->earnedValue->for($project, $statusDate),
            'statusDate' => $statusDate,
        ]);
    }

    public function pdf(Request $request, Project $project): Response
    {
        $this->authorize('view', $project);

        $statusDate = $this->statusDate($request);

        $pdf = Pdf::loadView('costing.earned-value-pdf', [
            'project' => $project,
            'evm' => $this->earnedValue->for($project, $statusDate),
            'generatedAt' => now(),
        ])->setPaper('letter', 'landscape')->setOption('isRemoteEnabled', false);

        return $pdf->download("{$project->code}-earned-value.pdf");
    }

    /**
     * La fecha de corte pedida, o hoy.
     *
     * Una fecha ilegible cae en hoy en vez de reventar: es un parámetro de la
     * dirección, y quien la escriba a mano no debe encontrarse un error de
     * servidor por un dedo de más.
     */
    private function statusDate(Request $request): Carbon
    {
        $asked = $request->string('at')->value();

        if ($asked === '') {
            return Carbon::now();
        }

        try {
            return Carbon::parse($asked)->endOfDay();
        } catch (\Throwable) {
            return Carbon::now();
        }
    }
}
