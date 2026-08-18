<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Project;
use App\Models\ProjectLogEntry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * El motor de los registros que crecen durante el proyecto.
 *
 * **Uno para los catorce.** Incidencias, decisiones, cambios, lecciones,
 * minutas, acciones, supuestos, mediciones: los catorce tienen la misma forma
 * —una fecha, un número que se cita, qué pasó, quién responde y en qué estado
 * está—, así que son una sola tabla con un tipo, no catorce tablas.
 *
 * Lo que sí cambia de verdad entre uno y otro vive en `config/pmi_logs.php`: el
 * prefijo del número, el juego de estados y qué columnas opcionales aplican. Una
 * solicitud de cambio se aprueba o se rechaza; una lección no. Forzarles a los
 * catorce el mismo «abierto / cerrado» obligaría a la gente a traducir el estado
 * real al único que ofrece la pantalla, y ahí es donde un registro deja de
 * leerse y se vuelve un trámite.
 *
 * Agregar el decimoquinto registro cuesta cuatro líneas de configuración y sus
 * textos. Ninguna migración, ninguna pantalla.
 */
final class ProjectLog
{
    /**
     * ¿Este código es un registro y ya tiene su definición?
     *
     * Se piden las dos cosas, igual que en el motor narrativo: un documento
     * marcado como registro sin definición abriría una pantalla sin estados
     * posibles, que es peor que decir que todavía no está.
     */
    public function isLog(string $code): bool
    {
        return (string) config("pmi_documents.catalogue.{$code}.kind") === 'log'
            && is_array(config("pmi_logs.types.{$code}"));
    }

    /**
     * Los estados posibles de este registro, en el orden en que avanzan.
     *
     * @return list<string>
     */
    public function statuses(string $code): array
    {
        /** @var list<string> $values */
        $values = config("pmi_logs.statuses.{$this->statusSet($code)}.values", []);

        return $values;
    }

    /**
     * Los estados que significan «ya no hay nada que hacer».
     *
     * Vive en la configuración y no en cada pantalla: si cada una decidiera por
     * su cuenta qué es estar abierto, el tablero y el PDF acabarían contando
     * cosas distintas del mismo registro.
     *
     * @return list<string>
     */
    public function closedStatuses(string $code): array
    {
        /** @var list<string> $values */
        $values = config("pmi_logs.statuses.{$this->statusSet($code)}.closed", []);

        return $values;
    }

    /**
     * Qué columnas opcionales aplican: `owner`, `due`, `priority`, `outcome`.
     *
     * @return list<string>
     */
    public function fields(string $code): array
    {
        /** @var list<string> $fields */
        $fields = config("pmi_logs.types.{$code}.fields", []);

        return $fields;
    }

    /** ¿Este registro usa esta columna opcional? */
    public function uses(string $code, string $field): bool
    {
        return in_array($field, $this->fields($code), strict: true);
    }

    /**
     * Los renglones de un registro, del hecho más reciente al más viejo.
     *
     * El orden es por fecha del hecho y no por captura: un registro se lee para
     * saber qué pasó, no para saber en qué orden alguien se sentó a escribirlo.
     * El número desempata, para que dos hechos del mismo día salgan siempre en
     * el mismo orden y la lista no baile entre dos cargas.
     *
     * @param  array{status?: ?string, owner?: ?int, q?: ?string}  $filters
     * @return Collection<int, ProjectLogEntry>
     */
    public function entries(Project $project, string $code, array $filters = []): Collection
    {
        $query = ProjectLogEntry::query()
            ->with('owner')
            ->where('project_id', $project->id)
            ->where('document_code', $code);

        $status = $filters['status'] ?? null;

        if (is_string($status) && in_array($status, $this->statuses($code), strict: true)) {
            $query->where('status', $status);
        }

        if (($filters['owner'] ?? null) !== null) {
            $query->where('owner_id', (int) $filters['owner']);
        }

        $term = trim((string) ($filters['q'] ?? ''));

        if ($term !== '') {
            // Se busca en lo que la gente recuerda —el título, el detalle y el
            // desenlace—, no en el número: para el número está el orden.
            $query->where(function ($inner) use ($term): void {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

                $inner->where('title', 'like', $like)
                    ->orWhere('detail', 'like', $like)
                    ->orWhere('outcome', 'like', $like);
            });
        }

        return $query->orderByDesc('occurred_on')->orderByDesc('sequence')->get();
    }

