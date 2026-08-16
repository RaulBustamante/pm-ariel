<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * El acta constitutiva: el documento que dice por qué existe el proyecto, qué
 * entrega y cómo se sabrá si salió bien.
 *
 * También guarda dónde se quedó el recorrido de inicio, porque nadie lo termina
 * de una sentada y volver a empezar es la forma más segura de que no se termine.
 *
 * @property array<int, string>|null $completed_steps
 */
#[Fillable([
    'project_id', 'template_id',
    'problem_statement', 'opportunity', 'expected_benefit', 'alignment',
    'objectives', 'deliverables', 'success_criteria', 'assumptions',
    'constraints', 'out_of_scope', 'high_level_milestones',
    'sponsor_id', 'current_step', 'completed_steps',
])]
class ProjectCharter extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'completed_steps' => 'array',
            'approved_at' => 'datetime',
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
     * @return BelongsTo<ProjectTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(ProjectTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sponsor_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->approved_at !== null;
    }

    public function hasCompleted(string $step): bool
    {
        return in_array($step, $this->completed_steps ?? [], strict: true);
    }

    /**
     * Marcar un paso no reordena los anteriores ni los borra: alguien puede
     * volver al paso 1 después de haber llegado al 4, y eso no debe perder nada.
     */
    public function markCompleted(string $step): void
    {
        $completed = $this->completed_steps ?? [];

        if (! in_array($step, $completed, strict: true)) {
            $completed[] = $step;
        }

        $this->completed_steps = $completed;
    }
}
