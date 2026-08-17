<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\ProjectFinding;
use Illuminate\Support\Collection;

/**
 * Los avisos, agrupados por regla en vez de uno por uno.
 *
 * En pantalla tiene sentido listarlos sueltos: se atienden de uno en uno y cada
 * renglón lleva a su tarea. En un documento no. El reporte del proyecto de
 * ejemplo salía con veinticinco párrafos que decían exactamente lo mismo —«está
 * en la ruta crítica y no tiene responsable»— cada uno con su explicación
 * completa repetida abajo. Tres páginas para un solo hecho.
 *
 * Un director que abre eso no lee veinticinco párrafos: cierra el documento. Y
 * lo peor es que el hecho importante —que hay veinticinco— queda escondido
 * entre las repeticiones en vez de ser lo primero que salta.
 *
 * Aquí se dice una vez cuántas son, se nombran, y la explicación va una sola
 * vez.
 */
final class FindingDigest
{
    /**
     * @param  Collection<int, ProjectFinding>  $findings
     * @return list<array{
     *     rule: string, count: int, severity: string,
     *     headline: string, why: string, subjects: list<string>
     * }>
     */
    public function group(Collection $findings): array
    {
        $grouped = [];

        foreach ($findings->groupBy('rule') as $rule => $ofRule) {
            /** @var Collection<int, ProjectFinding> $ofRule */
            $first = $ofRule->first();

            if ($first === null) {
                continue;
            }

            $grouped[] = [
                'rule' => (string) $rule,
                'count' => $ofRule->count(),
                'severity' => (string) $first->severity,
                // Con un solo caso, el mensaje original ya nombra al implicado y
                // se lee mejor que un conteo de uno.
                'headline' => $ofRule->count() === 1
                    ? (string) $first->message
                    // El punto de la regla se cambia por guion bajo: en una
                    // clave de traducción el punto separa archivo de llave, y
                    // `advisor.rule_task.critical_without_owner` iría a buscar
                    // un arreglo anidado que no existe.
                    : __('reports.finding_group', [
                        'count' => $ofRule->count(),
                        'what' => __('advisor.rule_'.str_replace('.', '_', (string) $rule)),
                    ]),
                'why' => (string) $first->why,
                'subjects' => $ofRule->count() === 1 ? [] : $this->subjects($ofRule),
            ];
        }

        // Lo que amenaza la entrega primero, y a igualdad de gravedad lo que más
        // veces ocurre: un documento que empieza por lo menor entrena a la gente
        // a hojearlo.
        //
        // El orden sale de `SEVERITY_ORDER` del modelo y no de una lista escrita
        // aquí. Con una copia local, agregar una gravedad nueva la mandaría al
        // final en silencio —y una gravedad nueva se agrega justamente porque
        // importa.
        $weight = array_flip(ProjectFinding::SEVERITY_ORDER);

        usort($grouped, fn (array $a, array $b): int => [$weight[$a['severity']] ?? 99, -$a['count']]
            <=> [$weight[$b['severity']] ?? 99, -$b['count']]);

        return $grouped;
    }

    /**
     * A quién le pasa. Se cortan en doce: una lista de cuarenta nombres vuelve a
     * ser el muro que se quería evitar, y para eso está la pantalla.
     *
     * @param  Collection<int, ProjectFinding>  $ofRule
     * @return list<string>
     */
    private function subjects(Collection $ofRule): array
    {
        // Se pregunta por la llave y no por la relación: la llave es el dato que
        // dice a qué apunta el aviso, y sigue diciéndolo aunque la tarea esté
        // borrada en suave y la relación venga vacía.
        $names = $ofRule
            ->map(function (ProjectFinding $finding): ?string {
                if ($finding->task_id !== null) {
                    return $finding->task?->name;
                }

                return $finding->resource?->name;
            })
            ->filter()
            ->unique()
            ->values();

        $shown = $names->take(12)->all();

        if ($names->count() > 12) {
            $shown[] = __('reports.and_more', ['count' => $names->count() - 12]);
        }

        /** @var list<string> $shown */
        return $shown;
    }
}
