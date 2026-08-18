<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un comentario en una tarea.
 *
 * **No es la nota de la tarea.** La nota es el estado de hoy —lo que hay que
 * saber para trabajarla— y se reescribe; esto es la conversación, y crece. La
 * pregunta que se hace tres meses después no es «qué dice la tarea» sino «qué
 * pasó aquí», y solo la segunda tiene respuesta si hay historial.
 *
 * No se edita a propósito: un comentario reescribible deja de ser historial.
 */
#[Fillable(['task_id', 'project_id', 'body'])]
class TaskComment extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Quién lo escribió. Lo llena `RecordsAudit`.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * ¿Este usuario puede borrarlo?
     *
     * Solo su autor. Un comentario es lo que alguien dijo; que otro lo pueda
     * quitar convierte el historial en algo que depende de quién tenga permisos,
     * y entonces deja de servir para lo único que sirve.
     */
    public function canBeDeletedBy(?User $user): bool
    {
        return $user !== null && $this->created_by !== null && $this->created_by === $user->id;
    }
}
