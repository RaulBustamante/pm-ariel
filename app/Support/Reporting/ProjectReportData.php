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
    /*
    | Las medidas de la hoja del diagrama.
    |
    | El diagrama va **girado** en el PDF: dompdf fija la orientación una sola vez
    | para todo el documento, así que no hay forma de intercalar una hoja
    | apaisada. Girado 90° sobre la hoja vertical, el eje del tiempo pasa a usar
    | el lado largo del papel —de 390 a 660 puntos, un 70 % más— y el diagrama
    | deja de verse apretado.
    |
    | Una carta vertical con los márgenes de la plantilla deja 694 × 853 px de
    | área útil. Girado, el alto del dibujo ocupa el ancho de la hoja:
    |   alto  = 42 + 22 × 26 + 4 = 618 px  ≤ 694 ✓
    |   ancho = 165 + 660        = 825 px  ≤ 853 ✓
    | Cambiar cualquiera de estos tres números obliga a rehacer esa cuenta.
    */
    private const GANTT_WIDTH = 660;

    private const NAME_WIDTH = 165;

    /**
     * Alto útil de la hoja, en píxeles a 96 ppp.
     *
     * Girado el diagrama, este número es el **ancho** de la carta menos los
     * márgenes de la plantilla: (215.9 mm − 32 mm) ÷ 25.4 × 96 ≈ 695, con quince
     * de holgura para que un redondeo no lo empuje fuera.
     */
    private const SHEET_HEIGHT = 680;

    /** Por debajo de esto el nombre de la tarea deja de leerse impreso. */
    private const MIN_ROW_HEIGHT = 9;

    /** Reserva: solo se usa cuando no hay tareas que medir. */
    private const ROWS_PER_PAGE = 22;

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
            'ganttImages' => null,
            'ganttSheet' => null,
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

        [$rowHeight, $rowsPerPage] = $this->fitRows($scheduled->count());

        $chunks = $scheduled->chunk($rowsPerPage);

        return [
            ...$data,
            'ganttLayout' => $layout,
            'ganttPages' => $chunks,
            'ganttImages' => $chunks->map(
                fn ($pageTasks): string => $this->svgAsImage($layout, $pageTasks, $project, $rowHeight),
            ),
            // Girado, el ancho que ocupa en la hoja es el **alto** natural del
            // dibujo, y por eso se calcula con el renglón ya comprimido.
            'ganttSheet' => [
                'width' => GanttLayout::HEADER_HEIGHT + ($chunks->first()?->count() ?? 0) * $rowHeight + 4,
            ],
        ];
    }

    /**
     * Cuánto se comprime el renglón para que el proyecto entre en una hoja.
     *
     * Un diagrama repartido en tres hojas deja de leerse como diagrama: se
     * pierde justo lo que uno va a buscar, que es ver el proyecto completo de un
     * golpe. Así que primero se intenta que quepa entero, comprimiendo.
     *
     * **Pero se comprime hasta un límite y no más.** Por debajo de nueve píxeles
     * el nombre de la tarea deja de leerse impreso, y una hoja ilegible es peor
     * que dos legibles. Pasado ese punto se vuelve a paginar. El límite está
     * aquí escrito y no repartido en la plantilla, para que se pueda subir o
     * bajar en un solo lugar cuando alguien lo mida en papel de verdad.
     *
     * @return array{float, int} alto de renglón y renglones por hoja
     */
    private function fitRows(int $tasks): array
    {
        $usable = self::SHEET_HEIGHT - GanttLayout::HEADER_HEIGHT - 4;

        if ($tasks < 1) {
            return [(float) GanttLayout::ROW_HEIGHT, self::ROWS_PER_PAGE];
        }

        $needed = $usable / $tasks;

        if ($needed >= self::MIN_ROW_HEIGHT) {
            // Cabe entero. No se estira más allá del alto normal: un proyecto de
            // seis tareas con renglones de cien píxeles se ve absurdo.
            return [min((float) GanttLayout::ROW_HEIGHT, $needed), $tasks];
        }

        return [(float) self::MIN_ROW_HEIGHT, (int) floor($usable / self::MIN_ROW_HEIGHT)];
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
    private function svgAsImage(GanttLayout $layout, $pageTasks, Project $project, float $rowHeight): string
    {
        $svg = view('reports._gantt-page', [
            'layout' => $layout,
            'pageTasks' => $pageTasks,
            'project' => $project,
            'nameWidth' => self::NAME_WIDTH,
            'rotate' => true,
            'compactRowHeight' => $rowHeight,
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
