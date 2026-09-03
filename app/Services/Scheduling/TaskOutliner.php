<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Project;
use App\Models\Task;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

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
     * Mueve varias tareas dentro de un mismo padre en una sola operación.
     *
     * Existe porque el camino de una en una no escala: cada indentada es un
     * recálculo del proyecto y una recarga de la lista, así que acomodar cinco
     * subtareas seguidas costaba cinco de las dos cosas. Aquí las cinco se
     * mueven dentro de una transacción y el proyecto se recalcula una vez.
     *
     * A diferencia de `indent()`, el padre lo escoge el usuario y no es
     * forzosamente la hermana de arriba. Eso abre dos formas de romper el árbol
     * que ahí eran imposibles, y las dos se cierran aquí:
     *
     * - **Meter una tarea dentro de sí misma o de una de sus descendientes.**
     *   Dejaría una rama huérfana del recorrido de `outline()` y colgada de un
     *   ciclo: la tarea no volvería a aparecer en pantalla.
     * - **Mover una rama y sus hijas por separado.** Si el usuario marca un
     *   paquete y también algo de adentro, lo que quiere es mover el paquete
     *   completo; mover las dos cosas lo desarmaría. Las descendientes de otra
     *   seleccionada se ignoran, porque ya viajan con su padre.
     *
     * El orden relativo de lo seleccionado se conserva: llegan al final del
     * nuevo padre en el mismo orden en que se leen en la lista.
     *
     * @param  Collection<int, Task>  $tasks  En el orden en que deben quedar.
     * @return int Cuántas se movieron de verdad.
     *
     * @throws InvalidArgumentException Si el destino crearía un ciclo.
     */
    public function reparent(Project $project, Collection $tasks, ?Task $newParent): int
    {
        $tasks = $tasks->filter(fn (Task $task): bool => $task->project_id === $project->id)->values();

        if ($tasks->isEmpty()) {
            return 0;
        }

        if ($newParent !== null) {
            $this->assertBelongs($project, $newParent);

            // El ciclo se busca **subiendo desde el destino**, no bajando desde
            // lo seleccionado. Es la misma pregunta dicha al revés —«¿el nuevo
            // padre cuelga de alguna de las que se están moviendo?»— y así se
            // contesta con un solo recorrido en vez de uno por tarea.
            //
            // Bajar desde el destino seria la pregunta equivocada: buscaria a
            // la tarea entre las hijas de su futuro padre, que es precisamente
            // donde todavia no esta.
            $blocked = $this->ancestorIds($project, $newParent);
            $blocked[] = (int) $newParent->id;

            foreach ($tasks as $task) {
                if (! in_array((int) $task->id, $blocked, true)) {
                    continue;
                }

                throw new InvalidArgumentException((int) $task->id === (int) $newParent->id
                    ? __('tasks.bulk_into_itself')
                    : __('tasks.bulk_cycle', ['name' => $newParent->name]));
            }
        }

        $selected = $tasks->map(fn (Task $task): int => (int) $task->id)->all();

        // Lo que ya viaja con su padre no se mueve aparte.
        $movable = $tasks->reject(
            fn (Task $task): bool => array_intersect($this->ancestorIds($project, $task), $selected) !== [],
        )->values();

        if ($movable->isEmpty()) {
            return 0;
        }

        $newParentId = $newParent?->id;

        // Los identificadores y los padres se sacan **antes** de escribir nada,
        // porque lo que sigue no vuelve a tocar estos modelos.
        //
        // `outline()` le cuelga a cada tarea un `outline_depth` que no es una
        // columna, y eso la deja marcada como modificada: un `$task->update()`
        // sobre uno de esos modelos intenta guardar el atributo de adorno junto
        // con lo demás y la base rechaza la escritura completa. Aquí se escribe
        // por identificador para no depender de con qué venía cargado el
        // modelo — el que llama tiene derecho a pasar el esquema tal como se
        // dibuja en pantalla, que es justo lo que hace la lista.
        $moves = $movable->map(fn (Task $task): array => [
            'id' => (int) $task->id,
            'from' => $task->parent_id,
        ])->all();

        return DB::transaction(function () use ($moves, $newParentId, $project): int {
            $ids = array_column($moves, 'id');

            $next = (int) Task::query()
                ->where('project_id', $project->id)
                ->where('parent_id', $newParentId)
                ->whereNotIn('id', $ids)
                ->max('sort_order') + 1;

            $touchedParents = [];

            foreach ($moves as $move) {
                $touchedParents[] = $move['from'];

                Task::query()->whereKey($move['id'])->update([
                    'parent_id' => $newParentId,
                    'sort_order' => $next++,
                    'updated_at' => now(),
                ]);
            }

            // Los padres de origen se quedan con huecos en la numeración. No
            // rompe nada, pero deja el orden dependiendo del id en vez de lo
            // que el usuario decidió la próxima vez que mueva algo ahí.
            foreach (array_unique(array_merge($touchedParents, [$newParentId]), SORT_REGULAR) as $parentId) {
                $this->resequence($project->id, $parentId, 0);
            }

            return count($moves);
        });
    }

    /**
     * Los ids de los padres de una tarea, subiendo hasta la raíz.
     *
     * Se resuelve con una sola consulta al proyecto y un recorrido en memoria:
     * una consulta por nivel convertiría un árbol hondo en decenas de viajes a
     * la base, y el proyecto entero cabe de sobra en memoria — la lista misma
     * ya lo carga completo en `outline()`.
     *
     * El `in_array` del recorrido no es defensa de más: si un ciclo alcanzara a
     * guardarse, sin él este `while` no termina nunca.
     *
     * @return list<int>
     */
    private function ancestorIds(Project $project, Task $task): array
    {
        $parents = Task::query()
            ->where('project_id', $project->id)
            ->get(['id', 'parent_id'])
            ->mapWithKeys(fn (Task $row): array => [(int) $row->id => $row->parent_id]);

        $found = [];
        $current = $task->parent_id;

        while ($current !== null && ! in_array((int) $current, $found, true)) {
            $found[] = (int) $current;
            $current = $parents->get((int) $current);
        }

        return $found;
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
