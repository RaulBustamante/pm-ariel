@extends('layouts.app')

@section('title', __('analysis.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'analysis'])

    @php
        $weeks = $workload['weeks'];
        $rows = $workload['rows'];

        // Alto de la barra de una semana. El pico de todo el proyecto marca el
        // techo, así que las barras se comparan entre sí y no contra un máximo
        // inventado que aplastaría todo contra el suelo.
        $peak = collect($rows)->max('peak') ?: 1;
    @endphp

    {{-- El valor ganado vive en su propia pantalla —tiene fecha de corte propia
         y una tabla por tarea—, pero se llega desde aquí: quien viene a ver el
         costo contra la línea base está a una pregunta de querer el CPI. --}}
    <div class="mb-4 flex justify-end">
        <a href="{{ route('projects.earned-value', $project) }}" class="btn btn-secondary btn-sm">
            {{ __('evm.title') }}
        </a>
    </div>

    {{-- ------------------------------------------------------------------
         6.7 · Carga de recursos
         ------------------------------------------------------------------ --}}
    <section class="card hud-in mb-4">
        <div class="card-header">
            <h2 class="card-title">{{ __('analysis.workload') }}</h2>
            <span class="text-xs text-slate-500">{{ count($rows) }} · {{ count($weeks) }} {{ __('analysis.weeks') }}</span>
        </div>

        <div class="p-4">
            <p class="mb-3 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __('analysis.workload_help') }}</p>

            @if ($rows === [])
                <p class="py-6 text-center text-sm text-slate-500">{{ __('analysis.no_workload') }}</p>
            @else
                @foreach ($rows as $row)
                    @php
                        // La capacidad semanal en horas. Se toma la jornada
                        // estándar de 9 h por 5 días: es el mismo supuesto con el
                        // que el asesor detecta sobreasignación, y usar otro aquí
                        // daría dos respuestas distintas a la misma pregunta.
                        $weekCapacity = 45 * ($row['capacity'] / 100);
                        $over = $row['peak'] > $weekCapacity;
                    @endphp

                    <div class="mb-4 last:mb-0">
                        <div class="mb-1 flex flex-wrap items-baseline justify-between gap-2">
                            <span class="text-sm font-medium text-slate-900">{{ $row['name'] }}</span>
                            <span class="text-xs text-slate-500">
                                {{ __('analysis.peak') }}
                                <strong class="tabular {{ $over ? 'text-[var(--color-badge-danger-fg)]' : 'text-slate-800' }}">
                                    {{ $row['peak'] }} h
                                </strong>
                                · {{ __('analysis.capacity') }} {{ round($weekCapacity) }} h
                                @if ($over)
                                    <span class="badge badge-danger ml-1">{{ __('analysis.over') }}</span>
                                @endif
                            </span>
                        </div>

                        {{-- Histograma. Una barra por semana, con la línea de
                             capacidad encima: sin esa línea, «40 horas» no dice
                             si alguien va cómodo o ahogado. --}}
                        <div class="relative flex h-16 items-end gap-px overflow-x-auto rounded bg-slate-50 p-1">
                            @php $capacityY = $weekCapacity > 0 ? min(100, $weekCapacity / $peak * 100) : 0; @endphp

                            <div class="pointer-events-none absolute inset-x-1 border-t border-dashed border-[var(--color-badge-warn-fg)]"
                                 style="bottom: calc({{ $capacityY }}% - 2px)"
                                 aria-hidden="true"></div>

                            @foreach ($weeks as $index => $week)
                                @php
                                    $hours = $row['hours'][$index] ?? 0;
                                    $height = $peak > 0 ? max(2, $hours / $peak * 100) : 2;
                                    $weekOver = $hours > $weekCapacity;
                                @endphp
                                <div class="min-w-[6px] flex-1 rounded-t {{ $weekOver ? 'bar-danger' : 'bar-brand' }}"
                                     style="height: {{ $height }}%"
                                     title="{{ \Illuminate\Support\Carbon::parse($week)->format('d/m') }} · {{ $hours }} h"></div>
                            @endforeach
                        </div>

                        {{-- La misma información como texto: un histograma es
                             una imagen vacía para quien usa lector de pantalla. --}}
                        <p class="sr-only">
                            {{ __('analysis.workload_reader', [
                                'name' => $row['name'],
                                'peak' => $row['peak'],
                                'capacity' => round($weekCapacity),
                            ]) }}
                        </p>
                    </div>
                @endforeach
            @endif
        </div>
    </section>

    <div class="grid gap-4 lg:grid-cols-2">
        {{-- ------------------------------------------------------------------
             6.6 · Distribución de horas
             ------------------------------------------------------------------ --}}
        <section class="card hud-in hud-in-1">
            <div class="card-header">
                <h2 class="card-title">{{ __('analysis.hours') }}</h2>
                <span class="text-xs tabular text-slate-500">{{ number_format($costs['hours'], 1) }} h</span>
            </div>

            <div class="overflow-x-auto">
                @if ($rows === [])
                    <p class="p-5 text-center text-sm text-slate-500">{{ __('analysis.no_workload') }}</p>
                @else
                    <table class="data-table">
                        <caption class="sr-only">{{ __('analysis.hours') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('resources.name') }}</th>
                                <th scope="col" class="w-24 text-right">{{ __('resources.hours') }}</th>
                                <th scope="col" class="w-40">{{ __('resources.share') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                @php
                                    $total = array_sum($row['hours']);
                                    $share = $costs['hours'] > 0 ? $total / $costs['hours'] * 100 : 0;
                                @endphp
                                <tr>
                                    <td class="text-slate-800">{{ $row['name'] }}</td>
                                    <td class="text-right tabular font-medium text-slate-900">{{ number_format($total, 1) }}</td>
                                    <td>
                                        <div class="flex items-center gap-2">
                                            <div class="meter h-1.5 flex-1"><div class="meter-fill" style="width: {{ round($share) }}%"></div></div>
                                            <span class="w-9 shrink-0 text-right text-[11px] tabular text-slate-500">{{ round($share) }} %</span>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>

        {{-- ------------------------------------------------------------------
             6.8 · Costo por fase
             ------------------------------------------------------------------ --}}
        <section class="card hud-in hud-in-2">
            <div class="card-header">
                <h2 class="card-title">{{ __('analysis.by_phase') }}</h2>
                <span class="text-xs tabular text-slate-500">{{ number_format($costs['total'], 0) }}</span>
            </div>

            <div class="overflow-x-auto">
                @if ($costs['by_phase'] === [])
                    <p class="p-5 text-center text-sm text-slate-500">{{ __('analysis.no_costs') }}</p>
                @else
                    <table class="data-table">
                        <caption class="sr-only">{{ __('analysis.by_phase') }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('analysis.phase') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('resources.cost') }}</th>
                                <th scope="col" class="w-28 text-right">{{ __('resources.cost_earned') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($costs['by_phase'] as $phase)
                                <tr>
                                    <td class="text-slate-800">
                                        {{ $phase['name'] }}
                                        <div class="meter mt-1 h-1"><div class="meter-fill" style="width: {{ $phase['share'] }}%"></div></div>
                                    </td>
                                    <td class="text-right tabular font-medium text-slate-900">{{ number_format($phase['cost'], 0) }}</td>
                                    <td class="text-right tabular text-slate-600">{{ number_format($phase['earned'], 0) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </section>
    </div>

    {{-- ------------------------------------------------------------------
         6.8 · Contra la línea base
         ------------------------------------------------------------------ --}}
    <section class="card card-hud hud-in hud-in-3 mt-4 p-4">
        <h2 class="card-title mb-3">{{ __('analysis.vs_baseline') }}</h2>

        @if ($baseline === null)
            {{-- Sin línea base no se inventa una comparación. Decirlo es más
                 útil que presentar una desviación contra nada. --}}
            <p class="text-sm text-slate-600">{{ __('analysis.no_baseline') }}</p>
        @else
            @php
                $planned = (float) $baseline->total_cost;
                $now = $costs['total'];
                $variance = $now - $planned;
                $percent = $planned > 0 ? $variance / $planned * 100 : 0;
            @endphp

            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <p class="stat-label">{{ __('analysis.baseline_cost') }}</p>
                    <p class="stat-value">{{ number_format($planned, 0) }}</p>
                    <p class="mt-1 text-[11px] text-slate-500">{{ $baseline->captured_at?->format('d/m/Y') }}</p>
                </div>

                <div>
                    <p class="stat-label">{{ __('analysis.current_cost') }}</p>
                    <p class="stat-value">{{ number_format($now, 0) }}</p>
                </div>

                <div>
                    <p class="stat-label">{{ __('analysis.variance') }}</p>
                    <p class="stat-value {{ $variance > 0 ? 'text-[var(--color-badge-danger-fg)]' : 'text-[var(--color-badge-ok-fg)]' }}">
                        {{ $variance > 0 ? '+' : '' }}{{ number_format($variance, 0) }}
                    </p>
                    <p class="mt-1 text-[11px] {{ $variance > 0 ? 'text-[var(--color-badge-danger-fg)]' : 'text-slate-500' }}">
                        {{ $variance > 0 ? '+' : '' }}{{ round($percent, 1) }} %
                    </p>
                </div>
            </div>

            {{-- Una línea base capturada antes de que existieran los costos de
                 recurso solo congeló el costo fijo. Decirlo evita que alguien
                 lea una desviación enorme que en realidad es un cambio de
                 método, no un sobrecosto. --}}
            @if ($planned > 0 && $costs['total'] > 0 && abs($percent) > 100)
                <p class="badge-warn mt-3 block rounded-md border px-3 py-2 text-xs">
                    {{ __('analysis.baseline_before_costs') }}
                </p>
            @endif
        @endif
    </section>

    {{-- El aviso de tarifas faltantes se repite aquí: quien llega directo a este
         reporte no pasó por la pantalla de recursos, y un total incompleto sin
         advertencia es peor que no tener el total. --}}
    @if ($costs['missing_rates'] !== [])
        <p class="badge-warn mt-4 block rounded-md border px-3 py-2 text-xs">
            {{ __('resources.missing_rates', ['names' => implode(', ', array_slice($costs['missing_rates'], 0, 6))]) }}
        </p>
    @endif
@endsection
