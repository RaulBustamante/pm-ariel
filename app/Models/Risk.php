<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Algo que todavía no pasa y que, si pasa, cambia el resultado del proyecto.
 *
 * Se registran también las oportunidades, no solo las amenazas. Dejarlas fuera
 * es la razón por la que el registro de riesgos suele volverse una lista de
 * miedos que nadie vuelve a abrir.
 */
#[Fillable([
    'project_id', 'code', 'category', 'description', 'cause', 'effect',
    'probability', 'impact', 'kind', 'status', 'owner_id', 'source', 'catalog_key',
])]
class Risk extends Model
{
    use RecordsAudit, SoftDeletes;

    public const KIND_THREAT = 'threat';

    public const KIND_OPPORTUNITY = 'opportunity';

    public const STATUS_IDENTIFIED = 'identified';

    public const STATUS_ANALYZING = 'analyzing';

    public const STATUS_RESPONDING = 'responding';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_MATERIALIZED = 'materialized';

    /** @var list<string> */
    public const STATUSES = [
        self::STATUS_IDENTIFIED, self::STATUS_ANALYZING, self::STATUS_RESPONDING,
        self::STATUS_CLOSED, self::STATUS_MATERIALIZED,
    ];

    public const LEVEL_LOW = 'low';

    public const LEVEL_MEDIUM = 'medium';

    public const LEVEL_HIGH = 'high';

    public const LEVEL_CRITICAL = 'critical';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'impact' => 'integer',
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
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<RiskResponse, $this>
     */
    public function responses(): HasMany
    {
        return $this->hasMany(RiskResponse::class);
    }

    /** Probabilidad × impacto, de 1 a 25. */
    public function score(): int
    {
        return $this->probability * $this->impact;
    }

    /**
     * Los cortes no son arbitrarios: 15 es 3×5 o 5×3 — algo que pasa a veces y
     * duele mucho, o casi seguro y duele algo. Cualquiera de los dos merece un
     * plan, no una nota.
     */
    public function level(): string
    {
        return match (true) {
            $this->score() >= 15 => self::LEVEL_CRITICAL,
            $this->score() >= 9 => self::LEVEL_HIGH,
            $this->score() >= 4 => self::LEVEL_MEDIUM,
            default => self::LEVEL_LOW,
        };
    }

    /**
     * Un riesgo alto sin respuesta es el hallazgo más común de una auditoría de
     * proyecto, y el más fácil de evitar.
     */
    public function needsResponse(): bool
    {
        if (in_array($this->status, [self::STATUS_CLOSED, self::STATUS_MATERIALIZED], strict: true)) {
            return false;
        }

        return in_array($this->level(), [self::LEVEL_HIGH, self::LEVEL_CRITICAL], strict: true)
            && $this->responses->isEmpty();
    }

    /**
     * El siguiente correlativo del proyecto (R-01, R-02...). Se calcula sobre lo
     * que ya existe, incluidos los borrados: reusar un código haría que dos
     * riesgos distintos aparecieran con el mismo nombre en dos minutas.
     */
    public static function nextCodeFor(Project $project): string
    {
        $used = self::withTrashed()
            ->where('project_id', $project->id)
            ->pluck('code')
            // Solo los dígitos. El guion de "R-01" cuenta como signo para
            // cualquier conversión ingenua, y entonces el correlativo arranca
            // en cero y a la segunda choca contra su propio índice único.
            ->map(fn (string $code): int => (int) preg_replace('/\D/', '', $code))
            ->max() ?? 0;

        return sprintf('R-%02d', $used + 1);
    }
}