    /**
     * Cuántos hay y cuántos siguen abiertos.
     *
     * Se cuenta sobre el registro completo y **no sobre lo filtrado**: un
     * contador que cambia al filtrar deja de contestar «¿cómo va esto?» y pasa a
     * contestar «¿cuántos coinciden con lo que escribí?», que casi nunca es la
     * pregunta.
     *
     * @return array{total: int, open: int, overdue: int}
     */
    public function summary(Project $project, string $code): array
    {
        $closed = $this->closedStatuses($code);

        $base = ProjectLogEntry::query()
            ->where('project_id', $project->id)
            ->where('document_code', $code);

        $total = (clone $base)->count();
        $open = (clone $base)->whereNotIn('status', $closed)->count();

        // Vencido solo tiene sentido donde hay fecha compromiso, y solo cuenta
        // lo que sigue abierto: una acción entregada tarde ya no es un pendiente.
        $overdue = $this->uses($code, 'due')
            ? (clone $base)->whereNotIn('status', $closed)->whereDate('due_on', '<', now())->count()
            : 0;

        return ['total' => $total, 'open' => $open, 'overdue' => $overdue];
    }

    /**
     * Da de alta un renglón y le asigna su número.
     *
     * El número se asigna **dentro de una transacción con bloqueo**, por la
     * misma razón que la versión de un documento emitido (7.1): sin eso, dos
     * personas registrando una incidencia a la vez leerían el mismo «va en la
     * 7», las dos intentarían crear la 8 y una fallaría por el índice único
     * después de haber escrito su texto.
     *
     * @param  array<string, mixed>  $input
     */
    public function record(Project $project, string $code, array $input): ProjectLogEntry
    {
        return DB::transaction(function () use ($project, $code, $input): ProjectLogEntry {
            $last = ProjectLogEntry::query()
                ->withTrashed()
                ->where('project_id', $project->id)
                ->where('document_code', $code)
                ->lockForUpdate()
                ->max('sequence');

            $data = $this->clean($code, $input);

            return ProjectLogEntry::query()->create([
                'project_id' => $project->id,
                'document_code' => $code,
                'sequence' => ((int) $last) + 1,
                'occurred_on' => $data['occurred_on'],
                'title' => $data['title'],
                'detail' => $data['detail'],
                'status' => $data['status'],
                'owner_id' => $data['owner_id'],
                'due_on' => $data['due_on'],
                'priority' => $data['priority'],
                'outcome' => $data['outcome'],
            ]);
        });
    }

    /**
     * Modifica un renglón.
     *
     * Todo se puede corregir menos el número: ya se citó en un correo o en una
     * junta, y reasignarlo haría que dos cosas distintas se llamen igual. La
     * fecha sí —quien anota el martes la incidencia del viernes se equivoca de
     * día con frecuencia—, y quién cambió qué queda en la bitácora de auditoría.
     *
     * @param  array<string, mixed>  $input
     */
    public function amend(ProjectLogEntry $entry, array $input): ProjectLogEntry
    {
        $entry->update($this->clean((string) $entry->document_code, $input));

        return $entry;
    }

    /**
     * Se queda solo con lo que **pertenece a este tipo de registro**.
     *
     * Filtrar aquí y no confiar en el formulario es lo que impide que una
     * petición armada a mano le ponga prioridad a una minuta o un estado que no
     * existe en su juego.
     *
     * @param  array<string, mixed>  $input
     * @return array{
     *     occurred_on: string, title: string, detail: ?string, status: string,
     *     owner_id: ?int, due_on: ?string, priority: ?string, outcome: ?string
     * }
     */
    private function clean(string $code, array $input): array
    {
        $statuses = $this->statuses($code);
        $status = (string) ($input['status'] ?? '');

        $data = [
            'occurred_on' => $this->text($input['occurred_on'] ?? null) ?? now()->toDateString(),
            'title' => trim((string) ($input['title'] ?? '')),
            'detail' => $this->text($input['detail'] ?? null),
            // Un estado desconocido cae al primero del juego en vez de guardarse:
            // un renglón con un estado que la pantalla no sabe pintar es
            // invisible aunque esté en la base.
            'status' => in_array($status, $statuses, strict: true) ? $status : ($statuses[0] ?? 'open'),
            'owner_id' => null,
            'due_on' => null,
            'priority' => null,
            'outcome' => null,
        ];

        if ($this->uses($code, 'owner') && ($input['owner_id'] ?? null) !== null) {
            $data['owner_id'] = (int) $input['owner_id'];
        }

        if ($this->uses($code, 'due')) {
            $data['due_on'] = $this->text($input['due_on'] ?? null);
        }

        /** @var list<string> $priorities */
        $priorities = config('pmi_logs.priorities', []);

        if ($this->uses($code, 'priority') && in_array($input['priority'] ?? null, $priorities, strict: true)) {
            $data['priority'] = (string) $input['priority'];
        }

        if ($this->uses($code, 'outcome')) {
            $data['outcome'] = $this->text($input['outcome'] ?? null);
        }

        return $data;
    }

    /**
     * Texto o nada. Una cadena vacía y «no escrito» son lo mismo para quien lee
     * el registro, y distinguirlos obligaría a comprobar las dos cosas en cada
     * pantalla.
     */
    private function text(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    private function statusSet(string $code): string
    {
        return (string) config("pmi_logs.types.{$code}.statuses", '');
    }
}
