<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Un requisito del proyecto: algo que se pidió y que hay que poder rastrear
 * hasta el trabajo que lo entrega.
 *
 * Un requisito **sin tarea** no es un error de captura: es el hallazgo. La
 * matriz de trazabilidad existe justo para encontrarlos — se pidió y nadie lo
 * está construyendo — y por eso la liga es opcional. Obligarla forzaría a
 * inventar una tarea para poder guardar, y el hueco dejaría de verse.
 */
#[Fillable([
    'project_id', 'sequence', 'description', 'origin', 'priority',
    'category', 'task_id', 'acceptance_criteria', 'status',
])]
class ProjectRequirement extends Model
{
    use RecordsAudit, SoftDeletes;

    /** Lo que el estándar llama MoSCoW, en tres niveles que la gente usa. */
    public const PRIORITIES = ['must', 'should', 'could'];

    public const STATUSES = ['proposed', 'approved', 'delivered', 'verified', 'dropped'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    /** El número que se cita: `REQ-004`. */
    public function reference(): string
    {
        return sprintf('REQ-%03d', $this->sequence);
    }

    /** ¿Se pidió y nadie lo está construyendo? */
    public function isOrphan(): bool
    {
        return $this->task_id === null && $this->status !== 'dropped';
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
