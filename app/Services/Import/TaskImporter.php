<?php

declare(strict_types=1);

namespace App\Services\Import;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskDependency;
use App\Support\Scheduling\DependencyExpression;
use App\Support\Scheduling\DurationParser;
use App\Support\Scheduling\ProjectDurations;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Traer un plan desde una hoja de cálculo.
 *
 * Casi todos los proyectos de Ariel ya viven en Excel. Pedirle a alguien que
 * recapture sesenta renglones a mano es la forma más segura de que nunca use el
 * sistema — el importador no es una comodidad, es la puerta de entrada.
 *
 * **Dos pasadas, siempre.** La primera solo lee y valida sin escribir nada; la
 * segunda escribe, dentro de una transacción. Un importador que va escribiendo
 * mientras lee deja medio plan cargado cuando el renglón 40 está mal, y limpiar
 * eso a mano es peor que no haber importado.
 */
final class TaskImporter
{
    /** Encabezados que se reconocen, en español y en inglés. */
    private const HEADERS = [
        'name' => ['nombre', 'tarea', 'name', 'task'],
        'duration' => ['duracion', 'duración', 'duration'],
        'predecessors' => ['depende de', 'predecesoras', 'predecessors', 'depends on'],
        'level' => ['nivel', 'level', 'esquema', 'outline'],
        'owner' => ['responsable', 'owner'],
        'cost' => ['costo', 'cost'],
        'progress' => ['avance', 'progress'],
    ];

    private DurationParser $durations;

    public function __construct(?DurationParser $durations = null)
    {
        $this->durations = $durations ?? new DurationParser;
    }

    /**
     * Fija la jornada del proyecto al que se va a importar. Sin esto, "3d" en un
     * proyecto de nueve horas diarias entraría como tres jornadas de ocho.
     */
    public function forProject(Project $project): self
    {
        return new self(ProjectDurations::for($project));
    }

