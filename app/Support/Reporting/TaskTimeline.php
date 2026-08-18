<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\AuditLog;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Qué pasó en esta tarea: lo que la gente dijo y lo que el sistema registró, en
 * un solo hilo.
 *
 * Separados en dos listas obligan a leer las dos y a cruzarlas mentalmente por
 * fecha, y ahí se pierde justo lo que se busca: que el comentario «el proveedor
 * pidió dos días» está **al lado** del cambio de duración que alguien hizo esa
 * misma tarde. La respuesta a «¿qué pasó aquí?» es la mezcla, no cada mitad.
 *
 * Los cambios del sistema se traducen a los nombres de los campos que ya usa la
 * aplicación. Un renglón que dice `percent_complete` obliga a saber cómo se
 * llaman las columnas por dentro, y quien lee un historial no tiene por qué.
 */
final class TaskTimeline
{
    /**
     * Los campos que vale la pena anunciar, y con qué nombre.
     *
     * Deliberadamente **no son todos**. `wbs_code`, `early_start` o
     * `total_float_minutes` los reescribe el motor en cada recálculo: anunciarlos
     * llenaría el hilo de renglones que nadie provocó y enterraría los que sí.
     *
     * @var array<string, string>
     */
    private const FIELDS = [
        'name' => 'tasks.name',
        'duration_minutes' => 'tasks.duration',
        'percent_complete' => 'tasks.progress',
        'owner_id' => 'tasks.owner',
        'cost' => 'tasks.cost',
        'actual_cost' => 'evm.actual_cost',
        'description' => 'tasks.notes',
        'constraint_type' => 'tasks.constraint',
        'constraint_date' => 'tasks.constraint_date',
        'parent_id' => 'tasks.parent',
    ];

    /**
     * El hilo completo, de lo más reciente a lo más viejo.
     *
     * @return list<array{
     *     kind: string, at: CarbonInterface, who: string, body: ?string,
     *     event: ?string, fields: list<string>, comment: ?TaskComment
     * }>
     */
    public function for(Task $task, int $limit = 40): array
    {
        $entries = [];

        $comments = TaskComment::query()
            ->with('author')
            ->where('task_id', $task->id)
            ->latest('id')
            ->limit($limit)
            ->get();

        foreach ($comments as $comment) {
            $entries[] = [
                'kind' => 'comment',
                'at' => $comment->created_at ?? Carbon::now(),
                'who' => $this->nameOf($comment->author),
                'body' => (string) $comment->body,
                'event' => null,
                'fields' => [],
                'comment' => $comment,
            ];
        }

        $changes = AuditLog::query()
            ->where('auditable_type', Task::class)
            ->where('auditable_id', $task->id)
            ->with('user')
            ->latest('id')
            ->limit($limit)
            ->get();

        foreach ($changes as $change) {
            $fields = $this->fieldsOf($change);

            // Un cambio que solo tocó campos del motor no es un evento de
            // negocio. Enseñarlo sería anunciar «alguien recalculó el plan» una
            // vez por cada tarea y por cada recálculo.
            if ($fields === [] && $change->event === 'updated') {
                continue;
            }

            $entries[] = [
                'kind' => 'change',
                'at' => $change->created_at ?? Carbon::now(),
                'who' => $this->nameOf($change->user),
                'body' => null,
                'event' => (string) $change->event,
                'fields' => $fields,
                'comment' => null,
            ];
        }

        usort($entries, fn (array $a, array $b): int => $b['at'] <=> $a['at']);

        return array_slice($entries, 0, $limit);
    }

    /**
     * Quién, o «Sistema» cuando la cuenta ya no existe.
     *
     * Se pregunta por el objeto y no con `?->` sobre la relación: la cuenta se
     * anula al borrarse (`nullOnDelete`), pero el análisis estático tipa la
     * relación como no nula y marca el `?->` a la izquierda de un `??`.
     */
    private function nameOf(?User $user): string
    {
        return $user === null ? (string) __('audit.system') : (string) $user->name;
    }

    /**
     * Los nombres visibles de lo que cambió.
     *
     * @return list<string>
     */
    private function fieldsOf(AuditLog $change): array
    {
        $names = [];

        foreach (array_keys($change->new_values ?? []) as $field) {
            $key = self::FIELDS[$field] ?? null;

            if ($key !== null) {
                $names[] = __($key);
            }
        }

        return $names;
    }
}
