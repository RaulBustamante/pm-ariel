<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ __('evm.title') }}</title>
    <style>
        /* Estilos propios, como el resto de los PDF: dompdf entiende un
           subconjunto de CSS y traer Tailwind aquí daría una hoja rota. */
        @page { margin: 16mm 14mm 18mm 14mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 8.5pt; color: #0f172a; line-height: 1.4; }

        .pie {
            position: fixed; bottom: -12mm; left: 0; right: 0;
            font-size: 7pt; color: #64748b;
            border-top: 0.5pt solid #cbd5e1; padding-top: 3px;
        }
        .pie .der { float: right; }

        .portada { border-bottom: 2pt solid #0f172a; padding-bottom: 8px; margin-bottom: 12px; }
        .portada .rotulo { font-size: 7pt; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .portada h1 { font-size: 15pt; margin: 4px 0 2px; }
        .portada .clave { font-family: DejaVu Sans Mono, monospace; font-size: 8.5pt; color: #475569; }

        h2 {
            font-size: 8.5pt; text-transform: uppercase; letter-spacing: 0.8px;
            color: #334155; margin: 14px 0 4px; border-bottom: 0.5pt solid #e2e8f0; padding-bottom: 2px;
        }

        /* El aviso va **antes** de los números. Un informe que enseña los
           índices y explica la letra chica al final ya convenció a quien lo
           leyó. */
        .aviso {
            border: 0.75pt solid #fde68a; background: #fffbeb; color: #92400e;
            padding: 5px 7px; margin-bottom: 10px; font-size: 7.5pt; line-height: 1.35;
        }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6px;
            color: #475569; border-bottom: 0.75pt solid #94a3b8; padding: 3px 4px;
        }
        td { padding: 3px 4px; border-bottom: 0.5pt solid #e2e8f0; vertical-align: top; }
        tr { page-break-inside: avoid; }

        .der { text-align: right; }
        .num { font-family: DejaVu Sans Mono, monospace; }
        .mal { color: #b91c1c; }
        .vacio { color: #94a3b8; }

        .cifras td { border: 0; padding: 3px 10px 3px 0; }
        .cifras .rotulo { font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6px; color: #64748b; }
        .cifras .valor { font-size: 12pt; font-family: DejaVu Sans Mono, monospace; }
        .cifras .nota { font-size: 6.5pt; color: #94a3b8; }
    </style>
</head>
<body>
    @php
        $money = fn (?float $value): string => $value === null ? '—' : number_format($value, 2);
        $index = fn (?float $value): string => $value === null ? '—' : number_format($value, 2);
    @endphp

    <div class="pie">
        {{ $project->code }} · {{ config('branding.name') }}
        <span class="der">{{ __('initiation.generated_on', ['date' => $generatedAt->format('d/m/Y H:i')]) }}</span>
    </div>

    <div class="portada">
        <div class="rotulo">{{ $project->name }}</div>
        <h1>{{ __('evm.title') }}</h1>
        <div class="clave">
            {{ $project->code }} ·
            {{ __('evm.as_of', ['date' => $evm['status_date']->format('d/m/Y')]) }}
            @if ($evm['has_baseline'])
                · {{ __('evm.baseline_used', ['name' => $evm['baseline_name']]) }}
            @endif
        </div>
    </div>

    @unless ($evm['has_baseline'])
        <div class="aviso">{{ __('evm.no_baseline') }}</div>
    @endunless

    @if ($evm['started_tasks'] === 0)
        <div class="aviso">{{ __('evm.nothing_started') }}</div>
    @elseif ($evm['missing_actuals'] > 0)
        <div class="aviso">
            <strong>{{ __('evm.no_actuals_title') }}.</strong>
            {{ __('evm.no_actuals', ['started' => $evm['started_tasks'], 'missing' => $evm['missing_actuals']]) }}
        </div>
    @endif

    <table class="cifras">
        <tr>
            @foreach (['pv', 'ev', 'ac', 'bac'] as $key)
                <td>
                    <div class="rotulo">{{ __("evm.{$key}") }} · {{ __("evm.{$key}_short") }}</div>
                    <div class="valor">{{ $money($evm[$key]) }}</div>
                </td>
            @endforeach
        </tr>
    </table>

    <h2>{{ __('common.status') }}</h2>

    <table>
        <thead>
            <tr>
                <th>{{ __('evm.reading') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.cpi_short') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.spi_short') }}</th>
                <th style="width: 26mm" class="der">{{ __('evm.cv_short') }}</th>
                <th style="width: 26mm" class="der">{{ __('evm.sv_short') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    @if ($evm['cpi'] !== null)
                        {{ $evm['cpi'] > 1.02 ? __('evm.cost_ok') : ($evm['cpi'] < 0.98 ? __('evm.cost_over') : __('evm.cost_tight')) }}
                    @endif
                    @if ($evm['spi'] !== null)
                        {{ $evm['spi'] > 1.02 ? __('evm.schedule_ok') : ($evm['spi'] < 0.98 ? __('evm.schedule_late') : __('evm.schedule_tight')) }}
                    @endif
                </td>
                <td class="der num {{ $evm['cpi'] !== null && $evm['cpi'] < 1 ? 'mal' : '' }}">{{ $index($evm['cpi']) }}</td>
                <td class="der num {{ $evm['spi'] < 1 ? 'mal' : '' }}">{{ $index($evm['spi']) }}</td>
                <td class="der num {{ $evm['cv'] !== null && $evm['cv'] < 0 ? 'mal' : '' }}">{{ $money($evm['cv']) }}</td>
                <td class="der num {{ $evm['sv'] < 0 ? 'mal' : '' }}">{{ $money($evm['sv']) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>{{ __('evm.forecast') }}</h2>

    <table>
        <thead>
            <tr>
                <th style="width: 34mm">{{ __('evm.eac_short') }}</th>
                <th style="width: 34mm">{{ __('evm.etc_short') }}</th>
                <th style="width: 34mm">{{ __('evm.vac_short') }}</th>
                <th style="width: 30mm">{{ __('evm.tcpi_short') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="num">{{ $money($evm['eac']) }}</td>
                <td class="num">{{ $money($evm['etc']) }}</td>
                <td class="num {{ $evm['vac'] !== null && $evm['vac'] < 0 ? 'mal' : '' }}">{{ $money($evm['vac']) }}</td>
                <td class="num {{ ($evm['tcpi'] ?? 0) > 1.1 ? 'mal' : '' }}">{{ $index($evm['tcpi']) }}</td>
                <td>
                    @if ($evm['vac'] !== null)
                        {{ $evm['vac'] < 0
                            ? __('evm.forecast_over', ['amount' => number_format(abs($evm['vac']), 2)])
                            : __('evm.forecast_under', ['amount' => number_format($evm['vac'], 2)]) }}
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <h2>{{ __('evm.by_task') }}</h2>

    <table>
        <thead>
            <tr>
                <th>{{ __('evm.task') }}</th>
                <th style="width: 14mm" class="der">{{ __('evm.progress') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.budget') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.pv_short') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.ev_short') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.ac_short') }}</th>
                <th style="width: 22mm" class="der">{{ __('evm.cv_short') }}</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($evm['lines'] as $line)
                <tr>
                    <td>
                        {{ $line['name'] }}
                        @if ($line['wbs'] !== '')
                            <span class="vacio num">{{ $line['wbs'] }}</span>
                        @endif
                    </td>
                    <td class="der num">{{ $line['percent'] }} %</td>
                    <td class="der num">{{ $money($line['budget']) }}</td>
                    <td class="der num">{{ $money($line['pv']) }}</td>
                    <td class="der num">{{ $money($line['ev']) }}</td>
                    <td class="der num {{ $line['ac'] === null ? 'vacio' : '' }}">{{ $money($line['ac']) }}</td>
                    <td class="der num {{ $line['cv'] !== null && $line['cv'] < 0 ? 'mal' : '' }}">{{ $money($line['cv']) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
