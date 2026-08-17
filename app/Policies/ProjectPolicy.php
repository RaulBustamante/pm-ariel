<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Permission;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Visibility\VisibilityScope;

/**
 * Las cuatro reglas de visibilidad viven aquí, y cada una tiene su prueba de 403.
 *
 *  1. Ver hacia abajo es automático y recursivo.
 *  2. Ser jefe no otorga edición: se edita solo donde se es miembro.
 *  3. Los costos son un permiso independiente del nivel jerárquico.
 *  4. El rol auditor ve todo, en solo lectura.
 */
final class ProjectPolicy
{
    public function __construct(
        private readonly VisibilityScope $visibility,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::PROJECTS_VIEW)
            || $user->hasRole(Role::AUDITOR);
    }

    /** Regla 1 y regla 4. */
    public function view(User $user, Project $project): bool
    {
        if ($user->hasRole(Role::ADMIN) || $user->hasRole(Role::AUDITOR)) {
            return true;
        }

        if (! $user->hasPermission(Permission::PROJECTS_VIEW)) {
            return false;
        }

        return $this->visibility->canSeeProject($user, $project);
    }

    /**
     * Crear un proyecto no se decide por membresía —todavía no hay proyecto del
     * cual ser miembro— sino por el permiso de gestionar proyectos.
     *
     * Faltaba este método, y su ausencia no fallaba con un error: `@can('create')`
     * simplemente devolvía false, el botón de «Nuevo proyecto» nunca se dibujaba
     * y la pantalla respondía 403. Un permiso que falta se ve igual que un
     * permiso denegado, y por eso hay que probar también lo que sí debe pasar.
     */
    public function create(User $user): bool
    {
        return ! $this->isReadOnly($user) && $user->hasPermission(Permission::PROJECTS_MANAGE);
    }

    /**
     * Regla 2. Ser jefe de alguien del proyecto da lectura, nunca escritura:
     * la comprobación es la membresía, no la jerarquía.
     */
    public function update(User $user, Project $project): bool
    {
        if ($this->isReadOnly($user)) {
            return false;
        }

        if ($user->hasRole(Role::ADMIN)) {
            return true;
        }

        if (! $user->hasPermission(Permission::PROJECTS_MANAGE)) {
            return false;
        }

        return in_array($user->projectRoleFor($project), Project::WRITING_ROLES, strict: true);
    }

    public function delete(User $user, Project $project): bool
    {
        if ($this->isReadOnly($user)) {
            return false;
        }

        return $user->hasRole(Role::ADMIN)
            || $user->projectRoleFor($project) === Project::ROLE_MANAGER;
    }

    /**
     * Regla 3. Un supervisor puede ver el avance de su gente sin ver tarifas:
     * la jerarquía no concede este permiso, solo el permiso lo concede.
     */
    public function viewCosts(User $user, Project $project): bool
    {
        if (! $this->view($user, $project)) {
            return false;
        }

        return $user->hasPermission(Permission::COSTS_VIEW)
            || $user->hasPermission(Permission::COSTS_MANAGE);
    }

    public function manageCosts(User $user, Project $project): bool
    {
        if ($this->isReadOnly($user)) {
            return false;
        }

        return $user->hasPermission(Permission::COSTS_MANAGE)
            && $this->update($user, $project);
    }

    /** Regla 4: el auditor no escribe en ninguna parte, tenga el rol que tenga además. */
    private function isReadOnly(User $user): bool
    {
        return $user->hasRole(Role::AUDITOR) || ! $user->is_active;
    }
}
