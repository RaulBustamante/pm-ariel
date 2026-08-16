<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

/**
 * La numeración jerárquica: 1, 1.1, 1.1.1, 1.2, 2…
 *
 * Se recalcula completa cada vez, y a propósito. Mantener los códigos "estables"
 * al reordenar suena amable hasta que la lista muestra 1, 3, 2 y nadie entiende
 * por qué. El código WBS describe una posición, no una identidad; la identidad
 * es el id de la tarea, que ese sí nunca cambia.
 */
final class WbsNumberer
{
    /**
     * @return array<string, string> id de tarea → código
     */
    public function number(ScheduleNetwork $network): array
    {
        $codes = [];

        $this->walk($network, $network->roots(), '', $codes);

        return $codes;
    }

    /**
     * @param  list<string>  $ids
     * @param  array<string, string>  $codes
     */
    private function walk(ScheduleNetwork $network, array $ids, string $prefix, array &$codes): void
    {
        $ordered = $this->ordered($network, $ids);

        foreach ($ordered as $index => $id) {
            $code = $prefix === '' ? (string) ($index + 1) : $prefix.'.'.($index + 1);
            $codes[$id] = $code;

            $children = $network->childrenOf($id);

            if ($children !== []) {
                $this->walk($network, $children, $code, $codes);
            }
        }
    }

    /**
     * Por `sortOrder`, y a igualdad por id. El desempate no es cosmético: sin él
     * dos tareas con el mismo orden intercambiarían códigos entre un cálculo y
     * el siguiente, y el plan impreso ayer dejaría de coincidir con el de hoy.
     *
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function ordered(ScheduleNetwork $network, array $ids): array
    {
        $sorted = $ids;

        usort($sorted, function (string $a, string $b) use ($network): int {
            $left = $network->task($a);
            $right = $network->task($b);

            return [$left->sortOrder, $left->id] <=> [$right->sortOrder, $right->id];
        });

        return $sorted;
    }
}
