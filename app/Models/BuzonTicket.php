<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'user_id', 'folio', 'tipo', 'titulo', 'descripcion', 'severidad', 'url',
    'ruta_nombre', 'navegador', 'sistema_operativo', 'resolucion', 'user_agent',
    'errores_consola', 'estado', 'asignado_a', 'notas_internas', 'resuelto_en',
])]
final class BuzonTicket extends Model
{
    use SoftDeletes;

    public const TIPO_ERROR = 'error';

    public const TIPO_SUGERENCIA = 'sugerencia';

    public const SEV_BLOQUEANTE = 'bloqueante';

    public const SEV_MOLESTO = 'molesto';

    public const SEV_COSMETICO = 'cosmetico';

    public const ESTADO_NUEVO = 'nuevo';

    public const ESTADO_EN_REVISION = 'en_revision';

    public const ESTADO_RESUELTO = 'resuelto';

    public const ESTADO_DESCARTADO = 'descartado';

    /** @return array<string, string> */
    public static function tipos(): array
    {
        return [self::TIPO_ERROR => 'Error', self::TIPO_SUGERENCIA => 'Sugerencia'];
    }

    /** @return array<string, string> */
    public static function severidades(): array
    {
        return [
            self::SEV_BLOQUEANTE => 'Me bloquea',
            self::SEV_MOLESTO => 'Me estorba',
            self::SEV_COSMETICO => 'Es cosmético',
        ];
    }

    /** @return array<string, string> */
    public static function estados(): array
    {
        return [
            self::ESTADO_NUEVO => 'Nuevo',
            self::ESTADO_EN_REVISION => 'En revisión',
            self::ESTADO_RESUELTO => 'Resuelto',
            self::ESTADO_DESCARTADO => 'Descartado',
        ];
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['errores_consola' => 'array', 'resuelto_en' => 'datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function asignadoA(): BelongsTo
    {
        return $this->belongsTo(User::class, 'asignado_a');
    }

    /** @return HasMany<BuzonAdjunto, $this> */
    public function adjuntos(): HasMany
    {
        return $this->hasMany(BuzonAdjunto::class);
    }

    public function scopePendientes(Builder $query): Builder
    {
        return $query->whereIn('estado', [self::ESTADO_NUEVO, self::ESTADO_EN_REVISION]);
    }

    public function getTipoLabelAttribute(): string
    {
        return self::tipos()[$this->tipo] ?? $this->tipo;
    }

    public function getEstadoLabelAttribute(): string
    {
        return self::estados()[$this->estado] ?? $this->estado;
    }

    public function getSeveridadLabelAttribute(): ?string
    {
        return $this->severidad ? (self::severidades()[$this->severidad] ?? $this->severidad) : null;
    }
}
