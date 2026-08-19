<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Reporting\TeamActivities;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class TeamActivityController extends Controller
{
    public function index(
        Request $request,
        VisibilityScope $visibility,
        TeamActivities $activities,
    ): View {
        /** @var User $viewer */
        $viewer = $request->user();
        $userIds = $activities->userIds($viewer, $visibility);

        $filters = $request->validate([
            'owner_id' => ['nullable', 'integer', Rule::in($userIds)],
            'status' => ['nullable', Rule::in(['open', 'late', 'doing', 'done', 'all'])],
        ]);

        $status = (string) ($filters['status'] ?? 'open');
        $query = $activities->query($viewer, $visibility);

        if (isset($filters['owner_id'])) {
            $query->where('owner_id', (int) $filters['owner_id']);
        }

        match ($status) {
            'late' => $query->where('percent_complete', '<', 100)
                ->whereNotNull('early_finish')
                ->where('early_finish', '<', now()),
            'doing' => $query->where('percent_complete', '>', 0)->where('percent_complete', '<', 100),
            'done' => $query->where('percent_complete', '>=', 100),
            'all' => null,
            default => $query->where('percent_complete', '<', 100),
        };

        return view('team-activities.index', [
            'activities' => $query
                ->orderByRaw('early_finish IS NULL')
                ->orderBy('early_finish')
                ->orderBy('id')
                ->paginate(50)
                ->withQueryString(),
            'people' => User::query()->whereIn('id', $userIds)->orderBy('name')->get(),
            'filters' => ['owner_id' => $filters['owner_id'] ?? null, 'status' => $status],
        ]);
    }
}
