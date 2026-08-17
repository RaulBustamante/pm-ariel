<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Una versión emitida de un documento, congelada.
 *
 * Es la respuesta a «qué le mandé a Jorge hace tres semanas». El sistema puede
 * volver a generar el corte semanal, pero con los datos de hoy — que ya son
 * otros. Lo que se emitió es un hecho, y un hecho no se recalcula.
 *
 * **No se edita ni se borra**, igual que una línea base. Toda su utilidad viene
 * de eso: un archivo que se puede modificar después no prueba nada. Si una
 * versión salió con un error, se emite otra; la equivocada se queda como parte
 * del histórico, que es exactamente lo que un control de documentos necesita.
 *
 * @property Carbon|null $issued_at
 * @property array<string, mixed>|null $summary
 */
#[Fillable([
    'project_id', 'document_code', 'version', 'title', 'issued_at', 'issued_by',
    'stored_path', 'byte_size', 'checksum', 'summary', 'notes',
])]
class DocumentIssue extends Model
{
    /*
    | Sin `RecordsAudit`, a proposito.
    |
    | El trait sirve para saber quien cambio que y cuando, y aqui **nada cambia
    | nunca**: el renglon se crea y se congela. Quien lo emitio y a que hora ya
    | viven en `issued_by` e `issued_at`, que es justo lo que la bitacora
    | anotaria.
    |
    | Ademas el trait asume borrado en suave —consulta `isForceDeleting()`— y
    | este modelo no lo usa, porque una version emitida no se borra en ningun
    | sentido.
    */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'version' => 'integer',
            'byte_size' => 'integer',
            'summary' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException(
                'Una versión emitida no se edita. Si salió con un error, emite otra: '
                .'la equivocada se queda en el histórico, que es de lo que sirve tenerlo.',
            );
        });

        static::deleting(function (): void {
            throw new RuntimeException('Una versión emitida no se borra.');
        });
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    /** El nombre visible del documento, desde el catálogo. */
    public function label(): string
    {
        return __("documents.doc_{$this->document_code}");
    }

    /** Cómo se cita en una junta: «Corte semanal v3». */
    public function reference(): string
    {
        return $this->label().' v'.$this->version;
    }
}
