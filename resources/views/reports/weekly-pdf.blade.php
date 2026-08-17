<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ __('reports.weekly_title') }}</title>
    <style>
        /*
        | Una hoja, y que quepa en una hoja.
        |
        | Es lo que distingue este documento de la ficha: se manda al cierre de la
        | semana a
        | alguien que va a mirarlo treinta segundos en el teléfono antes de una
        | junta. Si pasa de una página deja de leerse completo, y entonces da
        | igual lo que diga la segunda.
        */
        @page { margin: 16mm 14mm 14mm 14mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 9pt; color: #0f172a; line-height: 1.35; }

        .pie {
            position: fixed; bottom: -10mm; left: 0; right: 0;
            font-size: 7pt; color: #64748b;
            border-top: 0.5pt solid #cbd5e1; padding-top: 3px;
        }
        .pie .der { float: right; }

        .encabezado { border-bottom: 2pt solid #0f172a; padding-bottom: 8px; margin-bottom: 10px; }
        .encabezado .rotulo { font-size: 7pt; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .encabezado h1 { font-size: 15pt; margin: 3px 0 1px; }
        .encabezado .semana { font-size: 8.5pt; color: #475569; }

        h2 {
            font-size: 8pt; text-transform: uppercase; letter-spacing: 0.8px;
            color: #334155; margin: 11px 0 4px; border-bottom: 0.5pt solid #e2e8f0; padding-bottom: 2px;
        }

        /* Los indicadores. Tabla y no `grid`: dompdf entiende CSS de hace
           quince años y una caja moderna sale apilada en una columna. */
        .kpis { width: 100%; border-collapse: separate; border-spacing: 4px 0; margin-bottom: 4px; }
        .kpis td { border: 0.5pt solid #e2e8f0; background: #f8fafc; padding: 5px 7px; width: 25%; vertical-align: top; }
        .kpis .rotulo { font-size: 6.5pt; letter-spacing: 0.6px; text-transform: uppercase; color: #64748b; }
        .kpis .cifra { font-size: 14pt; font-weight: bold; }
        .kpis .cifra .unidad { font-size: 8pt; font-weight: normal; color: #64748b; }
        .kpis .nota { font-size: 6.5pt; color: #64748b; }
        .kpis .mal .cifra { color: #b91c1c; }
        .kpis .bien .cifra { color: #047857; }

        .barra { height: 6px; background: #e2e8f0; margin-top: 4px; }
        .barra div { height: 6px; background: #1d4ed8; }

        table.lista { width: 100%; border-collapse: collapse; }
        table.lista td { padding: 2px 4px; border-bottom: 0.4pt solid #f1f5f9; vertical-align: top; font-size: 8.5pt; }
        table.lista .meta { color: #64748b; font-size: 7.5pt; white-space: nowrap; text-align: right; }
        table.lista .tarde { color: #b91c1c; font-weight: bold; }

        .vacio { color: #64748b; font-size: 8.5pt; font-style: italic; }
        .nota { font-size: 7pt; color: #64748b; margin-top: 2px; }

        .aviso { border-left: 2pt solid #94a3b8; padding-left: 6px; margin-bottom: 5px; font-size: 8.5pt; }
        .aviso.grave { border-left-color: #b91c1c; }
        .aviso.medio { border-left-color: #d97706; }
        .aviso .causa { color: #475569; font-size: 7.5pt; }

        /* Dos columnas para las listas cortas: lo que sigue y lo que corre caben
           lado a lado y ahorran media hoja. */
        .par { width: 100%; border-collapse: collapse; }
        .par > tbody > tr > td { width: 50%; vertical-align: top; padding: 0 6px 0 0; border: none; }
        .par > tbody > tr > td:last-child { padding: 0 0 0 6px; }
    </style>
</head>
<body>
    <div class="pie">
        {{ $project->code }} · {{ config('branding.name') }}
        <span class="der">{{ __('initiation.generated_on', ['date' => $generatedAt->format('d/m/Y H:i')]) }}</span>
    </div>

    <div class="encabezado">
        <div class="rotulo">{{ __('reports.weekly_title') }}</div>
        <h1>{{ $project->name }}</h1>
        <div class="semana">
            {{ $project->code }} ·
            {{ __('reports.week_of', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y')]) }}
        </div>
    </div>

    {{-- Cómo vamos, en cuatro números. Es lo único que mucha gente va a leer, y
         por eso va antes que cualquier lista. --}}
    <table class="kpis">
        <tr>
            <td>
                <div class="rotulo">{{ __('reports.kpi_progress') }}</div>
                <div class="cifra">{{ $kpis['progress'] }}<span class="unidad"> %</span></div>
                <div class="barra"><div style="width: {{ min(100, max(0, $kpis['progress'])) }}%"></div></div>
            </td>
            <td>
                <div class="rotulo">{{ __('reports.kpi_finish') }}</div>
                <div class="cifra" style="font-size: 10.5pt">{{ $kpis['finish']?->format('d/m/Y') ?? '—' }}</div>
                <div class="nota">
                    @if ($baseline_finish)
                        {{ __('reports.slip') }}: {{ $baseline_finish->format('d/m/Y') }}
                    @endif
                </div>
            </td>
            <td class="{{ $slip_days === null ? '' : ($slip_days > 0 ? 'mal' : 'bien') }}">
                <div class="rotulo">{{ __('reports.slip') }}</div>
                @if ($slip_days === null)
                    <div class="cifra" style="font-size: 10.5pt">—</div>
                    <div class="nota">{{ __('reports.no_baseline') }}</div>
                @elseif ($slip_days > 0)
                    <div class="cifra">{{ $slip_days }}<span class="unidad"> d</span></div>
                    <div class="nota">{{ __('reports.slip_days', ['days' => $slip_days]) }}</div>
                @elseif ($slip_days < 0)
                    <div class="cifra">{{ abs($slip_days) }}<span class="unidad"> d</span></div>
                    <div class="nota">{{ __('reports.ahead_days', ['days' => abs($slip_days)]) }}</div>
                @else
                    <div class="cifra" style="font-size: 10.5pt">0</div>
                    <div class="nota">{{ __('reports.on_plan') }}</div>
                @endif
            </td>
            <td class="{{ $late->isNotEmpty() ? 'mal' : '' }}">
                <div class="rotulo">{{ __('reports.late') }}</div>
                <div class="cifra">{{ $late->count() }}</div>
                <div class="nota">{{ __('reports.kpi_critical') }}: {{ $kpis['critical'] }}</div>
            </td>
        </tr>
    </table>

    @if ($digest !== [])
        <h2>{{ __('advisor.heading') }}</h2>
        @foreach (array_slice($digest, 0, 3) as $group)
            <div class="aviso {{ $group['severity'] === \App\Models\ProjectFinding::SEVERITY_CRITICAL ? 'grave' : ($group['severity'] === \App\Models\ProjectFinding::SEVERITY_WARNING ? 'medio' : '') }}">
                <div>{{ $group['headline'] }}</div>
                <div class="causa">{{ $group['why'] }}</div>
            </div>
        @endforeach
    @endif

    @if ($focusChart)
        {{-- El horizonte cercano, dibujado.
             Las listas dicen qué hay; el diagrama dice **cómo se encima**, que
             es lo que no se ve en una lista y lo que hace obvio por qué algo va
             a atorarse la semana que entra. --}}
        <h2>{{ __('reports.schedule_chart') }}</h2>
        <img src="{{ $focusChart }}" alt="" style="width: 700px">
    @endif

    <h2>{{ __('reports.late') }}</h2>
    @if ($late->isEmpty())
        <p class="vacio">{{ __('reports.nothing_late') }}</p>
    @else
        <table class="lista">
            @foreach ($late->take(10) as $task)
                <tr>
                    <td>
                        {{ $task->name }}
                        <span style="color:#64748b">· {{ $task->owner?->name ?? __('reports.unassigned') }}</span>
                    </td>
                    <td class="meta tarde">
                        {{ $task->early_finish?->format('d/m') }} ·
                        {{ (int) $task->early_finish?->diffInDays($now) }} {{ __('reports.days_late') }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <h2>{{ __('reports.closed_this_week') }}</h2>
    @if ($closed->isEmpty())
        <p class="vacio">{{ __('reports.nothing_closed') }}</p>
    @else
        <table class="lista">
            @foreach ($closed as $task)
                <tr>
                    <td>{{ $task->name }}</td>
                    <td class="meta">{{ $task->actual_finish?->format('d/m') }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    <p class="nota">{{ __('reports.closed_note') }}</p>

    <table class="par">
        <tr>
            <td>
                <h2>{{ __('reports.doing_now') }}</h2>
                @if ($doing->isEmpty())
                    <p class="vacio">{{ __('reports.nothing_doing') }}</p>
                @else
                    <table class="lista">
                        @foreach ($doing->take(8) as $task)
                            <tr>
                                <td>{{ $task->name }}</td>
                                <td class="meta">{{ (int) $task->percent_complete }} %</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
            <td>
                <h2>{{ __('reports.coming_next') }}</h2>
                @if ($next->isEmpty())
                    <p class="vacio">{{ __('reports.nothing_next') }}</p>
                @else
                    <table class="lista">
                        @foreach ($next->take(8) as $task)
                            <tr>
                                <td>{{ $task->name }}</td>
                                <td class="meta">{{ $task->early_start?->format('d/m') }}</td>
                            </tr>
                        @endforeach
                    </table>
                @endif
            </td>
        </tr>
    </table>
</body>
</html>
