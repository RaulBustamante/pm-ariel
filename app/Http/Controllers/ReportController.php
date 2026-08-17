<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Documents\DocumentCatalogue;
use App\Support\Reporting\FindingDigest;
use App\Support\Reporting\ProjectReportData;
use App\Support\Reporting\WeeklyReport;
use App\Support\Scheduling\ProjectDurations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Las salidas del proyecto: PDF para mandar, CSV para seguir trabajando.
 *
 * **Dos motores a propósito** (D-020). El PDF de texto lo genera dompdf en el
 * servidor: sale un archivo que se adjunta a un correo sin que nadie tenga que
 * hacer nada. El Gantt se imprime desde el navegador con hoja de estilo propia,
 * porque dompdf no dibuja SVG de forma confiable y la alternativa —instalar un
 * navegador headless en el servidor de Ariel— es una decisión de TI, no de
 * código.
 */
final class ReportController extends Controller
{
    public function __construct(
        private readonly TaskOutliner $outliner,
        private readonly ProjectReportData $report,
        private readonly WeeklyReport $weeklyReport,
        private readonly FindingDigest $digest,
    ) {}

    /** Ficha del proyecto y lista de tareas, con portada y numeración. */
    public function pdf(Project $project): Response
    {
        return $this->render($project, complete: false);
    }

    /**
     * Lo mismo, más el diagrama, en un solo archivo.
     *
     * Existe porque mandar dos adjuntos y pedirle a quien los recibe que los
     * junte no es entregar un reporte. Sigue siendo dompdf y no un navegador
     * headless (D-020): el Gantt se ajusta al ancho de la hoja en vez de
     * recortarse, que es lo que obligaba a imprimirlo aparte.
     */
    public function complete(Project $project): Response
    {
        return $this->render($project, complete: true);
    }

    /**
     * El corte de la semana, en una hoja.
     *
     * Documento distinto de la ficha, no una versión corta: la ficha responde
     * «de qué se trata esto» y se emite una vez; esto responde «cómo vamos» y se
     * manda cada lunes a alguien que ya sabe de qué se trata.
     */
    public function weekly(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->loadMissing(['owner']);

        $data = $this->weeklyReport->for($project);
        $findings = $project->findings()->with(['task', 'resource'])->get();

        $pdf = Pdf::loadView('reports.weekly-pdf', [
            ...$data,
            'project' => $project,
            'digest' => $this->digest->group($findings),
            'generatedAt' => now(),
            'focusChart' => $this->report->focusChart($project, $data),
        ])->setPaper('letter')->setOption('isRemoteEnabled', false);

        return $pdf->download($this->filename($project, 'semana-'.$data['from']->format('Y-m-d').'.pdf'));
    }

    /**
     * El juego completo de documentos del PMI, con su estado.
     *
     * Se pinta el catálogo entero —los setenta— y no solo lo que existe. Un
     * sistema que enseña cuatro documentos y calla los otros sesenta y seis se
     * ve completo hasta que alguien pide el que falta a media junta.
     */
    public function documents(Project $project, DocumentCatalogue $catalogue): View
    {
        $this->authorize('view', $project);

        return view('reports.documents', [
            'project' => $project,
            'groups' => $catalogue->forProject($project),
            'coverage' => $catalogue->coverage(),
        ]);
    }

    private function render(Project $project, bool $complete): Response
    {
        $this->authorize('view', $project);

        $project->loadMissing(['charter.sponsor', 'owner', 'orgUnit']);

        $pdf = Pdf::loadView('reports.project-pdf', $this->report->for($project, $complete))
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', false);

        $this->stampPageNumbers($pdf);

        return $pdf->download($this->filename($project, $complete ? 'completo.pdf' : 'pdf'));
    }

    /**
     * El «página N de M», escrito sobre el lienzo y no con CSS.
     *
     * `counter(pages)` de CSS sale en cero en dompdf —el pie decía «Página 1/0»
     * en cada hoja— porque el total no existe hasta que terminó de maquetar. La
     * API del lienzo sí corre después, y es la única que conoce el número.
     *
     * La otra salida documentada es habilitar la ejecución de PHP dentro de las
     * plantillas de dompdf. No se hace: el reporte incluye texto que capturó un
     * usuario, y abrir un intérprete de PHP en el mismo lugar por un número de
     * página es un precio desproporcionado.
     */
    private function stampPageNumbers(\Barryvdh\DomPDF\PDF $pdf): void
    {
        $dompdf = $pdf->getDomPDF();
        $dompdf->render();

        $dompdf->getCanvas()->page_text(
            x: 470,
            y: 762,
            text: __('reports.page').' {PAGE_NUM} / {PAGE_COUNT}',
            font: $dompdf->getFontMetrics()->getFont('DejaVu Sans'),
            size: 7.5,
            color: [0.39, 0.45, 0.55],
        );
    }

    /** El Gantt, en una hoja pensada para imprimir. */
    public function ganttPrint(Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->outliner->outline($project)
            ->filter(fn ($task): bool => $task->early_start !== null && $task->early_finish !== null)
            ->values();

        return view('reports.gantt-print', [
            'project' => $project,
            'tasks' => $tasks,
            'dependencies' => $project->taskDependencies()->get(),
            'durations' => ProjectDurations::for($project),
            // Se pagina por bloques de renglones para que ninguna barra quede
            // partida entre dos hojas, y cada página repite el encabezado.
            'pages' => $tasks->chunk(28),
        ]);
    }

    /**
     * Exportación CSV.
     *
     * Con BOM y punto y coma: Excel en español abre un CSV de comas metiendo
     * todo en la primera columna, y sin BOM se come los acentos. Exportar algo
     * que hay que reparar a mano no es exportar.
     */
    public function csv(Project $project): StreamedResponse
    {
        $this->authorize('view', $project);

        $tasks = $this->outliner->outline($project);
        $durations = ProjectDurations::for($project);

        $filename = $this->filename($project, 'csv');

        return response()->streamDownload(function () use ($tasks, $durations): void {
            $handle = fopen('php://output', 'wb');

            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                __('tasks.row'), 'WBS', __('tasks.name'), __('tasks.duration'),
                __('tasks.start'), __('tasks.finish'), __('tasks.owner'),
                __('tasks.progress'), __('tasks.cost'), __('tasks.critical'),
            ], ';', '"', '\\');

            foreach ($tasks as $index => $task) {
                fputcsv($handle, [
                    $index + 1,
                    $task->wbs_code,
                    // La sangría se conserva: reimportar el archivo debe
                    // reconstruir la misma jerarquía.
                    str_repeat('    ', (int) ($task->outline_depth ?? 0)).$task->name,
                    $durations->toHuman((int) $task->duration_minutes),
                    $task->early_start?->format('d/m/Y') ?? '',
                    $task->early_finish?->format('d/m/Y') ?? '',
                    $task->owner_id === null ? '' : (string) $task->owner?->name,
                    (int) $task->percent_complete,
                    number_format((float) $task->cost, 2, '.', ''),
                    $task->is_critical ? __('common.yes') : __('common.no'),
                ], ';', '"', '\\');
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function filename(Project $project, string $extension): string
    {
        return str($project->code.'-'.$project->name)->slug()->limit(60, '')->value().'.'.$extension;
    }
}
