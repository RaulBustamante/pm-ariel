<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Quien hace el trabajo.
 *
 * No siempre es un usuario del sistema: puede ser alguien sin cuenta, una
 * maquina o un proveedor. Atar los recursos a las cuentas obligaria a dar de
 * alta usuarios que nunca van a entrar, solo para poder asignarles trabajo.
 */
#[Fillable([
    'project_id', 'user_id', 'name', 'type', 'role_title', 'email',
    'capacity_percent', 'cost_per_hour', 'calendar_id', 'is_active',
])]
class Resource extends Model
{
    use RecordsAudit, SoftDeletes;

    public const TYPE_PERSON = 'person';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_VENDOR = 'vendor';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity_percent' => 'integer',
            'cost_per_hour' => 'decimal:2',
            'is_active' => 'boolean',
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
     * @return BelongsToMany<Task, $this>
     */
    public function tasks(): BelongsToMany
    {
        return $this->belongsToMany(Task::class, 'task_assignments')
            ->withPivot('units_percent')
            ->withTimestamps();
    }
}
