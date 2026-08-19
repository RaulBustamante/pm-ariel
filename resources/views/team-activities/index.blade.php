@extends('layouts.app')

@section('title', __('team.title'))
@section('heading', __('team.title'))

@section('content')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div>
            <p class="max-w-2xl text-sm text-slate-600">{{ __('team.intro') }}</p>
        </div>
        <a href="{{ route('dashboard') }}" class="btn btn-secondary btn-sm">{{ __('team.back') }}</a>
    </div>

    <form method="GET" class="card mb-4 grid gap-3 p-4 sm:grid-cols-3">
        <div>
            <label for="team-owner" class="field-label">{{ __('team.person') }}</label>
            <select id="team-owner" name="owner_id" class="field">
                <option value="">{{ __('team.everyone') }}</option>
                @foreach ($people as $person)
                    <option value="{{ $person->id }}" @selected((int) $filters['owner_id'] === $person->id)>{{ $person->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="team-status" class="field-label">{{ __('common.status') }}</label>
            <select id="team-status" name="status" class="field">
                @foreach (['open', 'late', 'doing', 'done', 'all'] as $status)
                    <option value="{{ $status }}" @selected($filters['status'] === $status)>{{ __("team.status_{$status}") }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end gap-2">
            <button class="btn btn-primary" type="submit">{{ __('filters.apply') }}</button>
            <a href="{{ route('team-activities.index') }}" class="btn btn-ghost">{{ __('filters.clear') }}</a>
        </div>
    </form>

    <section class="card overflow-hidden">
        @if ($activities->isEmpty())
            <p class="p-8 text-center text-sm text-slate-500">{{ __('team.empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>{{ __('team.person') }}</th>
                            <th>{{ __('team.activity') }}</th>
                            <th>{{ __('team.project') }}</th>
                            <th>{{ __('tasks.start') }}</th>
                            <th>{{ __('tasks.finish') }}</th>
                            <th>{{ __('tasks.progress') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($activities as $task)
                            <tr>
                                <td class="font-medium text-slate-900">{{ $task->owner?->name ?? '—' }}</td>
                                <td>
                                    <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}" class="font-medium text-slate-900 hover:text-brand-700 hover:underline">
                                        {{ $task->name }}
                                    </a>
                                </td>
                                <td><span class="font-mono text-xs text-slate-500">{{ $task->project?->code }}</span></td>
                                <td class="whitespace-nowrap text-xs text-slate-600">{{ $task->early_start?->format('d/m/Y') ?? '—' }}</td>
                                <td class="whitespace-nowrap text-xs {{ $task->early_finish?->isPast() && (float) $task->percent_complete < 100 ? 'font-semibold text-[var(--color-badge-danger-fg)]' : 'text-slate-600' }}">
                                    {{ $task->early_finish?->format('d/m/Y') ?? '—' }}
                                </td>
                                <td class="w-40">
                                    <div class="flex items-center gap-2">
                                        <div class="meter h-1.5 flex-1"><div class="meter-fill" style="width: {{ min(100, (float) $task->percent_complete) }}%"></div></div>
                                        <span class="text-xs tabular text-slate-600">{{ round((float) $task->percent_complete) }} %</span>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-100 p-4">{{ $activities->links() }}</div>
        @endif
    </section>
@endsection
