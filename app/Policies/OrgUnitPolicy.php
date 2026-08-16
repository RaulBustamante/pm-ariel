<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OrgUnit;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;

/**
 * Las áreas son estructura de la organización, no personas: se gobiernan con
 * `settings.manage`. El auditor lee todo y no escribe nada, aquí tampoco.
 */
final class OrgUnitPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::SETTINGS_MANAGE)
            || $user->hasPermission(Permission::USERS_MANAGE)
            || $user->hasRole(Role::AUDITOR);
    }

    public function view(User $user, OrgUnit $unit): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return ! $this->isReadOnly($user) && $user->hasPermission(Permission::SETTINGS_MANAGE);
    }

    public function update(User $user, OrgUnit $unit): bool
    {
        return $this->create($user);
    }

    /**
     * Un área con gente dentro o con áreas colgando no se borra: se vaciaría el
     * dato de a quién pertenece cada quien sin que nadie lo note. El controlador
     * verifica eso; la política solo dice quién tiene derecho a intentarlo.
     */
    public function delete(User $user, OrgUnit $unit): bool
    {
        return $this->create($user);
    }

    private function isReadOnly(User $user): bool
    {
        return $user->hasRole(Role::AUDITOR) || ! $user->is_active;
    }
}
