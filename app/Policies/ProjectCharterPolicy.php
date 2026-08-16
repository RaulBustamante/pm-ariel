<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\Role;
use App\Models\User;
use App\Policies\Concerns\AuthorizesViaProject;

final class ProjectCharterPolicy
{
    use AuthorizesViaProject;

    public function view(User $user, ProjectCharter $charter): bool
    {
        return $this->canReadProject($user, $charter->loadMissing('project')->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canWriteProject($user, $project);
    }

    public function update(User $user, ProjectCharter $charter): bool
    {
        return $this->canWriteProject($user, $charter->loadMissing('project')->project);
    }

    public function delete(User $user, ProjectCharter $charter): bool
    {
        return $this->update($user, $charter);
    }

    /**
     * Aprobar el acta no es editarla. Quien la escribe no debería poder
     * aprobársela a sí mismo, pero mientras no exista el flujo de aprobación
     * formal (Fase 2) se exige al menos ser dueño del proyecto o administrador.
     */
    public function approve(User $user, ProjectCharter $charter): bool
    {
        $project = $charter->loadMissing('project')->project;

        if (! $this->canWriteProject($user, $project)) {
            return false;
        }

        return $user->hasRole(Role::ADMIN) || $project->owner_id === $user->id;
    }
}
