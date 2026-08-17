<?php

declare(strict_types=1);

namespace App\Support\Scheduling;

use InvalidArgumentException;

/**
 * Las dependencias escritas como en cualquier herramienta de programación:
 * `12`, `12FS+2d`, `15SS`, `8FF-1d`, o varias separadas por coma.
 *
 * Es la forma más rápida que existe de capturar una red de tareas, y la razón
 * por la que quien viene de otra herramienta se siente en casa de inmediato.
 * Escribir a mano vale la pena precisamente porque teclear `12FS+2d` toma dos
 * segundos y hacerlo con el ratón toma quince.
 *
 * Lo que el usuario teclea es el **código WBS** o el número de renglón, no el id
 * interno: nadie sabe que la tarea se llama 4173 en la base.
 */
final class DependencyExpression
{
    public function __construct(
        private readonly DurationParser $durations = new DurationParser,
    ) {}

    /**
     * @param  array<string, int>  $idsByReference  Código WBS o renglón → id de tarea.
     * @return list<array{predecessor_id: int, type: string, lag_minutes: int}>
     *
     * @throws InvalidArgumentException
     */
    public function parse(string $expression, array $idsByReference): array
    {
        $trimmed = trim($expression);

        if ($trimmed === '') {
            return [];
        }

        $links = [];
        $seen = [];

        foreach (preg_split('/[,;]+/', $trimmed) ?: [] as $piece) {
            $piece = trim($piece);

            if ($piece === '') {
                continue;
            }

            $links[] = $this->parseOne($piece, $idsByReference, $seen);
        }

        return $links;
    }

    /**
     * Devuelve la expresión a partir de las ligas guardadas, para volver a
     * mostrarla en el campo. Si esto no coincidiera con lo que el usuario
     * escribió, el campo se sentiría "poseído": escribes una cosa y aparece otra.
     *
     * @param  list<array{reference: string, type: string, lag_minutes: int}>  $links
     */
    public function format(array $links): string
    {
        $parts = [];

        foreach ($links as $link) {
            $piece = $link['reference'];

            // FS sin retraso es el caso normal y no se escribe: "12" ya lo dice.
            if ($link['type'] !== DependencyType::FinishToStart->value || $link['lag_minutes'] !== 0) {
                $piece .= $link['type'];
            }

            if ($link['lag_minutes'] !== 0) {
                $piece .= ($link['lag_minutes'] > 0 ? '+' : '-')
                    .$this->durations->toHuman(abs($link['lag_minutes']));
            }

            $parts[] = $piece;
        }

        return implode(', ', $parts);
    }

    /**
     * @param  array<string, int>  $idsByReference
     * @param  array<int, true>  $seen
     * @return array{predecessor_id: int, type: string, lag_minutes: int}
     */
    private function parseOne(string $piece, array $idsByReference, array &$seen): array
    {
        $pattern = '/^([0-9][0-9.]*)\s*(FS|SS|FF|SF)?\s*([+-]\s*[^\s]+)?$/i';

        if (preg_match($pattern, str_replace(' ', '', $piece), $parts) !== 1) {
            throw new InvalidArgumentException(
                __('tasks.dependency_unreadable', ['piece' => $piece]),
            );
        }

        $reference = rtrim($parts[1], '.');

        if (! isset($idsByReference[$reference])) {
            throw new InvalidArgumentException(
                __('tasks.dependency_unknown_task', ['reference' => $reference]),
            );
        }

        $id = $idsByReference[$reference];

        // Dos veces la misma predecesora no significa nada distinto de una vez,
        // y en cambio duplica el cálculo y confunde al leer.
        if (isset($seen[$id])) {
            throw new InvalidArgumentException(
                __('tasks.dependency_repeated', ['reference' => $reference]),
            );
        }

        $seen[$id] = true;

        $type = strtoupper($parts[2] ?? '') ?: DependencyType::FinishToStart->value;

        $lag = 0;

        // El grupo del retraso es opcional: si la expresión fue "12FS" sin más,
        // ni siquiera existe en el resultado.
        if (isset($parts[3])) {
            $sign = str_starts_with($parts[3], '-') ? -1 : 1;
            $lag = $sign * $this->durations->toMinutes(substr($parts[3], 1));
        }

        return ['predecessor_id' => $id, 'type' => $type, 'lag_minutes' => $lag];
    }
}
