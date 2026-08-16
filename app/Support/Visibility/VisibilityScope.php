<?php

declare(strict_types=1);

namespace App\Support\Visibility;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde vive la regla "un jefe ve todo lo de su cadena hacia abajo".
 *
 * Todas las Policies consumen esta clase. Si algún día la regla cambia, cambia
 * una vez; si estuviera repetida en cada Policy, cambiaría casi siempre.
 *
 * La expansión es iterativa por niveles y usa solo el constructor de consultas:
 * nada dependiente del motor de base de datos mientras no se confirme cuál corre
 * en producción (riesgo R-11, decisión D-011).
 */
final class VisibilityScope
{
    private const CACHE_TTL_SECONDS = 300;

    /**
     * Tope de profundidad. La jerarquía real tiene cuatro niveles; este número
     * existe para que un ciclo accidental en los datos no se vuelva un bucle
     * infinito silencioso.
     */
    private const MAX_DEPTH = 20;

    /**
     * El usuario mismo más toda su cadena de mando hacia abajo, recursivamente.
     *
     * @return list<int>
     */
    public function visibleUserIds(User $user): array
    {
        return Cache::remember(
            $this->cacheKey($user),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->expandChain($user->id),
        );
    }

    public function canSeeUser(User $viewer, User $target): bool
    {
        if ($viewer->is($target)) {
            return true;
        }

        return in_array($target->id, $this->visibleUserIds($viewer), strict: true);
    }

    /**
     * Un proyecto es visible si el usuario es miembro, o si su dueño o alguno de
     * sus miembros cae dentro de la cadena de mando del usuario.
     */
    public function canSeeProject(User $user, Project $project): bool
    {
        $reachable = $this->visibleUserIds($user);

        if (in_array($project->owner_id, $reachable, strict: true)) {
            return true;
        }

        return $project->members()
            ->whereIn('users.id', $reachable)
            ->exists();
    }

    /**
     * Restringe una consulta de proyectos a lo que el usuario puede ver. Se usa
     * en los listados, para no traer de la base lo que después habría que ocultar.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeProjects(Builder $query, User $user): Builder
    {
        $reachable = $this->visibleUserIds($user);

        return $query->where(function (Builder $inner) use ($reachable): void {
            $inner->whereIn('owner_id', $reachable)
                ->orWhereHas('members', fn (Builder $members) => $members->whereIn('users.id', $reachable));
        });
    }

    public function forget(User $user): void
    {
        Cache::forget($this->cacheKey($user));
    }

    /**
     * Se invalida en bloque porque un cambio de jefe altera la visibilidad de
     * toda la rama, no solo la de los dos usuarios involucrados.
     */
    public function flush(): void
    {
        Cache::forget('visibility:generation');
        Cache::increment('visibility:generation');
    }

    /**
     * @return list<int>
     */
    private function expandChain(int $rootUserId): array
    {
        $reachable = [$rootUserId => true];
        $frontier = [$rootUserId];

        for ($depth = 0; $depth < self::MAX_DEPTH && $frontier !== []; $depth++) {
            $next = DB::table('user_hierarchy')
                ->whereIn('manager_id', $frontier)
                ->whereNull('effective_to')
                ->whereNull('deleted_at')
                ->pluck('subordinate_id')
                ->all();

            $frontier = [];

            foreach ($next as $subordinateId) {
                $id = (int) $subordinateId;

                // El conjunto de visitados es lo que hace inofensivo un ciclo
                // accidental en los datos.
                if (! isset($reachable[$id])) {
                    $reachable[$id] = true;
                    $frontier[] = $id;
                }
            }
        }

        return array_map(intval(...), array_keys($reachable));
    }

    private function cacheKey(User $user): string
    {
        $generation = Cache::get('visibility:generation', 0);

        return "visibility:{$generation}:user:{$user->id}";
    }
}
