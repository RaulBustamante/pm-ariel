<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\TaskOutliner;
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
        private readonly ProjectAdvisor $advisor,
    ) {}

    /** Ficha del proyecto y lista de tareas, con portada y numeración. */
    public function pdf(Project $project): Response
    {
        $this->authorize('view', $project);

        $project->loadMissing(['charter.sponsor', 'owner', 'orgUnit']);

        $pdf = Pdf::loadView('reports.project-pdf', $this->data($project))
            ->setPaper('letter')
            ->setOption('isRemoteEnabled', false);

        return $pdf->download($this->filename($project, 'pdf'));
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

    /**
     * @return array<string, mixed>
     */
    private function data(Project $project): array
    {
        $tasks = $this->outliner->outline($project);
        $findings = $project->findings()->with(['task', 'resource'])->get();

        return [
            'project' => $project,
            'charter' => $project->charter,
            'tasks' => $tasks,
            'durations' => ProjectDurations::for($project),
            'findings' => $findings,
            'light' => $this->advisor->light($findings),
            'lastRun' => $project->scheduleRuns()->first(),
            'generatedAt' => now(),
        ];
    }

    private function filename(Project $project, string $extension): string
    {
        return str($project->code.'-'.$project->name)->slug()->limit(60, '')->value().'.'.$extension;
    }
}
