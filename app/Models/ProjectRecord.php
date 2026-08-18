<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Un acta de aceptación: de un entregable, o del proyecto entero.
 *
 * **Inmutable después de firmarse**, igual que una línea base y que una versión
 * emitida. Toda su utilidad viene de eso: un documento de aceptación que se
 * puede editar después no prueba nada. Si lo que se aceptó cambió de verdad, se
 * levanta otra acta — y queda el rastro de que cambió.
 *
 * La guarda vive en el modelo y no en el controlador a propósito: si dependiera
 * de recordar comprobarlo en cada camino de escritura, el día que alguien
 * agregue un camino nuevo el acta dejará de ser inmutable sin que nada avise.
 *
 * @property Carbon|null $accepted_on
 * @property Carbon|null $signed_at
 */
#[Fillable([
    'project_id', 'document_code', 'sequence', 'subject', 'detail', 'task_id',
    'decision', 'reservations', 'accepted_by_name', 'accepted_by_role',
    'accepted_by_org', 'accepted_on',
])]
class ProjectRecord extends Model
{
    use RecordsAudit, SoftDeletes;

    public const ACCEPTED = 'accepted';

    public const ACCEPTED_WITH_RESERVATIONS = 'accepted_with_reservations';

    public const REJECTED = 'rejected';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_on' => 'date',
            'signed_at' => 'datetime',
            'sequence' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $record): void {
            // Firmar **es** un update, así que el propio acto de congelar tiene
            // que poder pasar. Lo que se prohíbe es tocar el contenido de una
            // que ya está firmada.
            if ($record->getOriginal('signed_at') === null) {
                return;
            }

            $touched = array_diff(array_keys($record->getDirty()), ['updated_at']);

            if ($touched !== []) {
                throw new RuntimeException(
                    'Un acta firmada no se edita. Levanta otra y di por qué cambió.',
                );
            }
        });

        static::deleting(function (self $record): void {
            if ($record->signed_at !== null && ! $record->isForceDeleting()) {
                throw new RuntimeException('Un acta firmada no se borra: es el registro de lo que se aceptó.');
            }
        });
    }

    public function isSigned(): bool
    {
        return $this->signed_at !== null;
    }

    /** El número que se cita en una junta: `ACE-003`. */
    public function reference(): string
    {
        $prefix = (string) config("pmi_records.types.{$this->document_code}.prefix", '#');

        return sprintf('%s-%03d', $prefix, $this->sequence);
    }

    /**
     * La huella del contenido, para poder demostrar que el acta archivada es la
     * misma que este renglón.
     *
     * Se calcula sobre lo que **dice** el acta y no sobre la fila entera: si
     * incluyera `updated_at`, tocar el renglón sin cambiar nada daría otra
     * huella y la comprobación dejaría de significar algo.
     */
    public function fingerprint(): string
    {
        return hash('sha256', (string) json_encode([
            $this->document_code,
            $this->sequence,
            $this->subject,
            $this->detail,
            $this->task_id,
            $this->decision,
            $this->reservations,
            $this->accepted_by_name,
            $this->accepted_by_role,
            $this->accepted_by_org,
            $this->accepted_on?->toDateString(),
        ]));
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * El entregable que se acepta, si el acta apunta a uno.
     *
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Quién lo asentó en el sistema. **No es quien aceptó** — ese va como texto,
     * porque casi siempre es alguien de fuera que no tiene cuenta.
     *
     * @return BelongsTo<User, $this>
     */
    public function signedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_by');
    }
}
