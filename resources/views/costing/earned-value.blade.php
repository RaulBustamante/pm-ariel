@extends('layouts.app')

@section('title', __('evm.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'analysis'])

    @php
        /** Un número que puede no existir. «No se puede calcular» no es cero. */
        $money = fn (?float $value): string => $value === null ? '—' : number_format($value, 2);
        $index = fn (?float $value): string => $value === null ? '—' : number_format($value, 2);
    @endphp

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900">{{ __('evm.title') }}</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __('evm.intro') }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('projects.earned-value.pdf', [$project, 'at' => $statusDate->format('Y-m-d')]) }}"
               class="btn btn-secondary btn-sm">{{ __('evm.download') }}</a>

            <a href="{{ route('projects.documents', $project) }}" class="btn btn-ghost btn-sm">
                {{ __('documents.title') }}
            </a>
        </div>
    </div>

    {{-- La fecha de corte. Los índices de hoy no explican una junta de hace tres
         semanas, y recalcularlos a aquella fecha es la única forma honesta de
         decir qué se sabía entonces. --}}
    <form method="GET" action="{{ route('projects.earned-value', $project) }}"
          class="card hud-in mb-4 flex flex-wrap items-end gap-3 p-4">
        <div>
            <label for="at" class="field-label">{{ __('evm.status_date') }}</label>
            <input type="date" id="at" name="at" value="{{ $statusDate->format('Y-m-d') }}" class="field">
        </div>

        <button type="submit" class="btn btn-secondary">{{ __('evm.recalculate') }}</button>

        <p class="field-help max-w-xl flex-1">{{ __('evm.status_date_help') }}</p>
    </form>

    {{-- Lo que falta para que los números signifiquen algo, antes que los
         números. Un tablero que enseña un CPI calculado sobre un tercio del
         gasto y explica la letra chica después ya convenció a quien lo miró. --}}
    @unless ($evm['has_baseline'])
        <p class="badge-warn mb-4 block rounded-md border px-3 py-2 text-xs leading-relaxed">{{ __('evm.no_baseline') }}</p>
    @endunless

    @if ($evm['started_tasks'] === 0)
        <p class="badge-neutral mb-4 block rounded-md border px-3 py-2 text-xs leading-relaxed">{{ __('evm.nothing_started') }}</p>
    @elseif ($evm['missing_actuals'] > 0)
        <div class="badge-warn mb-4 rounded-md border px-3 py-2 text-xs leading-relaxed">
            <p class="font-semibold">{{ __('evm.no_actuals_title') }}</p>
            <p class="mt-0.5">
                {{ __('evm.no_actuals', ['started' => $evm['started_tasks'], 'missing' => $evm['missing_actuals']]) }}
            </p>
            <p class="mt-0.5">{{ __('evm.no_actuals_where') }}</p>
        </div>
    @endif

    {{-- Las tres cifras y el presupuesto --}}
    <section class="card card-hud hud-in mb-4 p-4">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['pv', $evm['pv']],
                ['ev', $evm['ev']],
                ['ac', $evm['ac']],
                ['bac', $evm['bac']],
            ] as [$key, $value])
                <div>
                    <p class="stat-label">{{ __("evm.{$key}") }} <span class="text-slate-400">· {{ __("evm.{$key}_short") }}</span></p>
                    <p class="stat-value tabular">{{ $money($value) }}</p>
                    <p class="mt-1 text-[11px] leading-relaxed text-slate-500">{{ __("evm.{$key}_help") }}</p>
                </div>
            @endforeach
        </div>

        {{-- Lo ganado contra lo que debería estar ganado, en una barra: es la
             misma comparación que el SPI, pero se entiende sin saber qué es un
             SPI. --}}
        @if ($evm['bac'] > 0)
            <div class="mt-4 border-t border-slate-100 pt-3">
                <div class="flex items-center gap-3">
                    <div class="meter h-2 flex-1"><div class="meter-fill" style="width: {{ min(100, $evm['progress']) }}%"></div></div>
                    <span class="w-32 shrink-0 text-right text-[11px] tabular text-slate-500">
                        {{ $evm['progress'] }} % / {{ $evm['planned_progress'] }} %
                    </span>
                </div>
            </div>
        @endif

        @if ($evm['has_baseline'])
            <p class="mt-3 text-[11px] text-slate-500">{{ __('evm.baseline_used', ['name' => $evm['baseline_name']]) }}</p>
        @endif
    </section>

    {{-- Índices y varianzas --}}
    <div class="mb-4 grid gap-4 lg:grid-cols-2">
        <section class="card hud-in hud-in-1 p-4">
            <h2 class="card-title mb-3">{{ __('common.status') }}</h2>

            <dl class="space-y-3">
                @foreach ([
                    ['cpi', $evm['cpi'], true],
                    ['spi', $evm['spi'], true],
                    ['cv', $evm['cv'], false],
                    ['sv', $evm['sv'], false],
                ] as [$key, $value, $isIndex])
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <dt class="text-sm font-medium text-slate-900">
                                {{ __("evm.{$key}") }}
                                <span class="text-xs font-normal text-slate-500">· {{ __("evm.{$key}_short") }}</span>
                            </dt>
                            <dd class="mt-0.5 text-[11px] leading-relaxed text-slate-600">{{ __("evm.{$key}_help") }}</dd>
                        </div>

                        {{-- Un índice bajo 1.00 y una varianza negativa son la
                             misma mala noticia contada de dos formas, y las dos
                             se marcan igual. --}}
                        <span class="shrink-0 text-sm font-semibold tabular
                                     {{ $value === null
                                        ? 'text-slate-400'
                                        : (($isIndex ? $value < 1 : $value < 0)
                                            ? 'text-[var(--color-badge-danger-fg)]'
                                            : 'text-slate-900') }}">
                            {{ $isIndex ? $index($value) : $money($value) }}
                        </span>
                    </div>
                @endforeach
            </dl>
        </section>

        <section class="card hud-in hud-in-2 p-4">
            <h2 class="card-title mb-3">{{ __('evm.forecast') }}</h2>

            <dl class="space-y-3">
                @foreach ([
                    ['eac', $evm['eac'], false],
                    ['etc', $evm['etc'], false],
                    ['vac', $evm['vac'], false],
                    ['tcpi', $evm['tcpi'], true],
                ] as [$key, $value, $isIndex])
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <dt class="text-sm font-medium text-slate-900">
                                {{ __("evm.{$key}") }}
                                <span class="text-xs font-normal text-slate-500">· {{ __("evm.{$key}_short") }}</span>
                            </dt>
                            <dd class="mt-0.5 text-[11px] leading-relaxed text-slate-600">{{ __("evm.{$key}_help") }}</dd>
                        </div>

                        <span class="shrink-0 text-sm font-semibold tabular
                                     {{ $value === null
                                        ? 'text-slate-400'
                                        : (($key === 'vac' && $value < 0) || ($key === 'tcpi' && $value > 1.1)
                                            ? 'text-[var(--color-badge-danger-fg)]'
                                            : 'text-slate-900') }}">
                            {{ $isIndex ? $index($value) : $money($value) }}
                        </span>
                    </div>
                @endforeach
            </dl>
        </section>
    </div>

    {{-- La lectura en palabras. Un tablero que dice «CPI 0.82» y nada más
         obliga a preguntarle a alguien, y ese alguien es el que ya sabía la
         respuesta. --}}
    <section class="card hud-in hud-in-3 mb-4 p-4">
        <h2 class="card-title mb-2">{{ __('evm.reading') }}</h2>

        <ul class="space-y-1 text-sm text-slate-700">
            @if ($evm['cpi'] !== null)
                <li>· {{ $evm['cpi'] > 1.02 ? __('evm.cost_ok') : ($evm['cpi'] < 0.98 ? __('evm.cost_over') : __('evm.cost_tight')) }}</li>
            @endif

            @if ($evm['spi'] !== null)
                <li>· {{ $evm['spi'] > 1.02 ? __('evm.schedule_ok') : ($evm['spi'] < 0.98 ? __('evm.schedule_late') : __('evm.schedule_tight')) }}</li>
            @endif

            @if ($evm['vac'] !== null)
                <li>
                    ·
                    {{ $evm['vac'] < 0
                        ? __('evm.forecast_over', ['amount' => number_format(abs($evm['vac']), 2)])
                        : __('evm.forecast_under', ['amount' => number_format($evm['vac'], 2)]) }}
                </li>
            @endif

            @if (($evm['tcpi'] ?? 0) > 1.1)
                <li>· {{ __('evm.tcpi_hard', ['factor' => number_format((float) $evm['tcpi'], 2)]) }}</li>
            @endif
        </ul>
    </section>

    {{-- Renglón por renglón: el total dice que algo va mal, y esta tabla dice
         dónde. --}}
    <section class="card hud-in hud-in-4">
        <div class="card-header">
            <h2 class="card-title">{{ __('evm.by_task') }}</h2>
            <span class="text-xs text-slate-500">{{ $evm['costed_tasks'] }}</span>
        </div>

        <div class="overflow-x-auto">
            <table class="data-table">
                <caption class="sr-only">{{ __('evm.by_task') }}</caption>
                <thead>
                    <tr>
                        <th scope="col">{{ __('evm.task') }}</th>
                        <th scope="col" class="w-20 text-right">{{ __('evm.progress') }}</th>
                        <th scope="col" class="w-28 text-right">{{ __('evm.budget') }}</th>
                        <th scope="col" class="w-28 text-right">{{ __('evm.pv_short') }}</th>
                        <th scope="col" class="w-28 text-right">{{ __('evm.ev_short') }}</th>
                        <th scope="col" class="w-28 text-right">{{ __('evm.ac_short') }}</th>
                        <th scope="col" class="w-28 text-right">{{ __('evm.cv_short') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($evm['lines'] as $line)
                        <tr>
                            <td>
                                <span class="font-medium text-slate-900">{{ $line['name'] }}</span>
                                @if ($line['wbs'] !== '')
                                    <span class="ml-1 font-mono text-[11px] text-slate-500">{{ $line['wbs'] }}</span>
                                @endif
                            </td>
                            <td class="text-right tabular text-slate-600">{{ $line['percent'] }} %</td>
                            <td class="text-right tabular">{{ $money($line['budget']) }}</td>
                            <td class="text-right tabular text-slate-600">{{ $money($line['pv']) }}</td>
                            <td class="text-right tabular text-slate-600">{{ $money($line['ev']) }}</td>
                            <td class="text-right tabular {{ $line['ac'] === null ? 'text-slate-400' : 'text-slate-600' }}">
                                {{ $money($line['ac']) }}
                            </td>
                            <td class="text-right tabular {{ $line['cv'] !== null && $line['cv'] < 0 ? 'font-medium text-[var(--color-badge-danger-fg)]' : 'text-slate-600' }}">
                                {{ $money($line['cv']) }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
