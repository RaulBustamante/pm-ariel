@extends('layouts.app')

@section('title', __('common.dashboard'))
@section('heading', __('common.dashboard'))

@section('content')
    @php
        /** @var \Illuminate\Support\Collection $projects */
        $projects = $projects ?? collect();
    @endphp

    @if ($projects->isEmpty())
        {{-- Estado vacío con guía: qué es esto, por qué está vacío y qué hacer.
             Una pantalla en blanco deja al usuario adivinando si el sistema
             falló o si simplemente no ha empezado. --}}
        <div class="card p-8 text-center">
            <h2 class="text-base font-semibold text-slate-900">{{ __('dashboard.empty_title') }}</h2>
            <p class="mx-auto mt-2 max-w-xl text-sm leading-relaxed text-slate-600">{{ __('dashboard.empty_body') }}</p>

            <div class="mt-6 flex flex-wrap justify-center gap-3">
                @can('create', App\Models\Project::class)
                    <a href="{{ route('projects.create') }}" class="btn btn-primary">
                        {{ __('dashboard.empty_action') }}
                    </a>
                @endcan

                <a href="{{ route('onboarding') }}" class="btn btn-secondary">
                    {{ __('onboarding.title') }}
                </a>
            </div>
        </div>
    @else
        <p class="mb-4 max-w-3xl text-sm text-slate-600">{{ __('dashboard.intro') }}</p>

        <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($projects as $row)
                @php
                    $badge = match ($row['light']) {
                        'green' => ['badge-ok', '✓'],
                        'amber' => ['badge-warn', '·'],
                        default => ['badge-danger', '!'],
                    };
                @endphp

                <a href="{{ route('projects.dashboard', $row['project']) }}"
                   class="card block p-4 transition-colors hover:border-brand-400">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-slate-900">{{ $row['project']->name }}</p>
                            <p class="font-mono text-[11px] text-slate-500">{{ $row['project']->code }}</p>
                        </div>

                        {{-- Semáforo con símbolo, no solo color. --}}
                        <span class="badge {{ $badge[0] }} shrink-0">
                            <span aria-hidden="true">{{ $badge[1] }}</span>
                            {{ __("dashboard.light_{$row['light']}") }}
                        </span>
                    </div>

                    <div class="mt-3">
                        <div class="flex justify-between text-[11px] text-slate-600">
                            <span>{{ __('dashboard.progress') }}</span>
                            <span class="font-medium text-slate-900">{{ $row['progress'] }} %</span>
                        </div>
                        <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div class="h-full bg-brand-600" style="width: {{ min(100, $row['progress']) }}%"></div>
                        </div>
                    </div>

                    <dl class="mt-3 flex gap-4 text-[11px] text-slate-600">
                        <div>
                            <dt class="inline">{{ __('dashboard.finish') }}:</dt>
                            <dd class="inline font-medium text-slate-800">{{ $row['finish']?->format('d/m/y') ?? '—' }}</dd>
                        </div>
                        @if ($row['overdue'] > 0)
                            <div>
                                <dt class="inline">{{ __('dashboard.overdue') }}:</dt>
                                <dd class="inline font-semibold text-red-700">{{ $row['overdue'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </a>
            @endforeach
        </div>
    @endif
@endsection
