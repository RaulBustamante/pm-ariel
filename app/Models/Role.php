<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\RecordsAudit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'is_system'])]
class Role extends Model
{
    use RecordsAudit, SoftDeletes;

    public const ADMIN = 'admin';

    public const PORTFOLIO_MANAGER = 'portfolio_manager';

    public const PROJECT_MANAGER = 'project_manager';

    public const TEAM_MEMBER = 'team_member';

    public const VIEWER = 'viewer';

    public const AUDITOR = 'auditor';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    /**
     * @return BelongsToMany<Permission, $this>
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_role')->withTimestamps();
    }
}
