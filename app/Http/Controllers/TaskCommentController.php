<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Services\Scheduling\TaskOutliner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Los comentarios de una tarea.
 *
 * Escribir uno **no recalcula el plan**, a diferencia de casi todo lo demás que
 * se guarda en una tarea. Es a propósito: comentar es lo más barato que alguien
 * puede hacer aquí, y si costara un recálculo del proyecto entero la gente
 * dejaría de hacerlo justo cuando más falta hace.
 */
final class TaskCommentController extends Controller
{
    public function __construct(
        private readonly TaskOutliner $outliner,
    ) {}

    public function store(Request $request, Project $project, Task $task): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        TaskComment::query()->create([
            'task_id' => $task->id,
            'project_id' => $project->id,
            'body' => trim($data['body']),
        ]);

        return back()->with('status', __('tasks.comment_added'));
    }

    /**
     * Solo su autor lo borra.
     *
     * Un comentario es lo que alguien dijo; que otro lo pueda quitar convierte
     * el historial en algo que depende de quién tenga permisos, y entonces deja
     * de servir para lo único que sirve. El borrado es suave y queda en la
     * bitácora, así que tampoco desaparece del todo.
     */
    public function destroy(Project $project, Task $task, TaskComment $comment): RedirectResponse
    {
        $this->authorize('update', $project);
        $this->outliner->assertBelongs($project, $task);

        abort_unless($comment->task_id === $task->id, 404);
        abort_unless($comment->canBeDeletedBy(auth()->user()), 403);

        $comment->delete();

        return back()->with('status', __('tasks.comment_deleted'));
    }
}
