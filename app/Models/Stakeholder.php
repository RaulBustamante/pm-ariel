<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Alguien a quien el proyecto le afecta o que puede afectarlo. No siempre es un
 * usuario del sistema: un cliente, una autoridad o un proveedor cuentan igual.
 */
#[Fillable([
    'project_id', 'user_id', 'name', 'organization', 'role_title', 'email', 'phone',
    'power', 'interest', 'expectations', 'engagement_strategy', 'notes', 'sort_order',
])]
class Stakeholder extends Model
{
    use RecordsAudit, SoftDeletes;

    /** El corte entre "alto" y "bajo" en la escala de 1 a 5. */
    public const HIGH_THRESHOLD = 4;

    public const QUADRANT_MANAGE_CLOSELY = 'manage_closely';

    public const QUADRANT_KEEP_SATISFIED = 'keep_satisfied';

    public const QUADRANT_KEEP_INFORMED = 'keep_informed';

    public const QUADRANT_MONITOR = 'monitor';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'power' => 'integer',
            'interest' => 'integer',
            'sort_order' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * El cuadrante de la matriz poder/interés. De aquí sale la estrategia
     * sugerida, y es la única regla del bloque que no depende de quién capture.
     */
    public function quadrant(): string
    {
        $highPower = $this->power >= self::HIGH_THRESHOLD;
        $highInterest = $this->interest >= self::HIGH_THRESHOLD;

        return match (true) {
            $highPower && $highInterest => self::QUADRANT_MANAGE_CLOSELY,
            $highPower => self::QUADRANT_KEEP_SATISFIED,
            $highInterest => self::QUADRANT_KEEP_INFORMED,
            default => self::QUADRANT_MONITOR,
        };
    }

    /**
     * Los de mucho poder y mucho interés son los que hunden o salvan un
     * proyecto. Sirve para ordenar la lista sin que nadie tenga que decidirlo.
     */
    public function weight(): int
    {
        return $this->power * $this->interest;
    }
}
