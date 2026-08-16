<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable([
    'name', 'email', 'password', 'auth_provider', 'external_id', 'locale',
    'timezone', 'expert_mode', 'position_id', 'org_unit_id', 'is_active',
    'must_change_password',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, RecordsAudit, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'expert_mode' => 'boolean',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Valores que nunca entran a la bitácora de auditoría.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['password', 'remember_token'];

    public function orgUnit(): BelongsTo
    {
        return $this->belongsTo(OrgUnit::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_role')->withTimestamps();
    }

    /**
     * Reportes directos vigentes. La vigencia importa: un cambio de jefe no
     * borra el histórico, solo lo cierra.
     */
    public function directReports(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_hierarchy', 'manager_id', 'subordinate_id')
            ->wherePivotNull('effective_to');
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'user_hierarchy', 'subordinate_id', 'manager_id')
            ->wherePivotNull('effective_to');
    }

    public function projectMemberships(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_members')
            ->withPivot('project_role')
            ->withTimestamps();
    }

    public function hasRole(string $role): bool
    {
        return $this->roles->contains(fn (Role $candidate): bool => $candidate->name === $role);
    }

    /**
     * Un permiso se tiene por rol. No hay permisos sueltos por usuario: eso
     * vuelve imposible responder "quién puede hacer qué" de un vistazo.
     */
    public function hasPermission(string $permission): bool
    {
        return $this->permissionNames()->contains($permission);
    }

    /**
     * @return Collection<int, string>
     */
    public function permissionNames(): Collection
    {
        return $this->roles
            ->loadMissing('permissions')
            ->flatMap(fn (Role $role): Collection => $role->permissions->pluck('name'))
            ->unique()
            ->values();
    }

    /**
     * El rol de proyecto que tiene este usuario, o null si no es miembro.
     * Ser jefe de alguien del proyecto no cuenta: eso da lectura, no rol.
     */
    public function projectRoleFor(Project $project): ?string
    {
        $membership = $project->members->firstWhere('id', $this->id);

        return $membership?->pivot->project_role;
    }
}
