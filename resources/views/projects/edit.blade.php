@extends('layouts.app')

@section('title', __('projects.settings'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'settings'])

    <div class="grid gap-4 lg:grid-cols-3">
        <form method="POST" action="{{ route('projects.update', $project) }}" class="card lg:col-span-2">
            @csrf
            @method('PUT')

            <div class="card-header"><h2 class="card-title">{{ __('projects.details') }}</h2></div>

            <div class="space-y-3 p-4">
                <div>
                    <label for="name-field" class="field-label">{{ __('initiation.project_name') }}</label>
                    <input id="name-field" type="text" name="name" value="{{ old('name', $project->name) }}" class="field" required>
                    @error('name') <p role="alert" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <div>
                        <label for="code-field" class="field-label">{{ __('initiation.project_code') }}</label>
                        <input id="code-field" type="text" name="code" value="{{ old('code', $project->code) }}" class="field" required>
                        @error('code') <p role="alert" class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="status-field" class="field-label">{{ __('common.status') }}</label>
                        <select id="status-field" name="status" class="field">
                            @foreach (['draft', 'active', 'on_hold', 'closed', 'cancelled'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $project->status) === $status)>
                                    {{ __("projects.status_{$status}") }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label for="currency-field" class="field-label">{{ __('projects.currency') }}</label>
                        <input id="currency-field" type="text" name="currency" maxlength="3"
                               value="{{ old('currency', $project->currency) }}" class="field uppercase" required>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="start-field" class="field-label">{{ __('tasks.project_start') }}</label>
                        <input id="start-field" type="date" name="planned_start"
                               value="{{ old('planned_start', $project->planned_start?->format('Y-m-d')) }}" class="field">
                        {{-- Se avisa antes, no después: cambiar esta fecha mueve
                             todas las tareas del proyecto. --}}
                        <p class="field-help mt-1">{{ __('projects.start_help') }}</p>
                    </div>

                    <div>
                        <label for="org-unit-field" class="field-label">{{ __('common.org_unit') }}</label>
                        <select id="org-unit-field" name="org_unit_id" class="field">
                            <option value="">—</option>
                            @foreach ($orgUnits as $unit)
                                <option value="{{ $unit->id }}" @selected((int) old('org_unit_id', $project->org_unit_id) === $unit->id)>
                                    {{ str_repeat('— ', $unit->depth) }}{{ $unit->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label for="description-field" class="field-label">{{ __('initiation.project_description') }}</label>
                    <textarea id="description-field" name="description" rows="2" class="field">{{ old('description', $project->description) }}</textarea>
                </div>

                <div class="flex flex-wrap items-center gap-3 border-t border-slate-100 pt-3">
                    <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>

                    <a href="{{ route('projects.calendars.index', $project) }}" class="btn btn-secondary">
                        {{ __('calendars.title') }}
                    </a>
                </div>
            </div>
        </form>

        <aside class="space-y-4">
            {{-- Miembros --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('projects.members') }}</h2></div>

                <div class="p-4">
                    <p class="mb-3 field-help">{{ __('projects.members_help') }}</p>

                    @foreach ($project->members as $member)
                        <div class="flex items-center justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
                            <div class="min-w-0">
                                <p class="truncate text-slate-800">{{ $member->name }}</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ __("projects.role_{$member->pivot->project_role}") }}
                                    @if ($project->owner_id === $member->id)
                                        · {{ __('projects.owner') }}
                                    @endif
                                </p>
                            </div>

                            @if ($project->owner_id !== $member->id)
                                <form method="POST" action="{{ route('projects.members.destroy', [$project, $member]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                </form>
                            @endif
                        </div>
                    @endforeach

                    <form method="POST" action="{{ route('projects.members.store', $project) }}" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                        @csrf
                        <div>
                            <label for="member-field" class="field-label">{{ __('projects.add_member') }}</label>
                            <select id="member-field" name="user_id" class="field" required>
                                @foreach ($candidates as $candidate)
                                    <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="role-field" class="field-label">{{ __('projects.project_role') }}</label>
                            <select id="role-field" name="project_role" class="field">
                                @foreach ([\App\Models\Project::ROLE_MANAGER, \App\Models\Project::ROLE_MEMBER, \App\Models\Project::ROLE_VIEWER] as $role)
                                    <option value="{{ $role }}">{{ __("projects.role_{$role}") }}</option>
                                @endforeach
                            </select>
                        </div>
                        <button type="submit" class="btn btn-secondary w-full">{{ __('projects.add_member') }}</button>
                    </form>
                </div>
            </section>

            {{-- Líneas base --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('projects.baselines') }}</h2>
                </div>

                <div class="p-4">
                    <p class="mb-3 field-help">{{ __('projects.baselines_help') }}</p>

                    @forelse ($baselines as $baseline)
                        <div class="flex items-start justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
                            <div class="min-w-0">
                                <p class="truncate text-slate-800">{{ $baseline->name }}</p>
                                <p class="text-[11px] text-slate-500">
                                    {{ $baseline->captured_at?->format('d/m/Y') }} · {{ $baseline->capturedBy?->name ?? '—' }}
                                </p>
                            </div>
                            @if ($baseline->is_active)
                                <span class="badge badge-ok shrink-0">{{ __('projects.baseline_active') }}</span>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('projects.no_baselines') }}</p>
                    @endforelse

                    @can('update', $project)
                        <form method="POST" action="{{ route('projects.baselines.store', $project) }}" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                            @csrf
                            <div>
                                <label for="baseline-name" class="field-label">{{ __('projects.baseline_name') }}</label>
                                <input id="baseline-name" type="text" name="name" class="field"
                                       value="{{ __('projects.baseline_default_name', ['date' => now()->format('d/m/Y')]) }}" required>
                            </div>
                            <button type="submit" class="btn btn-secondary w-full">{{ __('projects.capture_baseline') }}</button>
                        </form>
                    @endcan
                </div>
            </section>
        </aside>
    </div>
@endsection
