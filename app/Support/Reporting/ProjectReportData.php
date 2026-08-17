<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Project;
use App\Models\Task;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Scheduling\GanttLayout;
use App\Support\Scheduling\ProjectDurations;
use Illuminate\Support\Collection;

/**
 * Todo lo que necesita el reporte, armado en un solo lugar.
 *
 * Vivía dentro del controlador como un método privado, y la prueba que revisa
 * el HTML del PDF tenía que construir el mismo arreglo a mano. Cada campo nuevo
 * rompía la prueba por una razón falsa —faltaba una llave en la copia, no había
 * nada mal en el reporte— y, peor, la prueba pasaba a verificar una versión de
 * los datos que ya no era la que se manda a dirección.
 *
 * Aquí lo arman los dos, así que la prueba mira lo mismo que el usuario recibe.
 */
final class ProjectReportData
{
    /** Ancho útil para el diagrama en carta vertical, después de los nombres. */
    private const GANTT_WIDTH = 390;

    /** Renglones por hoja: ninguna barra queda partida entre dos páginas. */
    private const ROWS_PER_PAGE = 24;

    public function __construct(
        private readonly TaskOutliner $outliner,
        private readonly ProjectAdvisor $advisor,
        private readonly ProjectDashboard $dashboard,
        private readonly FindingDigest $digest,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function for(Project $project, bool $complete = false): array
    {
        $project->loadMissing(['charter.sponsor', 'owner', 'orgUnit']);

        $tasks = $this->outliner->outline($project);
        $findings = $project->findings()->with(['task', 'resource'])->get();

        $data = [
            'project' => $project,
            'charter' => $project->charter,
            'tasks' => $tasks,
            'durations' => ProjectDurations::for($project),
            'findings' => $findings,
            'digest' => $this->digest->group($findings),
            'kpis' => $this->dashboard->kpis($project),
            'light' => $this->advisor->light($findings),
            'lastRun' => $project->scheduleRuns()->first(),
            'generatedAt' => now(),
            'ganttPages' => null,
            'ganttLayout' => null,
        ];

        if (! $complete) {
            return $data;
        }

        $scheduled = $tasks
            ->filter(fn ($task): bool => $task->early_start !== null && $task->early_finish !== null)
            ->values();

        if ($scheduled->isEmpty()) {
            return $data;
        }

        // Ancho fijo: en pantalla el diagrama se desplaza a lo ancho, pero una
        // hoja no. Se recalculan los píxeles por día para que el proyecto
        // entero quepa en vez de recortarlo por la derecha.
        $layout = new GanttLayout($scheduled, GanttLayout::ZOOM_WEEK, fitWidth: self::GANTT_WIDTH);
        $chunks = $scheduled->chunk(self::ROWS_PER_PAGE);

        return [
            ...$data,
            'ganttLayout' => $layout,
            'ganttPages' => $chunks,
            'ganttImages' => $chunks->map(
                fn ($pageTasks): string => $this->svgAsImage($layout, $pageTasks, $project),
            ),
        ];
    }

    /**
     * El diagrama, convertido a una imagen que dompdf sí sabe dibujar.
     *
     * **dompdf no dibuja `<svg>` escrito dentro del HTML.** Lo ignora en
     * silencio: la hoja sale con el espacio en blanco y el archivo se genera sin
     * un solo error. Se comprobó midiendo las operaciones de dibujo del PDF, no
     * leyendo la documentación, porque la biblioteca de SVG sí está instalada y
     * eso hace pensar que funciona.
     *
     * Lo que sí dibuja es un SVG referenciado como imagen. Se incrusta como
     * `data:` y no como archivo en disco para no dejar temporales que alguien
     * tenga que limpiar, ni depender de permisos de escritura.
     *
     * @param  Collection<int, Task>  $pageTasks
     */
    private function svgAsImage(GanttLayout $layout, $pageTasks, Project $project): string
    {
        $svg = view('reports._gantt-page', [
            'layout' => $layout,
            'pageTasks' => $pageTasks,
            'project' => $project,
        ])->render();

        // El espacio de nombres es obligatorio cuando el SVG viaja solo. Dentro
        // del HTML lo hereda; como imagen no hay de quién heredarlo, y sin él la
        // biblioteca lo rechaza.
        if (! str_contains($svg, 'xmlns=')) {
            $svg = preg_replace('/<svg\b/', '<svg xmlns="http://www.w3.org/2000/svg"', $svg, 1) ?? $svg;
        }

        return 'data:image/svg+xml;base64,'.base64_encode(trim($svg));
    }
}
