<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Project;
use App\Models\Stakeholder;
use App\Models\User;
use App\Policies\Concerns\AuthorizesViaProject;

final class StakeholderPolicy
{
    use AuthorizesViaProject;

    public function view(User $user, Stakeholder $stakeholder): bool
    {
        return $this->canReadProject($user, $stakeholder->loadMissing('project')->project);
    }

    public function create(User $user, Project $project): bool
    {
        return $this->canWriteProject($user, $project);
    }

    public function update(User $user, Stakeholder $stakeholder): bool
    {
        return $this->canWriteProject($user, $stakeholder->loadMissing('project')->project);
    }

    public function delete(User $user, Stakeholder $stakeholder): bool
    {
        return $this->update($user, $stakeholder);
    }
}
