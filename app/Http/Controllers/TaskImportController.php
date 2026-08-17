<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Import\TaskImporter;
use App\Services\Scheduling\ProjectScheduler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Importar el plan desde una hoja de cálculo.
 *
 * **Siempre hay vista previa.** Escribir directo lo que trae un archivo es cómo
 * un plan de sesenta tareas se convierte en ciento veinte porque alguien oprimió
 * dos veces. La vista previa cuesta un clic y evita el desastre.
 */
final class TaskImportController extends Controller
{
    public function __construct(
        private readonly TaskImporter $importer,
        private readonly ProjectScheduler $scheduler,
    ) {}

    public function show(Project $project): View
    {
        $this->authorize('update', $project);

        return view('tasks.import', [
            'project' => $project,
            'preview' => null,
            'errors_list' => [],
        ]);
    }

    public function preview(Request $request, Project $project): View
    {
        $this->authorize('update', $project);

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:2048'],
        ]);

        $contents = (string) file_get_contents($request->file('file')->getRealPath());

        $result = $this->importer->forProject($project)->preview($contents);

        return view('tasks.import', [
            'project' => $project,
            'preview' => $result['rows'],
            'errors_list' => $result['errors'],
            'replace' => $request->boolean('replace'),
            // El contenido viaja de vuelta en un campo oculto para no tener que
            // guardar el archivo en disco entre las dos peticiones.
            'payload' => $contents,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'payload' => ['required', 'string', 'max:500000'],
            'replace' => ['nullable', 'boolean'],
        ]);

        $result = $this->importer->forProject($project)->preview($data['payload']);

        if ($result['rows'] === []) {
            return back()->with('error', __('import.nothing_to_import'));
        }

        $count = $this->importer->forProject($project)->import($project, $result['rows'], $request->boolean('replace'));

        $this->scheduler->reschedule($project->refresh());

        return redirect()
            ->route('projects.tasks.index', $project)
            ->with('status', __('import.done', ['count' => $count]));
    }
}
