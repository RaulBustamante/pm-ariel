<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\DocumentIssue;
use App\Models\Project;
use App\Support\Documents\DocumentIssuer;
use App\Support\Reporting\FindingDigest;
use App\Support\Reporting\ProjectReportData;
use App\Support\Reporting\WeeklyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Emitir una versión de un documento y volver a ella después.
 *
 * Emitir es un acto deliberado y no un efecto de descargar: bajar el PDF para
 * revisarlo es un borrador; emitirlo es comprometerse con lo que dice. Archivar
 * cada descarga llenaría la lista de borradores justo cuando hace falta
 * encontrar el bueno.
 */
final class DocumentIssueController extends Controller
{
    public function __construct(
        private readonly DocumentIssuer $issuer,
        private readonly ProjectReportData $report,
        private readonly WeeklyReport $weekly,
        private readonly FindingDigest $digest,
    ) {}

    /**
     * Los documentos que se pueden emitir hoy, y con qué datos se arma cada uno.
     *
     * Vive aquí y no en el catálogo porque es lo único que necesita **generar**
     * el archivo: el catálogo dice qué documentos existen; esto sabe cómo se
     * fabrican los tres que ya salen en PDF.
     */
    private const ISSUABLE = ['weekly', 'complete', 'sheet'];

    public function store(Request $request, Project $project, string $code): RedirectResponse
    {
        $this->authorize('update', $project);

        abort_unless(in_array($code, self::ISSUABLE, strict: true), 404);

        [$pdf, $title, $summary] = $this->build($project, $code);

        $issue = $this->issuer->issue(
            $project,
            $this->documentCode($code),
            $title,
            $pdf,
            $summary,
            $request->input('notes'),
        );

        return back()->with('status', __('documents.issued', [
            'document' => $issue->label(),
            'version' => $issue->version,
        ]));
    }

    /**
     * Se descarga desde aquí y no desde una dirección pública: es lo que hace
     * que la versión emitida de un proyecto ajeno no se abra con solo tener el
     * enlace.
     */
    public function download(Project $project, DocumentIssue $issue): StreamedResponse
    {
        $this->authorize('view', $project);

        abort_unless($issue->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($issue->stored_path), 404);

        return Storage::disk('local')->download(
            $issue->stored_path,
            $this->filename($project, $issue),
        );
    }

    /**
     * @return array{string, string, array<string, mixed>}
     */
    private function build(Project $project, string $code): array
    {
        if ($code === 'weekly') {
            $data = $this->weekly->for($project);
            $findings = $project->findings()->with(['task', 'resource'])->get();

            $pdf = Pdf::loadView('reports.weekly-pdf', [
                ...$data,
                'project' => $project,
                'digest' => $this->digest->group($findings),
                'generatedAt' => now(),
                'focusChart' => $this->report->focusChart($project, $data),
            ])->setPaper('letter')->setOption('isRemoteEnabled', false);

            return [
                $pdf->output(),
                __('reports.weekly_title').' · '.$data['from']->format('d/m/Y'),
                [
                    'progress' => $data['kpis']['progress'],
                    'late' => $data['late']->count(),
                    'closed' => $data['closed']->count(),
                    'slip_days' => $data['slip_days'],
                    'week_from' => $data['from']->format('Y-m-d'),
                ],
            ];
        }

        $complete = $code === 'complete';
        $data = $this->report->for($project, $complete);

        $pdf = Pdf::loadView('reports.project-pdf', $data)
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', false);

        return [
            $pdf->output(),
            $complete ? __('reports.complete') : __('reports.project_sheet'),
            [
                'progress' => $data['kpis']['progress'],
                'finish' => $data['kpis']['finish']?->format('Y-m-d'),
                'overdue' => $data['kpis']['overdue'],
                'tasks' => $data['tasks']->count(),
            ],
        ];
    }

    /** El código del catálogo PMI al que corresponde cada salida. */
    private function documentCode(string $code): string
    {
        return match ($code) {
            'weekly' => 'project_status_report',
            'complete' => 'project_management_plan',
            default => 'project_charter',
        };
    }

    private function filename(Project $project, DocumentIssue $issue): string
    {
        return sprintf(
            '%s-%s-v%d-%s.pdf',
            $project->code,
            $issue->document_code,
            $issue->version,
            $issue->issued_at?->format('Y-m-d') ?? 'sf',
        );
    }
}
