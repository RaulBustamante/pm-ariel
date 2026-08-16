<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Support\Scheduling\DependencyLink;
use App\Support\Scheduling\DependencyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['project_id', 'predecessor_id', 'successor_id', 'type', 'lag_minutes'])]
class TaskDependency extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['lag_minutes' => 'integer'];
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function predecessor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'predecessor_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function successor(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'successor_id');
    }

    public function toLink(): DependencyLink
    {
        return new DependencyLink(
            predecessorId: (string) $this->predecessor_id,
            successorId: (string) $this->successor_id,
            type: DependencyType::tryFrom((string) $this->type) ?? DependencyType::FinishToStart,
            lagMinutes: (int) $this->lag_minutes,
        );
    }
}
