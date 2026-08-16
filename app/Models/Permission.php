<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo cerrado: los permisos los define el sistema, no el usuario. Cada
 * constante tiene su regla en una Policy y su prueba de 403.
 */
#[Fillable(['name', 'group'])]
class Permission extends Model
{
    public const PROJECTS_VIEW = 'projects.view';

    public const PROJECTS_MANAGE = 'projects.manage';

    public const TASKS_MANAGE = 'tasks.manage';

    public const DEPENDENCIES_MANAGE = 'dependencies.manage';

    public const BASELINES_MANAGE = 'baselines.manage';

    public const RESOURCES_MANAGE = 'resources.manage';

    /** Independiente del nivel jerárquico. Un jefe puede ver avance sin ver tarifas. */
    public const COSTS_VIEW = 'costs.view';

    public const COSTS_MANAGE = 'costs.manage';

    public const USERS_MANAGE = 'users.manage';

    public const ROLES_MANAGE = 'roles.manage';

    public const REPORTS_EXPORT = 'reports.export';

    /** Lectura global de solo lectura, incluida la bitácora. */
    public const AUDIT_VIEW_ALL = 'audit.view_all';

    public const SETTINGS_MANAGE = 'settings.manage';

    public const BACKUPS_RUN = 'backups.run';

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * @return array<string, list<string>>
     */
    public static function catalog(): array
    {
        return [
            'projects' => [self::PROJECTS_VIEW, self::PROJECTS_MANAGE],
            'schedule' => [self::TASKS_MANAGE, self::DEPENDENCIES_MANAGE, self::BASELINES_MANAGE],
            'resources' => [self::RESOURCES_MANAGE],
            'costs' => [self::COSTS_VIEW, self::COSTS_MANAGE],
            'administration' => [
                self::USERS_MANAGE, self::ROLES_MANAGE, self::SETTINGS_MANAGE, self::BACKUPS_RUN,
            ],
            'reporting' => [self::REPORTS_EXPORT, self::AUDIT_VIEW_ALL],
        ];
    }
}
