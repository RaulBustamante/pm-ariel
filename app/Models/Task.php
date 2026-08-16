<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use App\Support\Scheduling\ConstraintType;
use App\Support\Scheduling\TaskConstraint;
use App\Support\Scheduling\TaskNode;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Una tarea guardada.
 *
 * Las columnas se dividen en dos grupos que **no se mezclan**: lo que capturó el
 * usuario (duración, restricción, costo, avance) y lo que produjo el último
 * cálculo (`early_*`, `late_*`, holguras, crítica). El motor solo escribe el
 * segundo grupo. Si algún día una pantalla escribe una fecha calculada a mano,
 * el siguiente cálculo la va a pisar — y con razón.
 *
 * Las columnas de fecha llevan cast, pero el análisis estático las lee como
 * texto si no se declaran aquí.
 *
 * @property Carbon|null $constraint_date
 * @property Carbon|null $early_start
 * @property Carbon|null $early_finish
 * @property Carbon|null $late_start
 * @property Carbon|null $late_finish
 * @property Carbon|null $actual_start
 * @property Carbon|null $actual_finish
 */
#[Fillable([
    'project_id', 'parent_id', 'name', 'description', 'duration_minutes',
    'constraint_type', 'constraint_date', 'calendar_id', 'sort_order',
    'cost', 'percent_complete', 'actual_start', 'actual_finish', 'owner_id',
])]
class Task extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'constraint_date' => 'datetime',
            'early_start' => 'datetime',
            'early_finish' => 'datetime',
            'late_start' => 'datetime',
            'late_finish' => 'datetime',
            'total_float_minutes' => 'integer',
            'free_float_minutes' => 'integer',
            'is_critical' => 'boolean',
            'is_summary' => 'boolean',
            'cost' => 'decimal:2',
            'percent_complete' => 'decimal:2',
            'actual_start' => 'datetime',
            'actual_finish' => 'datetime',
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
     * @return BelongsTo<self, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<self, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /**
     * @return BelongsTo<Calendar, $this>
     */
    public function calendar(): BelongsTo
    {
        return $this->belongsTo(Calendar::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<TaskDependency, $this>
     */
    public function predecessorLinks(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'successor_id');
    }

    /**
     * @return HasMany<TaskDependency, $this>
     */
    public function successorLinks(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_id');
    }

    public function isMilestone(): bool
    {
        return $this->duration_minutes === 0 && ! $this->is_summary;
    }

    /** El dato puro que consume el motor. */
    public function toNode(?string $calendarKey = null): TaskNode
    {
        $constraintType = ConstraintType::tryFrom((string) $this->constraint_type) ?? ConstraintType::AsSoonAsPossible;

        $date = $this->constraint_date;

        // Una restricción que necesita fecha y no la tiene no significa nada, y
        // aplicarla con una fecha inventada sería peor que ignorarla.
        $constraint = $constraintType->needsDate() && $date instanceof DateTimeInterface
            ? new TaskConstraint($constraintType, DateTimeImmutable::createFromInterface($date))
            : new TaskConstraint($constraintType->needsDate() ? ConstraintType::AsSoonAsPossible : $constraintType);

        return new TaskNode(
            id: (string) $this->id,
            name: (string) $this->name,
            durationMinutes: (int) $this->duration_minutes,
            parentId: $this->parent_id === null ? null : (string) $this->parent_id,
            calendarKey: $calendarKey,
            constraint: $constraint,
            sortOrder: (int) $this->sort_order,
            cost: (float) $this->cost,
            percentComplete: (float) $this->percent_complete,
        );
    }
}
