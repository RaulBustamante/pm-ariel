@extends('layouts.app')

@section('title', __('advisor.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'advisor'])

    @php
        $lightClasses = match ($light) {
            'green' => 'badge-ok',
            'amber' => 'badge-warn',
            default => 'badge-danger',
        };
        $lightMark = match ($light) { 'green' => '✓', 'amber' => '·', default => '!' };
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-5 lg:col-span-2">
            <section class="flex items-start gap-3 rounded-lg p-4 ring-1 {{ $lightClasses }}">
                <span aria-hidden="true"
                      class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-surface/70 text-sm font-bold">
                    {{ $lightMark }}
                </span>
                <div>
                    <p class="text-sm font-semibold">
                        {{ $findings->isEmpty() ? __('advisor.none') : __('advisor.heading') }}
                    </p>
                    <p class="mt-0.5 text-xs">
                        @if ($lastCheck)
                            {{ __('advisor.last_check', ['when' => $lastCheck->diffForHumans()]) }}
                        @else
                            {{ __('advisor.intro') }}
                        @endif
                    </p>
                </div>
            </section>

            @foreach ($findings as $finding)
                @php
                    $badge = match ($finding->severity) {
                        \App\Models\ProjectFinding::SEVERITY_CRITICAL => ['bg-[var(--color-badge-danger-bg)] text-[var(--color-badge-danger-fg)]', '!'],
                        \App\Models\ProjectFinding::SEVERITY_WARNING => ['bg-[var(--color-badge-warn-bg)] text-[var(--color-badge-warn-fg)]', '·'],
                        default => ['bg-slate-100 text-slate-700', 'i'],
                    };
                @endphp

                <article class="rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                    <div class="flex items-start gap-3">
                        {{-- Símbolo y etiqueta además del color: la gravedad no
                             puede depender de distinguir rojo de ámbar. --}}
                        <span aria-hidden="true"
                              class="mt-0.5 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $badge[0] }}">
                            {{ $badge[1] }}
                        </span>

                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">
                                {{ __("advisor.severity_{$finding->severity}") }}
                            </p>

                            <p class="mt-0.5 text-sm font-semibold text-slate-900">{{ $finding->message }}</p>

                            {{-- El porqué siempre, y siempre completo. Un aviso
                                 sin causa obliga a adivinar, que es justo lo que
                                 este panel existe para evitar. --}}
                            <p class="mt-1 text-sm text-slate-600">{{ $finding->why }}</p>

                            @if ($finding->task_id)
                                <a href="{{ route('projects.tasks.index', $project) }}"
                                   class="mt-2 inline-block rounded text-xs text-brand-700 underline hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-hud-500">
                                    {{ $finding->task?->name ?? __('tasks.title') }} →
                                </a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach

            {{-- Se dice en la pantalla, no solo en el decision log: quien la usa
                 debe saber por qué el sistema no le está diciendo qué hacer. --}}
            <p class="rounded-md bg-slate-50 px-4 py-3 text-xs leading-relaxed text-slate-600 ring-1 ring-slate-200">
                {{ __('advisor.why_no_suggestion') }}
            </p>
        </div>

        <aside class="space-y-4">
            <form method="POST" action="{{ route('projects.advisor.analyze', $project) }}">
                @csrf
                <button type="submit"
                        class="w-full btn btn-primary">
                    {{ __('advisor.analyze') }}
                </button>
            </form>

            <section class="rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('advisor.workload') }}</h2>
                <p class="mt-1 text-xs text-slate-600">{{ __('advisor.workload_intro') }}</p>

                @if ($workload === [])
                    <p class="mt-3 text-xs text-slate-500">{{ __('advisor.no_resources') }}</p>
                @else
                    <ul class="mt-3 space-y-2.5">
                        @foreach ($workload as $row)
                            @php
                                $peak = $row['peak'];
                                $capacity = (int) $row['resource']->capacity_percent;
                                $over = $peak > max(100, $capacity);
                            @endphp
                            <li>
                                <div class="flex items-baseline justify-between gap-2 text-xs">
                                    <span class="truncate font-medium text-slate-800">{{ $row['resource']->name }}</span>
                                    <span class="shrink-0 {{ $over ? 'font-semibold text-[var(--color-badge-danger-fg)]' : 'text-slate-600' }}">
                                        {{ $peak }} %
                                    </span>
                                </div>

                                {{-- La barra puede pasar del 100 %: recortarla ahí
                                     escondería exactamente el problema. --}}
                                <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full {{ $over ? 'bar-danger' : 'bar-brand' }}"
                                         style="width: {{ min(100, $peak) }}%"></div>
                                </div>

                                <p class="mt-0.5 text-[11px] text-slate-500">
                                    {{ __('advisor.capacity') }} {{ $capacity }} % ·
                                    {{ $row['tasks'] }} {{ mb_strtolower(__('advisor.assigned_tasks')) }}
                                </p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            @can('update', $project)
                <form method="POST" action="{{ route('projects.resources.store', $project) }}"
                      class="space-y-3 rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                    @csrf

                    <h2 class="text-sm font-semibold text-slate-900">{{ __('resources.add') }}</h2>

                    <x-form-field name="name" :label="__('resources.name')" required />
                    <x-form-field name="role_title" :label="__('resources.role')" />
                    <x-form-field name="email" type="email" :label="__('resources.email')" />
                    <x-form-field name="capacity_percent" type="number" :label="__('resources.capacity')"
                                  value="100" :help="__('resources.capacity_help')" required />

                    <button type="submit"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-hud-500 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-hud-500">
                        {{ __('resources.add') }}
                    </button>
                </form>
            @endcan
        </aside>
    </div>
@endsection
