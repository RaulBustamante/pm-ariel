@extends('layouts.app')

@section('title', __('projects.baseline_comparison'))
@section('heading', $project->name)

@section('content')
    @php
        $durations = new \App\Support\Scheduling\DurationParser;
        $slip = $comparison['finish_variance_minutes'];
    @endphp

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-sm font-semibold text-slate-900">{{ $baseline->name }}</h2>
            <p class="text-xs text-slate-500">
                {{ $baseline->captured_at?->format('d/m/Y H:i') }} · {{ $baseline->capturedBy?->name ?? '—' }}
            </p>
        </div>

        <a href="{{ route('projects.edit', $project) }}" class="btn btn-secondary btn-sm">
            ← {{ __('projects.settings') }}
        </a>
    </div>

    {{-- El titular arriba: la desviación del proyecto entero. Un reporte que
         obliga a sumar columnas para saber si vamos tarde no sirve en una junta. --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-3">
        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('projects.finish_variance') }}</p>
            <p class="mt-1 text-2xl font-bold {{ $slip > 0 ? 'text-red-700' : ($slip < 0 ? 'text-emerald-700' : 'text-slate-900') }}">
                {{ $slip === 0 ? __('projects.on_time') : ($slip > 0 ? '+' : '−').$durations->toHuman(abs($slip)) }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-500">{{ __('projects.variance_help') }}</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('projects.cost_variance') }}</p>
            <p class="mt-1 text-2xl font-bold {{ $comparison['cost_variance'] > 0 ? 'text-red-700' : 'text-slate-900' }}">
                {{ number_format($comparison['cost_variance'], 2) }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-500">{{ $project->currency }}</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('projects.removed_tasks') }}</p>
            <p class="mt-1 text-2xl font-bold {{ $comparison['removed'] !== [] ? 'text-amber-700' : 'text-slate-900' }}">
                {{ count($comparison['removed']) }}
            </p>
            <p class="mt-0.5 text-[11px] text-slate-500">{{ __('projects.removed_help') }}</p>
        </div>
    </div>

    @if ($comparison['removed'] !== [])
        <div role="alert" class="mb-4 rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-900 ring-1 ring-amber-200">
            <p class="font-medium">{{ __('projects.removed_warning') }}</p>
            <p class="mt-1">{{ collect($comparison['removed'])->pluck('name')->implode(', ') }}</p>
        </div>
    @endif

    <div class="card overflow-hidden">
        <div class="overflow-x-auto">
            <table class="data-table">
                <caption class="sr-only">{{ __('projects.baseline_comparison') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('tasks.name') }}</th>
                        <th scope="col">{{ __('projects.start_variance') }}</th>
                        <th scope="col">{{ __('projects.finish_variance') }}</th>
                        <th scope="col">{{ __('projects.cost_variance') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($comparison['tasks'] as $row)
                        <tr>
                            <td class="font-medium">
                                {{ $row['name'] }}
                                @if ($row['is_new'])
                                    <span class="badge badge-brand ml-1">{{ __('projects.task_is_new') }}</span>
                                @endif
                            </td>
                            @foreach (['start_variance_minutes', 'finish_variance_minutes'] as $field)
                                @php $value = $row[$field]; @endphp
                                <td class="{{ $value > 0 ? 'font-semibold text-red-700' : ($value < 0 ? 'text-emerald-700' : 'text-slate-500') }}">
                                    {{ $value === 0 ? '—' : ($value > 0 ? '+' : '−').$durations->toHuman(abs($value)) }}
                                </td>
                            @endforeach
                            <td class="{{ $row['cost_variance'] > 0 ? 'text-red-700' : 'text-slate-500' }}">
                                {{ $row['cost_variance'] == 0.0 ? '—' : number_format($row['cost_variance'], 2) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection
