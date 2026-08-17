<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mover tareas dentro del esquema: indentar, desindentar, subir y bajar.
 *
 * Es la operación que más se usa al construir una WBS y la que más fácil deja
 * el árbol inconsistente si se hace a mano en cada pantalla. Vive aquí una sola
 * vez, con las reglas explícitas.
 *
 * La regla que gobierna todo: **una tarea se indenta bajo la hermana que tiene
 * justo encima.** Sin hermana arriba no hay bajo quién colgarse, y el intento
 * simplemente no hace nada — en vez de inventar un padre.
 */
final class TaskOutliner
{
    public function indent(Task $task): bool
    {
        $siblings = $this->siblingsOf($task);
        $position = $siblings->search(fn (Task $candidate): bool => $candidate->is($task));

        if ($position === false || $position === 0) {
            return false;
        }

        $newParent = $siblings[$position - 1];

        // Una tarea con hijas se lleva su rama completa: indentar un paquete
        // mueve el paquete, no lo desarma.
        return DB::transaction(function () use ($task, $newParent): bool {
            $task->update([
                'parent_id' => $newParent->id,
                'sort_order' => (int) Task::query()
                    ->where('parent_id', $newParent->id)
                    ->max('sort_order') + 1,
            ]);

            return true;
        });
    }

    public function outdent(Task $task): bool
    {
        if ($task->parent_id === null) {
            return false;
        }

        $parent = Task::query()->find($task->parent_id);

        if ($parent === null) {
            return false;
        }

        return DB::transaction(function () use ($task, $parent): bool {
            $task->update([
                'parent_id' => $parent->parent_id,
                // Queda justo debajo de quien era su padre, que es donde el
                // usuario espera verla después de desindentar.
                'sort_order' => $parent->sort_order + 1,
            ]);

            $this->resequence($task->project_id, $parent->parent_id, $task->id);

            return true;
        });
    }

    public function move(Task $task, int $offset): bool
    {
        $siblings = $this->siblingsOf($task)->values();
        $position = $siblings->search(fn (Task $candidate): bool => $candidate->is($task));

        if ($position === false) {
            return false;
        }

        $target = $position + $offset;

        if ($target < 0 || $target >= $siblings->count()) {
            return false;
        }

        return DB::transaction(function () use ($siblings, $position, $target): bool {
            $ordered = $siblings->all();
            [$ordered[$position], $ordered[$target]] = [$ordered[$target], $ordered[$position]];

            foreach ($ordered as $index => $sibling) {
                $sibling->update(['sort_order' => $index]);
            }

            return true;
        });
    }

    /**
     * Reordena a las hermanas dejando hueco para la recién llegada. Sin esto,
     * dos tareas comparten `sort_order` y el orden entre ellas depende del id
     * — que es estable pero no es lo que el usuario decidió.
     */
    private function resequence(int $projectId, ?int $parentId, int $keepId): void
    {
        $siblings = Task::query()
            ->where('project_id', $projectId)
            ->where('parent_id', $parentId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($siblings as $index => $sibling) {
            $sibling->update(['sort_order' => $index]);
        }
    }

    /**
     * @return Collection<int, Task>
     */
    private function siblingsOf(Task $task): Collection
    {
        return Task::query()
            ->where('project_id', $task->project_id)
            ->where('parent_id', $task->parent_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();
    }

    /**
     * El árbol aplanado en el orden en que se lee, con la profundidad a la mano.
     *
     * @return Collection<int, Task>
     */
    public function outline(Project $project): Collection
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->with('owner')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $byParent = $tasks->groupBy(fn (Task $task): int => (int) $task->parent_id);

        $ordered = new Collection;

        $walk = function (int $parentId, int $depth) use (&$walk, $byParent, $ordered): void {
            foreach ($byParent->get($parentId, new Collection) as $task) {
                $task->setAttribute('outline_depth', $depth);
                $ordered->push($task);
                $walk((int) $task->id, $depth + 1);
            }
        };

        $walk(0, 0);

        if ($ordered->count() !== $tasks->count()) {
            // Un padre borrado dejaría huérfanas fuera del recorrido. Mostrarlas
            // al final es preferible a que desaparezcan de la pantalla.
            $ordered = $ordered->concat($tasks->diff($ordered)->each(
                fn (Task $task) => $task->setAttribute('outline_depth', 0),
            ));
        }

        return $ordered->values();
    }

    /**
     * Índice de código WBS y número de renglón hacia el id, para poder escribir
     * dependencias como "12FS+2d".
     *
     * @param  Collection<int, Task>  $outline
     * @return array<string, int>
     */
    public function referenceIndex(Collection $outline): array
    {
        $index = [];

        foreach ($outline as $position => $task) {
            $index[(string) ($position + 1)] = (int) $task->id;

            if (filled($task->wbs_code)) {
                $index[(string) $task->wbs_code] = (int) $task->id;
            }
        }

        return $index;
    }

    /**
     * La ruta trae proyecto y tarea por separado. Sin esta comprobación, cambiar
     * el número en la barra de direcciones alcanzaría la tarea de otro proyecto
     * — uno al que sí se tiene acceso, así que la Policy no lo detendría.
     *
     * Responde 404 y no una excepción: para quien pide esa dirección, la tarea
     * no existe ahí. Un error 500 diría que el sistema se rompió, y no es cierto.
     */
    public function assertBelongs(Project $project, Task $task): void
    {
        abort_unless($task->project_id === $project->id, 404);
    }
}
