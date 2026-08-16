<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * Solo se agrega. Sin updated_at, sin borrado, sin edición.
 *
 * La columna es JSON en la base, pero el cast la entrega como arreglo; sin
 * esto el análisis estático la lee como texto.
 *
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 */
#[Fillable([
    'user_id', 'auditable_type', 'auditable_id', 'event',
    'old_values', 'new_values', 'ip_address', 'user_agent',
])]
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new RuntimeException('Audit log entries are immutable and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new RuntimeException('Audit log entries are immutable and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }
}
