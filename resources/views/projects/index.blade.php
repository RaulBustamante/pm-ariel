@extends('layouts.app')

@section('title', __('initiation.projects'))
@section('heading', __('initiation.projects'))

@section('content')
    @can('create', App\Models\Project::class)
        <div class="mb-4">
            <a href="{{ route('projects.create') }}"
               class="inline-block btn btn-primary">
                {{ __('initiation.new_project') }}
            </a>
        </div>
    @endcan

    @if ($projects->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-surface p-8 text-center">
            <h2 class="text-base font-semibold">{{ __('common.empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600">{{ __('initiation.no_projects') }}</p>
        </div>
    @else
        <div class="overflow-x-auto rounded-lg bg-surface ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">{{ __('initiation.projects') }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        <th scope="col" class="px-4 py-3">{{ __('initiation.project_code') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('common.name') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('common.org_unit') }}</th>
                        <th scope="col" class="px-4 py-3">{{ __('initiation.health') }}</th>
                        <th scope="col" class="px-4 py-3"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($projects as $project)
                        @php
                            $light = $health->light($project);
                            $completion = $health->completion($project);
                            $badge = match ($light) {
                                'green' => ['bg-[var(--color-badge-ok-bg)] text-[var(--color-badge-ok-fg)]', '✓'],
                                'amber' => ['bg-[var(--color-badge-warn-bg)] text-[var(--color-badge-warn-fg)]', '·'],
                                default => ['bg-[var(--color-badge-danger-bg)] text-[var(--color-badge-danger-fg)]', '!'],
                            };
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $project->code }}</td>
                            <td class="px-4 py-3 font-medium">{{ $project->name }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $project->orgUnit?->name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                {{-- Símbolo y porcentaje además del color. --}}
                                <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium {{ $badge[0] }}">
                                    <span aria-hidden="true">{{ $badge[1] }}</span>
                                    {{ $completion }} %
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('projects.tasks.index', $project) }}"
                                       class="rounded font-medium text-brand-700 underline hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-hud-500">
                                        {{ __('tasks.title') }}<span class="sr-only"> — {{ $project->name }}</span>
                                    </a>
                                    <a href="{{ route('projects.initiation.overview', $project) }}"
                                       class="rounded text-slate-600 underline hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-hud-500">
                                        {{ $completion === 100 ? __('initiation.title') : __('initiation.continue') }}<span class="sr-only"> — {{ $project->name }}</span>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $projects->links() }}</div>
    @endif
@endsection
