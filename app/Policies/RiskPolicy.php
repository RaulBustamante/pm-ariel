<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\Risk;
use App\Models\User;
use App\Policies\Concerns\AuthorizesViaProject;

final class RiskPolicy
{
    use AuthorizesViaProject;

    public function view(User $user, Risk $risk): bool
    {
        return $this->canReadProject($user, $risk->loadMissing('project')->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canWriteProject($user, $project);
    }

    public function update(User $user, Risk $risk): bool
    {
        return $this->canWriteProject($user, $risk->loadMissing('project')->project);
    }

    public function delete(User $user, Risk $risk): bool
    {
        return $this->update($user, $risk);
    }
}
