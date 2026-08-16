<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Qué se va a hacer con un riesgo. Sin dueño y sin fecha no es una respuesta,
 * es una intención — pero se permite guardarla igual, porque obligar a llenar
 * todo de golpe es lo que hace que nadie registre nada.
 */
#[Fillable(['risk_id', 'strategy', 'description', 'owner_id', 'due_date', 'status'])]
class RiskResponse extends Model
{
    use RecordsAudit, SoftDeletes;

    public const STRATEGY_AVOID = 'avoid';

    public const STRATEGY_MITIGATE = 'mitigate';

    public const STRATEGY_TRANSFER = 'transfer';

    public const STRATEGY_ACCEPT = 'accept';

    public const STRATEGY_ESCALATE = 'escalate';

    /** @var list<string> */
    public const STRATEGIES = [
        self::STRATEGY_AVOID, self::STRATEGY_MITIGATE, self::STRATEGY_TRANSFER,
        self::STRATEGY_ACCEPT, self::STRATEGY_ESCALATE,
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_DONE = 'done';

    /** @var list<string> */
    public const STATUSES = [self::STATUS_PENDING, self::STATUS_IN_PROGRESS, self::STATUS_DONE];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    /**
     * @return BelongsTo<Risk, $this>
     */
    public function risk(): BelongsTo
    {
        return $this->belongsTo(Risk::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
