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

/**
 * Esquema mínimo de la Etapa 1 (CL-008): existe para que las reglas de
 * visibilidad se puedan probar. El CRUD y la lógica llegan en la Etapa 3.
 */
#[Fillable(['code', 'name', 'description', 'status', 'owner_id', 'org_unit_id', 'currency'])]
class Project extends Model
{
    use RecordsAudit, SoftDeletes;

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
