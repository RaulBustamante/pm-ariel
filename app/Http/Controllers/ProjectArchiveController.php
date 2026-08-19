<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Documents\ProjectArchive;
use RuntimeException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * El expediente archivado: el proyecto entero en un solo paquete.
 *
 * Se descarga y **se borra del servidor al terminar de mandarlo**. Un ZIP de
 * cien megas por cada vez que alguien oprime el botón llenaría el disco sin que
 * nadie lo note, y el disco de esta máquina tiene menos espacio libre del que
 * conviene.
 */
final class ProjectArchiveController extends Controller
{
    public function __construct(
        private readonly ProjectArchive $archive,
    ) {}

    public function download(Project $project): BinaryFileResponse
    {
        $this->authorize('view', $project);

        try {
            $path = $this->archive->build($project);
        } catch (RuntimeException $exception) {
            abort(500, $exception->getMessage());
        }

        return response()
            ->download($path, $project->code.'-expediente.zip')
            ->deleteFileAfterSend();
    }
}
