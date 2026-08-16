<?php

declare(strict_types=1);

namespace App\Support\Hierarchy;

use App\Models\User;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Único lugar donde se cambia de jefe.
 *
 * Tres reglas viven aquí y en ningún otro lado:
 *
 * 1. Un cambio **cierra** la relación anterior, no la borra. El histórico es lo
 *    que permite explicar meses después por qué alguien veía cierto proyecto.
 * 2. Nadie puede quedar por encima de su propio jefe. Un ciclo no rompe nada de
 *    inmediato — `VisibilityScope` lo tolera —, pero convierte el organigrama en
 *    algo que ya no se puede leer ni auditar.
 * 3. Al cambiar, se invalida el caché de visibilidad de toda la rama, no el de
 *    los dos usuarios involucrados.
 */
final class HierarchyManager
{
    private const MAX_DEPTH = 20;

    public function __construct(
        private readonly VisibilityScope $visibility,
    ) {}

    /**
     * El jefe vigente, o null si la persona no reporta a nadie.
     */
    public function managerOf(User $user): ?User
    {
        $managerId = DB::table('user_hierarchy')
            ->where('subordinate_id', $user->id)
            ->whereNull('effective_to')
            ->whereNull('deleted_at')
            ->value('manager_id');

        return $managerId === null ? null : User::query()->find($managerId);
    }

    /**
     * ¿Poner a $manager como jefe de $subordinate cerraría un ciclo?
     *
     * Se cumple si el propio subordinado ya está en la cadena de mando del
     * candidato, o si son la misma persona.
     */
    public function wouldCreateCycle(User $subordinate, User $manager): bool
    {
        if ($subordinate->is($manager)) {
            return true;
        }

        $current = $manager;

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            $current = $this->managerOf($current);

            if ($current === null) {
                return false;
            }

            if ($current->is($subordinate)) {
                return true;
            }
        }

        // Si se agotó la profundidad, ya hay un ciclo en los datos. Negarse es
        // lo correcto: agregar otra arista solo lo empeora.
        return true;
    }

    /**
     * Deja a $manager como jefe vigente de $subordinate. Pasar null lo deja sin
     * jefe. Devuelve false si el cambio cerraría un ciclo.
     */
    public function assign(User $subordinate, ?User $manager): bool
    {
        if ($manager !== null && $this->wouldCreateCycle($subordinate, $manager)) {
            return false;
        }

        $current = $this->managerOf($subordinate);

        if ($current?->id === $manager?->id) {
            return true;
        }

        $today = Carbon::today();

        DB::transaction(function () use ($subordinate, $manager, $today): void {
            DB::table('user_hierarchy')
                ->where('subordinate_id', $subordinate->id)
                ->whereNull('effective_to')
                ->whereNull('deleted_at')
                ->update([
                    'effective_to' => $today,
                    'updated_by' => Auth::id(),
                    'updated_at' => now(),
                ]);

            if ($manager === null) {
                return;
            }

            // Corregirse el mismo día es normal: "lo moví mal, regrésalo". La
            // relación es única por (jefe, subordinado, fecha de inicio), así
            // que ahí se reabre la fila en vez de insertar una que chocaría.
            DB::table('user_hierarchy')->updateOrInsert(
                [
                    'manager_id' => $manager->id,
                    'subordinate_id' => $subordinate->id,
                    'effective_from' => $today,
                ],
                [
                    'effective_to' => null,
                    'deleted_at' => null,
                    'created_by' => Auth::id(),
                    'updated_by' => Auth::id(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        });

        $this->visibility->flush();

        return true;
    }
}
