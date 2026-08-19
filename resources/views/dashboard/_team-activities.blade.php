@if (($teamMembersCount ?? 0) > 0)
    <section class="card hud-in mt-4 overflow-hidden">
        <div class="card-header">
            <div>
                <h2 class="card-title">{{ __('team.dashboard_title') }}</h2>
                <p class="mt-1 text-xs text-slate-500">{{ __('team.dashboard_help', ['people' => $teamMembersCount]) }}</p>
            </div>
            <a href="{{ route('team-activities.index') }}" class="btn btn-secondary btn-sm">
                {{ __('team.view_all', ['count' => $teamActivitiesTotal]) }}
            </a>
        </div>

        @if ($teamActivities->isEmpty())
            <p class="p-5 text-sm text-slate-500">{{ __('team.empty_open') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('team.person') }}</th>
                            <th>{{ __('team.activity') }}</th>
                            <th>{{ __('team.project') }}</th>
                            <th>{{ __('tasks.finish') }}</th>
                            <th>{{ __('tasks.progress') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($teamActivities as $task)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $task->owner?->name ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">{{ $task->name }}</a>
                                </td>
                                <td class="font-mono text-xs text-slate-500">{{ $task->project?->code }}</td>
                                <td class="whitespace-nowrap text-xs {{ $task->early_finish?->isPast() ? 'font-semibold text-[var(--color-badge-danger-fg)]' : 'text-slate-600' }}">
                                    {{ $task->early_finish?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="text-xs tabular text-slate-600">{{ round((float) $task->percent_complete) }} %</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endif
