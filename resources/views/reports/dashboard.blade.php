@extends('layouts.app')

@section('title', __('dashboard.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'dashboard'])

    @php
        $lightClasses = match ($light) {
            'green' => ['bg-[var(--color-badge-ok-bg)] ring-[var(--color-badge-ok-line)] text-[var(--color-badge-ok-fg)]', '✓'],
            'amber' => ['bg-[var(--color-badge-warn-bg)] ring-[var(--color-badge-warn-line)] text-[var(--color-badge-warn-fg)]', '·'],
            default => ['bg-[var(--color-badge-danger-bg)] ring-[var(--color-badge-danger-line)] text-[var(--color-badge-danger-fg)]', '!'],
        };
        $behind = $kpis['elapsed_percent'] - $kpis['progress'];
    @endphp

    {{-- Semáforo con su porqué. Uno que solo se pinta obliga a preguntarle a
         alguien, y ese alguien es el que ya sabía la respuesta. --}}
    <section class="mb-4 rounded-lg p-4 ring-1 {{ $lightClasses[0] }}">
        <div class="flex items-start gap-3">
            <span aria-hidden="true" class="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface/80 text-base font-bold">
                {{ $lightClasses[1] }}
            </span>
            <div class="min-w-0">
                <p class="text-sm font-semibold">{{ __("dashboard.light_{$light}") }}</p>
                <ul class="mt-1 space-y-0.5 text-sm">
                    @foreach ($reasons as $reason)
                        <li>· {{ $reason }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </section>

    {{-- KPIs --}}
    <div class="mb-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('dashboard.progress') }}</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $kpis['progress'] }} %</p>
            <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100"
                 role="progressbar" aria-valuenow="{{ $kpis['progress'] }}" aria-valuemin="0" aria-valuemax="100">
                <div class="h-full bg-brand-600" style="width: {{ min(100, $kpis['progress']) }}%"></div>
            </div>
            <p class="mt-1 text-[11px] text-slate-500">{{ __('dashboard.progress_help') }}</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('dashboard.elapsed') }}</p>
            <p class="mt-1 text-2xl font-bold {{ $behind > 10 ? 'text-[var(--color-badge-danger-fg)]' : 'text-slate-900' }}">
                {{ $kpis['elapsed_percent'] }} %
            </p>
            <p class="mt-2 text-[11px] {{ $behind > 10 ? 'font-medium text-[var(--color-badge-danger-fg)]' : 'text-slate-500' }}">
                {{ $behind > 10
                    ? __('dashboard.behind_by', ['points' => round($behind, 1)])
                    : __('dashboard.elapsed_help') }}
            </p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('dashboard.overdue') }}</p>
            <p class="mt-1 text-2xl font-bold {{ $kpis['overdue'] > 0 ? 'text-[var(--color-badge-danger-fg)]' : 'text-slate-900' }}">
                {{ $kpis['overdue'] }}
            </p>
            <p class="mt-2 text-[11px] text-slate-500">{{ __('dashboard.overdue_help') }}</p>
        </div>

        <div class="card p-4">
            <p class="text-xs text-slate-600">{{ __('dashboard.finish') }}</p>
            <p class="mt-1 text-2xl font-bold text-slate-900">{{ $kpis['finish']?->format('d/m/y') ?? '—' }}</p>

            {{-- La fecha comprometida contra la que calcula el plan.
                 Son dos cosas distintas y la diferencia entre ellas es la
                 conversación que hay que tener a tiempo: el motor dice cuándo
                 acaba esto, no cuándo se prometió que acabaría. --}}
            @php
                $committed = $project->planned_finish;
                $slip = $committed && $kpis['finish']
                    ? (int) $committed->startOfDay()->diffInDays($kpis['finish'], false)
                    : null;
            @endphp

            @if ($committed)
                <p class="mt-2 text-[11px] {{ ($slip ?? 0) > 0 ? 'font-medium text-[var(--color-badge-danger-fg)]' : 'text-slate-500' }}">
                    @if (($slip ?? 0) > 0)
                        {{ __('projects.over_committed', ['days' => $slip]) }}
                    @else
                        {{ __('projects.planned_finish') }}: {{ $committed->format('d/m/y') }}
                    @endif
                </p>
            @endif

            <p class="mt-2 text-[11px] text-slate-500">
                {{ __('tasks.critical_path') }}: {{ $kpis['critical'] }}
            </p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Curva S --}}
        <section class="card lg:col-span-2">
            <div class="card-header">
                <h2 class="card-title">{{ __('dashboard.s_curve') }}</h2>
            </div>

            <div class="p-4">
                @if ($curve['labels'] === [])
                    <p class="py-8 text-center text-sm text-slate-500">{{ __('dashboard.no_data') }}</p>
                @else
                    @php
                        $width = 640; $height = 220; $padLeft = 44; $padBottom = 26; $padTop = 10;
                        $count = max(1, count($curve['labels']) - 1);
                        $stepX = ($width - $padLeft - 10) / $count;
                        $scaleY = fn (float $v): float => $height - $padBottom - ($v / $curve['max']) * ($height - $padBottom - $padTop);
                        $pointsFor = function (array $values) use ($stepX, $padLeft, $scaleY): string {
                            $parts = [];
                            foreach ($values as $i => $v) {
                                $parts[] = round($padLeft + $i * $stepX, 1).','.round($scaleY((float) $v), 1);
                            }
                            return implode(' ', $parts);
                        };
                    @endphp

                    <svg viewBox="0 0 {{ $width }} {{ $height }}" class="w-full" role="img"
                         aria-label="{{ __('dashboard.s_curve_label') }}">
                        <desc>{{ __('dashboard.s_curve_description') }}</desc>

                        {{-- Rejilla horizontal, con su escala. Una curva sin eje
                             se ve bonita y no se puede leer. --}}
                        @foreach ([0, 25, 50, 75, 100] as $percent)
                            @php $y = $scaleY($curve['max'] * $percent / 100); @endphp
                            <line x1="{{ $padLeft }}" y1="{{ $y }}" x2="{{ $width - 10 }}" y2="{{ $y }}"
                                  class="c-grid" stroke-width="1" />
                            <text x="{{ $padLeft - 6 }}" y="{{ $y + 3 }}" text-anchor="end"
                                  font-size="9" class="c-axis" font-family="sans-serif">{{ $percent }}%</text>
                        @endforeach

                        <polyline points="{{ $pointsFor($curve['planned']) }}"
                                  fill="none" class="c-planned" stroke-width="2" stroke-dasharray="4 3" />

                        @if ($curve['actual'] !== [])
                            <polyline points="{{ $pointsFor($curve['actual']) }}"
                                      fill="none" class="c-actual" stroke-width="2.5" />
                        @endif

                        @foreach ($curve['labels'] as $i => $label)
                            @if ($i % max(1, (int) ceil(count($curve['labels']) / 8)) === 0)
                                <text x="{{ round($padLeft + $i * $stepX, 1) }}" y="{{ $height - 8 }}"
                                      text-anchor="middle" font-size="9" class="c-axis" font-family="sans-serif">{{ $label }}</text>
                            @endif
                        @endforeach
                    </svg>

                    <div class="mt-2 flex flex-wrap gap-4 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1.5">
                            <svg width="24" height="6" aria-hidden="true"><line x1="0" y1="3" x2="24" y2="3" class="c-planned" stroke-width="2" stroke-dasharray="4 3"/></svg>
                            {{ __('dashboard.planned') }}
                        </span>
                        <span class="inline-flex items-center gap-1.5">
                            <svg width="24" height="6" aria-hidden="true"><line x1="0" y1="3" x2="24" y2="3" class="c-actual" stroke-width="2.5"/></svg>
                            {{ __('dashboard.actual') }}
                        </span>
                    </div>

                    <p class="mt-2 text-xs text-slate-500">{{ __('dashboard.s_curve_help') }}</p>
                @endif
            </div>
        </section>

        <aside class="space-y-4">
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('dashboard.distribution') }}</h2></div>
                <div class="space-y-3 p-4">
                    @foreach ([
                        ['label' => __('kanban.done'), 'value' => $kpis['done'], 'class' => 'bar-ok'],
                        ['label' => __('kanban.doing'), 'value' => $kpis['doing'], 'class' => 'bg-brand-600'],
                        ['label' => __('kanban.todo'), 'value' => $kpis['todo'], 'class' => 'bg-slate-400'],
                    ] as $slice)
                        @php $pct = $kpis['task_count'] > 0 ? round($slice['value'] / $kpis['task_count'] * 100) : 0; @endphp
                        <div>
                            <div class="flex justify-between text-xs">
                                <span class="text-slate-700">{{ $slice['label'] }}</span>
                                <span class="font-medium text-slate-900">{{ $slice['value'] }} · {{ $pct }}%</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div class="h-full {{ $slice['class'] }}" style="width: {{ $pct }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('reports.title') }}</h2></div>
                <div class="space-y-2 p-4">
                    {{-- El completo va primero y es el botón fuerte: es el que
                         se manda a dirección. Los otros dos siguen ahí para
                         quien solo quiere la ficha o solo el diagrama. --}}
                    <a href="{{ route('projects.reports.complete', $project) }}" class="btn btn-primary w-full">
                        {{ __('reports.complete') }}
                    </a>
                    <p class="field-help -mt-1 mb-1">{{ __('reports.complete_help') }}</p>

                    <a href="{{ route('projects.reports.pdf', $project) }}" class="btn btn-secondary w-full">
                        {{ __('reports.download_pdf') }}
                    </a>
                    <a href="{{ route('projects.reports.gantt', $project) }}" class="btn btn-secondary w-full">
                        {{ __('reports.gantt_print') }}
                    </a>
                    <a href="{{ route('projects.reports.csv', $project) }}" class="btn btn-secondary w-full">
                        {{ __('reports.download_csv') }}
                    </a>
                </div>
            </section>
        </aside>
    </div>
@endsection
