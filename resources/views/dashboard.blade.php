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
                            {{-- La lista va completa y la tarjeta se recorre.
                                 Antes cabían ocho y el resto era «y 9 más», que
                                 dice cuántas faltan pero no cuáles — y obliga a
                                 salir del inicio a averiguarlo.

                                 `tabindex` no es un adorno: una caja que se
                                 desplaza y no recibe foco es una caja que solo
                                 se puede recorrer con ratón. Con foco, las
                                 flechas y AvPág la recorren. --}}
                            <div class="scroll-pane mt-2 max-h-52 pr-1"
                                 tabindex="0"
                                 role="group"
                                 aria-label="{{ __("dashboard.week_{$key}") }}">
                                <ul class="space-y-1.5">
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
                            </div>

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

        {{-- ------------------------------------------------------------------
             La cartera: todos los proyectos en un renglón cada uno.

             Las tarjetas contestaban «¿cómo va este?» doce veces y obligaban a
             recorrerlas con la vista para encontrar el que va mal. Una tabla
             contesta «¿cómo vamos?», que es la pregunta de quien tiene más de un
             proyecto encima — y deja comparar dos proyectos, que en tarjetas
             separadas no se puede.
             ------------------------------------------------------------------ --}}
        @php $totals = $portfolio['totals']; @endphp

        <section class="card card-hud hud-in mb-4 p-4">
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ([
                    ['projects', $totals['projects'], null],
                    ['late_projects', $totals['late_projects'], $totals['late_projects'] > 0],
                    ['overdue', $totals['overdue'], $totals['overdue'] > 0],
                    ['alerts', $totals['alerts'], false],
                ] as [$key, $value, $bad])
                    <div>
                        <p class="stat-label">{{ __("portfolio.total_{$key}") }}</p>
                        <p class="stat-value {{ $bad ? 'text-[var(--color-badge-danger-fg)]' : '' }}">{{ $value }}</p>
                    </div>
                @endforeach

                @if ($withCosts)
                    <div>
                        <p class="stat-label">{{ __('portfolio.total_cost') }}</p>
                        <p class="stat-value tabular">
                            {{ number_format($totals['cost'], 0) }}<span class="stat-unit"> {{ __('resources.currency') }}</span>
                        </p>
                        {{-- Lo devengado contra lo planeado, en una barra: es la
                             cifra que dice si el gasto va al ritmo del avance. --}}
                        @if ($totals['cost'] > 0)
                            <div class="meter mt-2 h-1.5">
                                <div class="meter-fill" style="width: {{ min(100, round($totals['earned'] / $totals['cost'] * 100)) }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-slate-500">
                                {{ __('portfolio.earned_share', ['percent' => round($totals['earned'] / $totals['cost'] * 100)]) }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>
        </section>

        <section class="card hud-in">
            <div class="card-header">
                <h2 class="card-title">{{ __('portfolio.title') }}</h2>
                <span class="text-xs text-slate-500">{{ $totals['projects'] }}</span>
            </div>

            <p class="px-5 pt-3 text-xs leading-relaxed text-slate-600">{{ __('portfolio.help') }}</p>

            <div class="overflow-x-auto">
                <table class="data-table">
                    <caption class="sr-only">{{ __('portfolio.title') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('portfolio.project') }}</th>
                            <th scope="col" class="w-32">{{ __('common.status') }}</th>
                            <th scope="col" class="w-44">{{ __('dashboard.progress') }}</th>
                            <th scope="col" class="w-24 text-right">{{ __('portfolio.tasks') }}</th>
                            <th scope="col" class="w-24 text-right">{{ __('dashboard.overdue') }}</th>
                            <th scope="col" class="w-28">{{ __('dashboard.finish') }}</th>
                            @if ($withCosts)
                                <th scope="col" class="w-28 text-right">{{ __('portfolio.hours') }}</th>
                                <th scope="col" class="w-36 text-right">{{ __('resources.cost') }}</th>
                            @endif
                            <th scope="col" class="w-36">{{ __('portfolio.owner') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($portfolio['rows'] as $row)
                            @php
                                $badge = match ($row['light']) {
                                    'green' => ['badge-ok', '✓'],
                                    'amber' => ['badge-warn', '·'],
                                    default => ['badge-danger', '!'],
                                };
                            @endphp
                            <tr>
                                <td>
                                    <a href="{{ route('projects.dashboard', $row['project']) }}"
                                       class="font-medium text-slate-900 hover:text-brand-700 hover:underline">
                                        {{ $row['project']->name }}
                                    </a>
                                    <span class="block font-mono text-[11px] text-slate-500">{{ $row['project']->code }}</span>
                                </td>

                                <td>
                                    {{-- Símbolo además de color: el semáforo no
                                         puede depender de distinguir rojo. --}}
                                    <span class="badge {{ $badge[0] }}">
                                        <span aria-hidden="true">{{ $badge[1] }}</span>
                                        {{ __("dashboard.light_{$row['light']}") }}
                                    </span>
                                </td>

                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="meter h-1.5 flex-1">
                                            <div class="meter-fill" style="width: {{ min(100, $row['progress']) }}%"></div>
                                        </div>
                                        <span class="w-10 shrink-0 text-right text-[11px] tabular text-slate-600">{{ round($row['progress']) }} %</span>
                                    </div>
                                </td>

                                <td class="text-right tabular text-slate-600">
                                    {{ $row['done'] }}<span class="text-slate-400">/{{ $row['tasks'] }}</span>
                                </td>

                                <td class="text-right tabular {{ $row['overdue'] > 0 ? 'font-semibold text-[var(--color-badge-danger-fg)]' : 'text-slate-500' }}">
                                    {{ $row['overdue'] > 0 ? $row['overdue'] : '—' }}
                                </td>

                                <td class="whitespace-nowrap text-xs text-slate-600">{{ $row['finish']?->format('d/m/y') ?? '—' }}</td>

                                @if ($withCosts)
                                    <td class="text-right tabular text-slate-600">{{ number_format($row['hours'], 0) }}</td>
                                    <td class="text-right tabular font-medium text-slate-900">{{ number_format($row['cost'], 0) }}</td>
                                @endif

                                <td class="truncate text-xs text-slate-600">{{ $row['owner'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Cuánto pesa cada proyecto dentro de la cartera.
             La tabla dice cuánto cuesta cada uno; esto dice cuál se lleva el
             dinero, que es una pregunta distinta y no se contesta leyendo doce
             números en columna. --}}
        @if ($withCosts && $totals['cost'] > 0)
            <section class="card hud-in mt-4 p-4">
                <h2 class="card-title mb-3">{{ __('portfolio.weight') }}</h2>

                <div class="flex h-4 w-full overflow-hidden rounded-md" role="img"
                     aria-label="{{ __('portfolio.weight') }}">
                    @foreach ($portfolio['rows'] as $index => $row)
                        @continue($row['cost'] <= 0)
                        <div class="h-full"
                             style="width: {{ $row['cost'] / $totals['cost'] * 100 }}%; background-color: var(--color-viz-{{ $index % 8 + 1 }})"
                             title="{{ $row['project']->code }}: {{ number_format($row['cost'], 0) }}"></div>
                    @endforeach
                </div>

                <ul class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5 text-[11px] text-slate-600">
                    @foreach ($portfolio['rows'] as $index => $row)
                        @continue($row['cost'] <= 0)
                        <li class="flex items-center gap-1.5">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-sm"
                                  style="background-color: var(--color-viz-{{ $index % 8 + 1 }})" aria-hidden="true"></span>
                            <span class="font-mono">{{ $row['project']->code }}</span>
                            <span class="tabular text-slate-500">{{ round($row['cost'] / $totals['cost'] * 100) }} %</span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('dashboard._team-activities')
    @endif
@endsection
