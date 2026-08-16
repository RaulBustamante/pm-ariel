<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Visibility\VisibilityScope;

final class UserPolicy
{
    public function __construct(
        private readonly VisibilityScope $visibility,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::USERS_MANAGE) || $user->hasRole(Role::AUDITOR);
    }

    public function view(User $user, User $target): bool
    {
        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::AUDITOR)) {
            return true;
        }

        return $this->visibility->canSeeUser($user, $target);
    }

    public function create(User $user): bool
    {
        return ! $this->isReadOnly($user) && $user->hasPermission(Permission::USERS_MANAGE);
    }

    public function update(User $user, User $target): bool
    {
        return $this->create($user);
    }

    /**
     * Nadie se desactiva a sí mismo: dejaría el sistema sin administrador de un
     * clic y sin forma de volver a entrar.
     */
    public function delete(User $user, User $target): bool
    {
        return ! $user->is($target) && $this->create($user);
    }

    public function assignRoles(User $user): bool
    {
        return ! $this->isReadOnly($user) && $user->hasPermission(Permission::ROLES_MANAGE);
    }

    private function isReadOnly(User $user): bool
    {
        return $user->hasRole(Role::AUDITOR) || ! $user->is_active;
    }
}
