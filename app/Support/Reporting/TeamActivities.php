<?php

declare(strict_types=1);

namespace App\Support\Reporting;

use App\Models\Task;
use App\Models\User;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Database\Eloquent\Builder;

/** Tareas de la cadena de mando del usuario, sin incluir las propias. */
final class TeamActivities
{
    /** @return list<int> */
    public function userIds(User $manager, VisibilityScope $visibility): array
    {
        return array_values(array_filter(
            $visibility->visibleUserIds($manager),
            fn (int $id): bool => $id !== $manager->id,
        ));
    }

    /** @return Builder<Task> */
    public function query(User $manager, VisibilityScope $visibility): Builder
    {
        $userIds = $this->userIds($manager, $visibility);

        return Task::query()
            ->with(['owner:id,name,email', 'project:id,code,name'])
            ->whereIn('owner_id', $userIds)
            ->where('is_summary', false)
            ->whereHas('project', fn (Builder $projects) => $visibility->scopeProjects($projects, $manager));
    }
}