    /**
     * Lee y valida sin escribir. Devuelve lo que se importaría y los problemas
     * encontrados, para poder mostrarlos antes de tocar nada.
     *
     * @return array{rows: list<array<string, mixed>>, errors: list<string>, headers: array<string, int>}
     */
    public function preview(string $contents): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($contents)) ?: [];

        if (count($lines) < 2) {
            return ['rows' => [], 'errors' => [__('import.needs_header_and_rows')], 'headers' => []];
        }

        $separator = $this->detectSeparator($lines[0]);
        $headers = $this->mapHeaders(str_getcsv($lines[0], $separator, '"', '\\'));

        if (! isset($headers['name'])) {
            return ['rows' => [], 'errors' => [__('import.no_name_column')], 'headers' => $headers];
        }

        $rows = [];
        $errors = [];

        foreach (array_slice($lines, 1) as $index => $line) {
            $number = $index + 2;

            if (trim($line) === '') {
                continue;
            }

            $cells = str_getcsv($line, $separator, '"', '\\');
            $name = trim((string) ($cells[$headers['name']] ?? ''));

            if ($name === '') {
                $errors[] = __('import.row_without_name', ['row' => $number]);

                continue;
            }

            $duration = trim((string) ($cells[$headers['duration'] ?? -1] ?? ''));
            $minutes = 0;

            if ($duration !== '') {
                try {
                    $minutes = $this->durations->toMinutes($duration);
                } catch (InvalidArgumentException $exception) {
                    $errors[] = __('import.bad_duration', ['row' => $number, 'reason' => $exception->getMessage()]);

                    continue;
                }
            }

            $rows[] = [
                'row' => $number,
                'name' => $name,
                'duration_minutes' => $minutes,
                'duration' => $duration,
                'level' => max(0, (int) ($cells[$headers['level'] ?? -1] ?? 0)),
                'predecessors' => trim((string) ($cells[$headers['predecessors'] ?? -1] ?? '')),
                'cost' => (float) str_replace([',', '$'], '', (string) ($cells[$headers['cost'] ?? -1] ?? '0')),
                'progress' => min(100.0, max(0.0, (float) ($cells[$headers['progress'] ?? -1] ?? 0))),
            ];
        }

        if ($rows === [] && $errors === []) {
            $errors[] = __('import.no_rows');
        }

        return ['rows' => $rows, 'errors' => $errors, 'headers' => $headers];
    }

    /**
     * Escribe lo que la vista previa aprobó. Todo o nada.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return int Tareas creadas.
     */
    public function import(Project $project, array $rows, bool $replace = false): int
    {
        return DB::transaction(function () use ($project, $rows, $replace): int {
            if ($replace) {
                Task::query()->where('project_id', $project->id)->delete();
            }

            $base = (int) Task::query()->where('project_id', $project->id)->max('sort_order');

            // El nivel del renglón arma la jerarquía: nivel 1 cuelga del último
            // renglón de nivel 0 que se haya visto. Es como se ve en Excel.
            $lastAtLevel = [];
            $created = [];

            foreach ($rows as $index => $row) {
                $level = (int) $row['level'];

                $task = new Task;
                $task->fill([
                    'project_id' => $project->id,
                    'parent_id' => $level > 0 ? ($lastAtLevel[$level - 1] ?? null) : null,
                    'name' => (string) $row['name'],
                    'duration_minutes' => (int) $row['duration_minutes'],
                    'sort_order' => $base + $index + 1,
                    'cost' => (float) $row['cost'],
                    'percent_complete' => (float) $row['progress'],
                ]);
                $task->save();

                $lastAtLevel[$level] = $task->id;

                // Los niveles más profundos dejan de ser válidos al subir.
                foreach (array_keys($lastAtLevel) as $known) {
                    if ($known > $level) {
                        unset($lastAtLevel[$known]);
                    }
                }

                $created[(string) $row['row']] = $task->id;
                $created[(string) ($index + 1)] = $task->id;
            }

            $this->linkDependencies($project, $rows, $created);

            return count($rows);
        });
    }

    /**
     * Las dependencias se ligan al final, cuando todas las tareas existen: una
     * fila puede depender de otra que viene más abajo en el archivo.
     *
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, int>  $created
     */
    private function linkDependencies(Project $project, array $rows, array $created): void
    {
        $expression = new DependencyExpression($this->durations);

        foreach ($rows as $index => $row) {
            $text = (string) $row['predecessors'];

            if ($text === '') {
                continue;
            }

            $reference = (string) ($index + 1);

            if (! array_key_exists($reference, $created)) {
                continue;
            }

            $successorId = $created[$reference];

            try {
                $links = $expression->parse($text, $created);
            } catch (InvalidArgumentException) {
                // Una dependencia ilegible no tumba la importación completa: la
                // tarea entra sin liga y el usuario la corrige en la Lista, que
                // es mucho menos costoso que rechazar el archivo entero.
                continue;
            }

            foreach ($links as $link) {
                if ($link['predecessor_id'] === $successorId) {
                    continue;
                }

                TaskDependency::query()->updateOrCreate(
                    [
                        'predecessor_id' => $link['predecessor_id'],
                        'successor_id' => $successorId,
                        'type' => $link['type'],
                    ],
                    ['project_id' => $project->id, 'lag_minutes' => $link['lag_minutes']],
                );
            }
        }
    }

    /**
     * Coma o punto y coma: Excel en español exporta con punto y coma, y culpar
     * al usuario por eso sería culparlo por su configuración regional.
     */
    private function detectSeparator(string $header): string
    {
        return substr_count($header, ';') > substr_count($header, ',') ? ';' : ',';
    }

    /**
     * @param  list<string|null>  $cells
     * @return array<string, int>
     */
    private function mapHeaders(array $cells): array
    {
        $map = [];

        foreach ($cells as $position => $cell) {
            $normalized = mb_strtolower(trim((string) $cell));

            foreach (self::HEADERS as $field => $aliases) {
                if (in_array($normalized, $aliases, strict: true)) {
                    $map[$field] = $position;
                }
            }
        }

        return $map;
    }
}
