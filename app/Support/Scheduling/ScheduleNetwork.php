<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use InvalidArgumentException;

/**
 * Las tareas y sus ligas, indexadas para que el cálculo no ande buscando.
 *
 * **Solo se programan las hojas.** Una tarea resumen no tiene fechas propias:
 * las hereda de sus hijas (`SummaryRollup`). Darle fechas propias sería permitir
 * que un resumen diga que termina antes que el trabajo que resume.
 */
final class ScheduleNetwork
{
    /** @var array<string, TaskNode> */
    private array $tasks = [];

    /** @var list<DependencyLink> */
    private array $links = [];

    /** @var array<string, list<DependencyLink>> */
    private array $incoming = [];

    /** @var array<string, list<DependencyLink>> */
    private array $outgoing = [];

    /** @var array<string, list<string>> */
    private array $childrenOf = [];

    /**
     * @param  list<TaskNode>  $tasks
     * @param  list<DependencyLink>  $links
     */
    public function __construct(array $tasks, array $links = [])
    {
        foreach ($tasks as $task) {
            if (isset($this->tasks[$task->id])) {
                throw new InvalidArgumentException("Identificador de tarea repetido: {$task->id}.");
            }

            $this->tasks[$task->id] = $task;
            $this->incoming[$task->id] = [];
            $this->outgoing[$task->id] = [];
        }

        foreach ($tasks as $task) {
            if ($task->parentId === null) {
                continue;
            }

            if (! isset($this->tasks[$task->parentId])) {
                throw new InvalidArgumentException("La tarea {$task->id} cuelga de {$task->parentId}, que no existe.");
            }

            $this->childrenOf[$task->parentId][] = $task->id;
        }

        foreach ($links as $link) {
            foreach ([$link->predecessorId, $link->successorId] as $id) {
                if (! isset($this->tasks[$id])) {
                    throw new InvalidArgumentException("La liga menciona la tarea {$id}, que no existe.");
                }
            }

            // Una liga contra un resumen sería ambigua: ¿contra su primera hija,
            // contra la última, contra todas? Se prohíbe en vez de adivinar.
            foreach ([$link->predecessorId, $link->successorId] as $id) {
                if (isset($this->childrenOf[$id])) {
                    throw new InvalidArgumentException(
                        "La tarea {$id} es un resumen y no puede tener dependencias: liga sus hojas."
                    );
                }
            }

            $this->links[] = $link;
            $this->incoming[$link->successorId][] = $link;
            $this->outgoing[$link->predecessorId][] = $link;
        }
    }

    /**
     * @return array<string, TaskNode>
     */
    public function tasks(): array
    {
        return $this->tasks;
    }

    public function task(string $id): TaskNode
    {
        return $this->tasks[$id] ?? throw new InvalidArgumentException("No existe la tarea {$id}.");
    }

    public function has(string $id): bool
    {
        return isset($this->tasks[$id]);
    }

    /**
     * @return list<DependencyLink>
     */
    public function links(): array
    {
        return $this->links;
    }

    /**
     * @return list<DependencyLink>
     */
    public function incomingOf(string $id): array
    {
        return $this->incoming[$id] ?? [];
    }

    /**
     * @return list<DependencyLink>
     */
    public function outgoingOf(string $id): array
    {
        return $this->outgoing[$id] ?? [];
    }

    public function isSummary(string $id): bool
    {
        return isset($this->childrenOf[$id]);
    }

    /**
     * @return list<string>
     */
    public function childrenOf(string $id): array
    {
        return $this->childrenOf[$id] ?? [];
    }

    /**
     * Las hojas, que son las únicas que el cálculo programa.
     *
     * @return list<TaskNode>
     */
    public function leaves(): array
    {
        return array_values(array_filter(
            $this->tasks,
            fn (TaskNode $task): bool => ! $this->isSummary($task->id),
        ));
    }

    /**
     * Las de primer nivel, en el orden en que se capturaron.
     *
     * @return list<string>
     */
    public function roots(): array
    {
        $roots = [];

        foreach ($this->tasks as $task) {
            if ($task->parentId === null) {
                $roots[] = $task->id;
            }
        }

        return $roots;
    }
}
