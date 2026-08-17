<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Services\Scheduling\TaskOutliner;
use App\Support\Scheduling\GanttLayout;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El Gantt.
 *
 * Se dibuja en SVG generado en el servidor, sin biblioteca de terceros y sin
 * JavaScript para el trazo. Tres razones: se imprime bien —que es la mitad del
 * valor de un Gantt en una junta—, funciona con lector de pantalla si se le
 * ponen los rótulos, y no ata el proyecto a una dependencia de terceros que
 * habría que mantener durante años.
 */
final class GanttController extends Controller
{
    public function __construct(
        private readonly TaskOutliner $outliner,
    ) {}

    public function show(Request $request, Project $project): View
    {
        $this->authorize('view', $project);

        $tasks = $this->outliner->outline($project)
            ->filter(fn (Task $task): bool => $task->early_start !== null && $task->early_finish !== null)
            ->values();

        $zoom = (string) $request->query('zoom', GanttLayout::ZOOM_WEEK);

        $layout = new GanttLayout($tasks, $zoom);

        return view('gantt.show', [
            'project' => $project,
            'tasks' => $tasks,
            'layout' => $layout,
            'zoom' => $layout->zoom,
            'dependencies' => $project->taskDependencies()->get(),
        ]);
    }
}
