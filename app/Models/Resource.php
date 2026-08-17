<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
    'capacity_percent', 'unit_of_measure', 'cost_per_hour', 'cost_per_unit',
    'supplier', 'is_external', 'calendar_id', 'is_active',
])]
class Resource extends Model
{
    use RecordsAudit, SoftDeletes;

    public const TYPE_PERSON = 'person';

    public const TYPE_EQUIPMENT = 'equipment';

    public const TYPE_VENDOR = 'vendor';

    /**
     * Lo que se consume, no lo que trabaja.
     *
     * Se separa de los otros tres porque **no se mide igual**: media tonelada de
     * acero no se asigna «al 60 % de la jornada», se asigna en cantidad. De esa
     * diferencia salen dos consecuencias que recorren todo el sistema: un
     * material no puede estar sobreasignado —no tiene jornada que exceder— y su
     * costo es cantidad × costo unitario, no horas × tarifa.
     */
    public const TYPE_MATERIAL = 'material';

    /** @return list<string> */
    public static function types(): array
    {
        return [self::TYPE_PERSON, self::TYPE_EQUIPMENT, self::TYPE_VENDOR, self::TYPE_MATERIAL];
    }

    /**
     * Los que aportan trabajo, y por tanto tienen jornada, tarifa por hora y
     * pueden quedar sobreasignados.
     *
     * @return list<string>
     */
    public static function workTypes(): array
    {
        return [self::TYPE_PERSON, self::TYPE_EQUIPMENT, self::TYPE_VENDOR];
    }

    /** ¿Se consume por unidad en vez de aportar horas? */
    public function isMaterial(): bool
    {
        return $this->type === self::TYPE_MATERIAL;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capacity_percent' => 'integer',
            'cost_per_hour' => 'decimal:2',
            'cost_per_unit' => 'decimal:2',
            'is_active' => 'boolean',
            'is_external' => 'boolean',
        ];
    }

    /**
     * En qué tareas está. Se usa para saber si se puede borrar y para el
     * reporte de carga.
     *
     * @return HasMany<TaskAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TaskAssignment::class);
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
