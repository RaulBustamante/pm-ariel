<?php

declare(strict_types=1);

namespace App\Policies\Concerns;

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Los documentos de inicio no tienen reglas propias: heredan las del proyecto.
 *
 * Es a propósito. Si el acta, los interesados y los riesgos tuvieran cada uno su
 * criterio, tarde o temprano alguien podría leer el acta de un proyecto que no
 * ve, o editar riesgos de uno donde no es miembro. Con esto no hay dónde
 * equivocarse: quien ve el proyecto ve sus documentos, y quien lo edita los
 * edita.
 */
trait AuthorizesViaProject
{
    private function canReadProject(User $user, Project $project): bool
    {
        return Gate::forUser($user)->allows('view', $project);
    }

    private function canWriteProject(User $user, Project $project): bool
    {
        return Gate::forUser($user)->allows('update', $project);
    }
}
