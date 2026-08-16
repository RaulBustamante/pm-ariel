<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * Lo que se comprometió, congelado.
 *
 * **Inmutable después de capturarse.** Toda la utilidad de una línea base viene
 * de que no se pueda tocar: en cuanto alguien puede "actualizarla" para que
 * cuadre con el avance real, la varianza da cero siempre y la comparación deja
 * de significar nada. Si el compromiso cambió de verdad, se captura una nueva y
 * se dice cuál está vigente — así queda el rastro de que cambió.
 */
#[Fillable(['project_id', 'name', 'notes', 'captured_at', 'captured_by', 'project_start', 'project_finish', 'total_cost', 'is_active'])]
class Baseline extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'project_start' => 'datetime',
            'project_finish' => 'datetime',
            'total_cost' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $baseline): void {
            // Cambiar cuál es la vigente sí se permite: no altera lo capturado.
            $touched = array_keys($baseline->getDirty());

            if (array_diff($touched, ['is_active', 'updated_at']) !== []) {
                throw new RuntimeException(
                    'Una línea base no se edita. Captura una nueva y márcala como vigente.',
                );
            }
        });

        static::deleting(function (): void {
            throw new RuntimeException('Una línea base no se borra: es el registro de lo que se comprometió.');
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
     * @return HasMany<BaselineTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(BaselineTask::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function capturedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'captured_by');
    }
}
