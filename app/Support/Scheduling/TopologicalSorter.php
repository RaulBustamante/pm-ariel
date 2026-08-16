<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

/**
 * Orden en el que se pueden calcular las tareas: ninguna antes que sus
 * predecesoras.
 *
 * Se implementa con Kahn — grados de entrada y una cola — porque además de ser
 * lineal deja lo que sobra al final: si algo queda sin poder salir, esas tareas
 * son exactamente las que están enredadas en uno o más ciclos. Sobre ese resto
 * se busca el ciclo concreto con una búsqueda en profundidad, que es barata
 * porque ya se sabe dónde mirar.
 */
final class TopologicalSorter
{
    /**
     * @return list<string> Identificadores de hoja, en orden calculable.
     *
     * @throws CircularDependencyException
     */
    public function sort(ScheduleNetwork $network): array
    {
        $inDegree = [];
        $queue = [];

        foreach ($network->leaves() as $task) {
            $inDegree[$task->id] = count($network->incomingOf($task->id));

            if ($inDegree[$task->id] === 0) {
                $queue[] = $task->id;
            }
        }

        $ordered = [];

        while ($queue !== []) {
            $id = array_shift($queue);
            $ordered[] = $id;

            foreach ($network->outgoingOf($id) as $link) {
                $successor = $link->successorId;

                if (--$inDegree[$successor] === 0) {
                    $queue[] = $successor;
                }
            }
        }

        if (count($ordered) === count($inDegree)) {
            return $ordered;
        }

        // PHP convierte a entero cualquier clave de arreglo que parezca número,
        // y los identificadores de tarea vienen de la base como "12". Sin volver
        // a texto aquí, el ciclo saldría con enteros y no coincidiría con nada.
        $stuck = array_map(
            strval(...),
            array_keys(array_filter($inDegree, static fn (int $degree): bool => $degree > 0)),
        );

        throw new CircularDependencyException($this->findCycle($network, $stuck));
    }

    /**
     * El primer ciclo que se encuentre entre las tareas atoradas, cerrado: el
     * identificador inicial se repite al final para que se lea como el círculo
     * que es.
     *
     * @param  list<string>  $candidates
     * @return list<string>
     */
    private function findCycle(ScheduleNetwork $network, array $candidates): array
    {
        $inCandidates = array_flip($candidates);
        $state = [];
        $stack = [];

        $walk = function (string $id) use (&$walk, $network, $inCandidates, &$state, &$stack): ?array {
            $state[$id] = 'visiting';
            $stack[] = $id;

            foreach ($network->outgoingOf($id) as $link) {
                $next = $link->successorId;

                if (! isset($inCandidates[$next])) {
                    continue;
                }

                if (($state[$next] ?? null) === 'visiting') {
                    // Se cierra el círculo: se recorta la pila desde donde
                    // apareció por primera vez y se cierra repitiéndolo.
                    $start = array_search($next, $stack, strict: true);

                    return [...array_slice($stack, (int) $start), $next];
                }

                if (! isset($state[$next]) && ($found = $walk($next)) !== null) {
                    return $found;
                }
            }

            array_pop($stack);
            $state[$id] = 'done';

            return null;
        };

        foreach ($candidates as $id) {
            if (isset($state[$id])) {
                continue;
            }

            if (($cycle = $walk($id)) !== null) {
                return $cycle;
            }
        }

        // Inalcanzable con Kahn: si quedaron tareas atoradas, hay un ciclo. El
        // respaldo existe para no devolver una lista vacía si algún día cambia
        // la forma de elegir los candidatos.
        return [...$candidates, $candidates[0] ?? ''];
    }
}
