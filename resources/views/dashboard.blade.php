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
    @if (($week ?? null) && array_sum($week['counts']) > 0)
        {{-- Mi semana, cruzando todos los proyectos.
             El corte semanal contesta «cómo va este proyecto»; esto contesta la
             otra pregunta, la de la mañana: «¿qué me toca?». Nadie trabaja en un
             proyecto a la vez, y armar la lista entrando a cinco es justo el
             trabajo que el sistema debería estar haciendo. --}}
        <section class="mb-5">
            <div class="mb-2 flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('dashboard.my_week') }}</h2>
                <p class="text-xs text-slate-500">
                    {{ __('reports.week_of', ['from' => $week['from']->format('d/m'), 'to' => $week['to']->format('d/m')]) }}
                </p>
            </div>

            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @foreach ([
                    ['late', 'badge-danger', 'meter-fill-danger'],
                    ['due', 'badge-warn', 'meter-fill-warn'],
                    ['next', 'badge-neutral', 'meter-fill'],
                    ['closed', 'badge-ok', 'meter-fill'],
                ] as $index => [$key, $badge, $fill])
                    <div class="card hud-in hud-in-{{ min(4, $index + 1) }} p-3">
                        <div class="flex items-baseline justify-between gap-2">
                            <p class="stat-label">{{ __("dashboard.week_{$key}") }}</p>
                            <span class="badge {{ $badge }}">{{ $week['counts'][$key] }}</span>
                        </div>

                        @if ($week[$key]->isEmpty())
                            <p class="mt-2 text-xs italic text-slate-500">{{ __("dashboard.week_no_{$key}") }}</p>
                        @else
                            <ul class="mt-2 space-y-1.5">
                                @foreach ($week[$key] as $task)
                                    <li class="min-w-0">
                                        <a href="{{ route('projects.tasks.show', [$task->project, $task]) }}"
                                           class="block truncate text-xs text-slate-800 hover:text-hud-400">
                                            {{ $task->name }}
                                        </a>
                                        {{-- La clave del proyecto en cada renglón: sin
                                             ella, una lista que mezcla cinco proyectos
                                             obliga a adivinar de cuál es cada tarea. --}}
                                        <span class="font-mono text-[10px] text-slate-500">
                                            {{ $task->project?->code }}
                                            @if ($key === 'late' && $task->owner_id === null)
                                                · {{ __('reports.unassigned') }}
                                            @elseif ($task->early_finish)
                                                · {{ $task->early_finish->format('d/m') }}
                                            @endif
                                        </span>
                                    </li>
                                @endforeach
                            </ul>

                            @if ($week['counts'][$key] > $week[$key]->count())
                                <p class="mt-2 text-[10px] text-slate-500">
                                    {{ __('reports.and_more', ['count' => $week['counts'][$key] - $week[$key]->count()]) }}
                                </p>
                            @endif
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

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
                                <dd class="inline font-semibold text-[var(--color-badge-danger-fg)]">{{ $row['overdue'] }}</dd>
                            </div>
                        @endif
                    </dl>
                </a>
            @endforeach
        </div>
    @endif
@endsection
