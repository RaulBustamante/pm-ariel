<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * El filtro que comparten las cinco vistas.
 *
 * **Cambiar de pestaña conserva el filtro.** Si cada vista tuviera el suyo, la
 * gente filtraría en la Lista, saltaría al Gantt, vería todo otra vez y
 * concluiría que el filtro no sirve. Vive en la dirección — no en la sesión —
 * para que además el enlace se pueda mandar por correo y muestre lo mismo.
 */
final readonly class TaskFilter
{
    public function __construct(
        public string $search = '',
        public bool $onlyCritical = false,
        public bool $onlyMine = false,
        public string $progress = 'all',
        public bool $onlyWaiting = false,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            search: trim((string) $request->query('q', '')),
            onlyCritical: $request->boolean('critical'),
            onlyMine: $request->boolean('mine'),
            progress: (string) $request->query('progress', 'all'),
            onlyWaiting: $request->boolean('waiting'),
        );
    }

    public function isActive(): bool
    {
        return $this->search !== ''
            || $this->onlyCritical
            || $this->onlyMine
            || $this->onlyWaiting
            || $this->progress !== 'all';
    }

    /**
     * Los parámetros con los que reconstruir esta misma vista en otra pestaña.
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'q' => $this->search,
            'critical' => $this->onlyCritical ? 1 : '',
            'mine' => $this->onlyMine ? 1 : '',
            'progress' => $this->progress === 'all' ? '' : $this->progress,
            'waiting' => $this->onlyWaiting ? 1 : '',
        ], fn (string|int $value): bool => $value !== '');
    }

    /**
     * @param  Collection<int, Task>  $tasks
     * @return Collection<int, Task>
     */
    public function apply(Collection $tasks, ?int $userId = null): Collection
    {
        if (! $this->isActive()) {
            return $tasks;
        }

        return $tasks->filter(function (Task $task) use ($userId): bool {
            if ($this->search !== '' && ! str_contains(mb_strtolower((string) $task->name), mb_strtolower($this->search))) {
                return false;
            }

            // Un resumen se conserva si alguna hija pasa el filtro; eso lo
            // resuelve quien llama, porque aquí no se conoce el árbol.
            if ($this->onlyCritical && ! $task->is_critical) {
                return false;
            }

            if ($this->onlyMine && $task->owner_id !== $userId) {
                return false;
            }

            // «Qué traigo detenido» es la revisión para la que existe este
            // filtro, y es una pregunta aparte del avance: lo que espera puede
            // estar al 0 % o al 90 %.
            if ($this->onlyWaiting && ! $task->isWaiting()) {
                return false;
            }

            $progress = (float) $task->percent_complete;

            return match ($this->progress) {
                'todo' => $progress <= 0,
                'doing' => $progress > 0 && $progress < 100,
                'done' => $progress >= 100,
                default => true,
            };
        })->values();
    }
}
