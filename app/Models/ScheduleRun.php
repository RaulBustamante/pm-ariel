<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cada vez que se recalculó un proyecto, y qué salió.
 *
 * Sirve para dos preguntas que se hacen siempre y casi nunca se pueden
 * responder: "¿de cuándo son estas fechas?" y "¿por qué no calculó?". Los
 * cálculos fallidos se guardan igual que los buenos — sobre todo esos, que son
 * los que alguien va a querer reconstruir.
 *
 * @property array<int, string>|null $failure_cycle
 */
#[Fillable([
    'project_id', 'project_start', 'project_finish', 'task_count',
    'critical_task_count', 'elapsed_ms', 'status', 'failure_reason',
    'failure_cycle', 'triggered_by',
])]
class ScheduleRun extends Model
{
    public const STATUS_OK = 'ok';

    public const STATUS_FAILED = 'failed';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'project_start' => 'datetime',
            'project_finish' => 'datetime',
            'task_count' => 'integer',
            'critical_task_count' => 'integer',
            'elapsed_ms' => 'float',
            'failure_cycle' => 'array',
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
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function failed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
