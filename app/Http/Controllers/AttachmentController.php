<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\Project;
use App\Models\Task;
use App\Services\Scheduling\TaskOutliner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Subir y descargar archivos de una tarea.
 *
 * Tres reglas, y las tres son de seguridad, no de comodidad:
 *
 * 1. **Lista blanca de extensiones**, no lista negra. Una lista negra siempre
 *    olvida algo — `.phtml`, `.svg` con script dentro, la extensión de moda del
 *    año que viene. La blanca solo deja pasar lo que se decidió a propósito.
 * 2. **El nombre en disco lo genera el sistema.** El que trae el archivo se
 *    guarda aparte, solo para mostrarlo.
 * 3. **Los archivos viven fuera de `public/`** y se sirven por una ruta que
 *    comprueba permisos. Un archivo alcanzable por su dirección es un archivo
 *    que cualquiera con el enlace puede abrir, tenga acceso al proyecto o no.
 */
final class AttachmentController extends Controller
{
    /** 25 MB, como dice el plan. */
    private const MAX_KILOBYTES = 25600;

    /** @var list<string> */
    private const ALLOWED = [
        'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
        'csv', 'txt', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'zip',
    ];

    public function __construct(
        private readonly TaskOutliner $outliner,
    ) {}

    public function store(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        $request->validate([
            'file' => ['required', 'file', 'max:'.self::MAX_KILOBYTES, 'mimes:'.implode(',', self::ALLOWED)],
        ], [
            'file.mimes' => __('attachments.not_allowed', ['list' => implode(', ', self::ALLOWED)]),
            'file.max' => __('attachments.too_big', ['mb' => (int) (self::MAX_KILOBYTES / 1024)]),
        ]);

        $file = $request->file('file');

        // Nombre generado: sin relación con el que trae el archivo, y con la
        // extensión ya validada contra la lista blanca.
        $extension = strtolower((string) $file->getClientOriginalExtension());
        $stored = "attachments/{$project->id}/".Str::uuid()->toString().'.'.$extension;

        Storage::disk('local')->put($stored, (string) file_get_contents($file->getRealPath()));

        Attachment::query()->create([
            'project_id' => $project->id,
            'task_id' => $task->id,
            'original_name' => $file->getClientOriginalName(),
            'stored_path' => $stored,
            'mime_type' => (string) $file->getClientMimeType(),
            'extension' => $extension,
            'size_bytes' => (int) $file->getSize(),
            'checksum' => hash_file('sha256', $file->getRealPath()) ?: null,
            'uploaded_by' => Auth::id(),
        ]);

        return back()->with('status', __('attachments.uploaded'));
    }

    /**
     * La descarga pasa por aquí y no por una dirección pública: es lo que hace
     * que un archivo del proyecto ajeno no se abra con solo tener el enlace.
     */
    public function download(Project $project, Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $project);

        abort_unless($attachment->project_id === $project->id, 404);
        abort_unless(Storage::disk('local')->exists($attachment->stored_path), 404);

        return Storage::disk('local')->download($attachment->stored_path, $attachment->original_name);
    }

    public function destroy(Project $project, Attachment $attachment): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($attachment->project_id === $project->id, 404);

        // El registro se borra en suave; el archivo se va de verdad. Conservar
        // bytes que ya nadie puede alcanzar solo llena el disco.
        Storage::disk('local')->delete($attachment->stored_path);
        $attachment->delete();

        return back()->with('status', __('attachments.deleted'));
    }
}
