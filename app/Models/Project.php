<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * Esquema mínimo de la Etapa 1 (CL-008): existe para que las reglas de
 * visibilidad se puedan probar. El CRUD completo llega en la Etapa 4.
 *
 * @property Carbon|null $planned_start
 * @property Carbon|null $planned_finish
 */
#[Fillable([
    'code', 'name', 'description', 'status', 'owner_id', 'org_unit_id', 'currency',
    'planned_start', 'planned_finish',
])]
class Project extends Model
{
    use RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'planned_start' => 'datetime',
            'planned_finish' => 'datetime',
        ];
    }

    public const ROLE_MANAGER = 'manager';

    public const ROLE_MEMBER = 'member';

    public const ROLE_VIEWER = 'viewer';

    /** Roles de proyecto que otorgan escritura. */
    public const WRITING_ROLES = [self::ROLE_MANAGER, self::ROLE_MEMBER];

    /**
     * @return HasOne<ProjectCharter, $this>
     */
    public function charter(): HasOne
    {
        return $this->hasOne(ProjectCharter::class);
    }

    /**
     * Los de más peso primero. Quien abre la lista quiere ver a los que pueden
     * hundir el proyecto, no el orden en que se capturaron.
     *
     * @return HasMany<Stakeholder, $this>
     */
    public function stakeholders(): HasMany
    {
        return $this->hasMany(Stakeholder::class)
            ->orderByRaw('power * interest DESC')
            ->orderBy('name');
    }

    /**
     * @return HasMany<Risk, $this>
     */
    public function risks(): HasMany
    {
        return $this->hasMany(Risk::class)
            ->orderByRaw('probability * impact DESC')
            ->orderBy('code');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<TaskDependency, $this>
     */
    public function taskDependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class);
    }

    /**
     * @return HasMany<Calendar, $this>
     */
    public function calendars(): HasMany
    {
        return $this->hasMany(Calendar::class);
    }

    /**
     * @return HasMany<Baseline, $this>
     */
    public function baselines(): HasMany
    {
        return $this->hasMany(Baseline::class)->orderByDesc('captured_at');
    }

    /**
     * @return HasMany<ScheduleRun, $this>
     */
    public function scheduleRuns(): HasMany
    {
        return $this->hasMany(ScheduleRun::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<\App\Models\Resource, $this>
     */
    /**
     * Las versiones emitidas de los documentos de este proyecto.
     *
     * @return HasMany<DocumentIssue, $this>
     */
    public function documentIssues(): HasMany
    {
        return $this->hasMany(DocumentIssue::class);
    }

    /**
     * Los recursos del proyecto: quien trabaja y que se consume.
     *
     * El generico va explicito porque `Resource` es un tipo reservado de PHP y
     * hay que escribirlo con la ruta completa; sin el, el analisis estatico
     * pierde el tipo en cuanto la consulta pasa por `withCount()`.
     *
     * @return HasMany<\App\Models\Resource, $this>
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class)->orderBy('name');
    }

    /**
     * @return HasMany<ProjectFinding, $this>
     */
    public function findings(): HasMany
    {
        return $this->hasMany(ProjectFinding::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return BelongsTo<OrgUnit, $this>
     */
    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot('project_role')
            ->withTimestamps();
    }
}
