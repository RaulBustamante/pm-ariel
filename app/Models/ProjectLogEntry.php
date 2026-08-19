<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Support\Documents\ProjectLog;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Un renglón de cualquiera de los catorce registros del PMI.
 *
 * Una incidencia, una decisión, una lección, un punto de una minuta. El tipo lo
 * dice `document_code`, y por eso este modelo **no sabe de incidencias**: si
 * empezara a saberlo —un método `isOverdue()` que solo aplica a las acciones, un
 * estado especial para los cambios— dejaría de servir para los catorce y la
 * decisión de tener una sola tabla se perdería por dentro aunque siga siendo una
 * sola por fuera.
 *
 * Lo que cambia por tipo vive en `config/pmi_logs.php` y lo resuelve
 * {@see ProjectLog}.
 *
 * @property Carbon|null $occurred_on
 * @property Carbon|null $due_on
 */
#[Fillable([
    'project_id', 'document_code', 'sequence', 'occurred_on', 'title',
    'detail', 'status', 'owner_id', 'due_on', 'priority', 'outcome',
])]
class ProjectLogEntry extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_on' => 'date',
            'due_on' => 'date',
            'sequence' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Quién responde por este renglón. No es quien lo capturó: la mitad del
     * valor de un registro es que cada asunto tenga dueño, y el que escribe casi
     * nunca es el que resuelve.
     *
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * El código que se cita en una junta: `INC-004`.
     *
     * El prefijo sale de la configuración y no de la base, así que corregirlo no
     * obliga a reescribir renglones. Se rellena a tres dígitos porque un
     * registro ordenado por texto pondría INC-10 antes que INC-9, y a nadie le
     * parece un defecto del sistema: le parece que el suyo desapareció.
     */
    public function reference(): string
    {
        $prefix = (string) config("pmi_logs.types.{$this->document_code}.prefix", '#');

        return sprintf('%s-%03d', $prefix, $this->sequence);
    }
}
