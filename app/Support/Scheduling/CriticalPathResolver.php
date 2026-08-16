<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

/**
 * Qué tareas no pueden retrasarse ni un minuto, y por dónde van las cadenas.
 *
 * Crítica es la que tiene holgura total menor o igual a cero. El "o igual"
 * importa: con una fecha rígida vencida la holgura es negativa, y esa tarea es
 * más crítica todavía, no menos.
 *
 * **Puede haber más de una ruta crítica.** Es lo normal en cuanto dos cadenas
 * empatan, y esconderlo mostrando solo una hace que la gente proteja la ruta
 * equivocada. Por eso se devuelven todas, no la primera.
 */
final class CriticalPathResolver
{
    /**
     * @param  list<string>  $order
     * @param  array<string, int>  $totalFloat
     * @return list<string>
     */
    public function criticalTasks(array $order, array $totalFloat): array
    {
        return array_values(array_filter(
            $order,
            static fn (string $id): bool => ($totalFloat[$id] ?? 1) <= 0,
        ));
    }

    /**
     * Las cadenas completas de tareas críticas ligadas entre sí, cada una desde
     * su arranque hasta su final.
     *
     * @param  list<string>  $order
     * @param  array<string, int>  $totalFloat
     * @return list<list<string>>
     */
    public function criticalPaths(ScheduleNetwork $network, array $order, array $totalFloat): array
    {
        $isCritical = static fn (string $id): bool => ($totalFloat[$id] ?? 1) <= 0;

        $critical = $this->criticalTasks($order, $totalFloat);

        if ($critical === []) {
            return [];
        }

        $inPath = array_flip($critical);

        // Arranques: tareas críticas sin ninguna predecesora crítica.
        $starts = array_values(array_filter($critical, function (string $id) use ($network, $inPath): bool {
            foreach ($network->incomingOf($id) as $link) {
                if (isset($inPath[$link->predecessorId])) {
                    return false;
                }
            }

            return true;
        }));

        $paths = [];

        $walk = function (string $id, array $sofar) use (&$walk, $network, $isCritical, &$paths): void {
            $sofar[] = $id;

            $continued = false;

            foreach ($network->outgoingOf($id) as $link) {
                if ($isCritical($link->successorId)) {
                    $continued = true;
                    $walk($link->successorId, $sofar);
                }
            }

            if (! $continued) {
                $paths[] = $sofar;
            }
        };

        foreach ($starts as $start) {
            $walk($start, []);
        }

        return $paths;
    }
}
