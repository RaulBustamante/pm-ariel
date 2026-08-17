<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Lo que **yo** tengo esta semana, de todos mis proyectos juntos.
 *
 * El corte semanal contesta «cómo va este proyecto». Esto contesta otra
 * pregunta, la que uno se hace al abrir el sistema en la mañana: «¿qué me toca
 * hoy?». Nadie trabaja en un proyecto a la vez, y tener que entrar a cinco para
 * armar mentalmente la lista de pendientes es exactamente el trabajo que el
 * sistema debería estar haciendo.
 *
 * Se resuelve en **una consulta sobre todas las tareas visibles**, no llamando
 * al corte semanal proyecto por proyecto: con cinco proyectos eso serían cinco
 * recorridos completos para armar una lista de doce renglones.
 */
final class MyWeek
{
    /** Cuántos renglones caben en una tarjeta de inicio sin volverse una tabla. */
    private const PER_LIST = 8;

    /**
     * @param  Collection<int, Project>  $projects  los que el usuario alcanza a ver
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable,
     *     late: Collection<int, Task>, due: Collection<int, Task>,
     *     next: Collection<int, Task>, closed: Collection<int, Task>,
     *     counts: array{late: int, due: int, next: int, closed: int}
     * }
     */
    public function for(User $user, Collection $projects, ?CarbonImmutable $asOf = null): array
    {
        $now = $asOf ?? CarbonImmutable::now();
        $from = $now->startOfDay()->startOfWeek();
        $to = $now->startOfDay()->endOfWeek();

        if ($projects->isEmpty()) {
            return $this->empty($from, $to);
        }

        /*
        | Solo lo mío.
        |
        | «Mío» es ser el responsable de la tarea, no ser miembro del proyecto.
        | Si fuera lo segundo, el gerente de un proyecto de cincuenta tareas
        | abriría el inicio y encontraría las cincuenta — que es la lista que
        | tenía antes de que existiera este sistema.
        |
        | Las tareas sin responsable se incluyen **solo en las atrasadas**: una
        | tarea vencida que nadie está empujando es de quien esté mirando.
        */
        $mine = Task::query()
            ->with(['project:id,code,name'])
            ->whereIn('project_id', $projects->pluck('id'))
            ->where('is_summary', false)
            ->where(fn ($query) => $query->where('owner_id', $user->id)->orWhereNull('owner_id'))
            ->orderBy('early_finish')
            ->get();

        $ownedByMe = fn (Task $task): bool => (int) $task->owner_id === $user->id;

        $open = $mine->filter(fn (Task $t): bool => (float) $t->percent_complete < 100);

        $late = $open
            ->filter(fn (Task $t): bool => $t->early_finish !== null && $t->early_finish->lt($now))
            ->values();

        $due = $open
            ->filter($ownedByMe)
            ->reject(fn (Task $t): bool => $late->contains('id', $t->id))
            ->filter(
                fn (Task $t): bool => $t->early_finish !== null
                    && $t->early_finish->betweenIncluded($now, $to),
            )
            ->values();

        $next = $open
            ->filter($ownedByMe)
            ->reject(fn (Task $t): bool => $late->contains('id', $t->id) || $due->contains('id', $t->id))
            ->filter(
                fn (Task $t): bool => $t->early_start !== null
                    && $t->early_start->betweenIncluded($to, $to->addWeek()),
            )
            ->values();

        $closed = $mine
            ->filter($ownedByMe)
            ->filter(
                fn (Task $t): bool => $t->actual_finish !== null
                    && $t->actual_finish->betweenIncluded($from, $to),
            )
            ->values();

        return [
            'from' => $from,
            'to' => $to,
            'late' => $late->take(self::PER_LIST),
            'due' => $due->take(self::PER_LIST),
            'next' => $next->take(self::PER_LIST),
            'closed' => $closed->take(self::PER_LIST),
            // Los conteos van completos aunque la lista se corte: «3 de 17» dice
            // algo muy distinto de «3», y esconder la diferencia sería mentir
            // por omisión sobre cuánto falta.
            'counts' => [
                'late' => $late->count(),
                'due' => $due->count(),
                'next' => $next->count(),
                'closed' => $closed->count(),
            ],
        ];
    }

    /**
     * @return array{
     *     from: CarbonImmutable, to: CarbonImmutable,
     *     late: Collection<int, Task>, due: Collection<int, Task>,
     *     next: Collection<int, Task>, closed: Collection<int, Task>,
     *     counts: array{late: int, due: int, next: int, closed: int}
     * }
     */
    private function empty(CarbonImmutable $from, CarbonImmutable $to): array
    {
        /** @var Collection<int, Task> $none */
        $none = collect();

        return [
            'from' => $from,
            'to' => $to,
            'late' => $none,
            'due' => $none,
            'next' => $none,
            'closed' => $none,
            'counts' => ['late' => 0, 'due' => 0, 'next' => 0, 'closed' => 0],
        ];
    }
}
