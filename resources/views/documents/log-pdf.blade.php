<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ $title }}</title>
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

        /* Las cifras de arriba. Un registro se lee para saber cuánto falta, y
           ese número tiene que estar antes de la primera fila, no después de la
           última. */
        .cifras { margin-bottom: 10px; font-size: 8pt; color: #334155; }
        .cifras strong { font-size: 11pt; color: #0f172a; }
        .cifras span { padding-right: 18px; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.6px;
            color: #475569; border-bottom: 0.75pt solid #94a3b8; padding: 3px 4px;
        }
        td { padding: 4px; border-bottom: 0.5pt solid #e2e8f0; vertical-align: top; }
        tr { page-break-inside: avoid; }

        .num { font-family: DejaVu Sans Mono, monospace; font-size: 7.5pt; color: #475569; white-space: nowrap; }
        .fecha { color: #64748b; font-size: 7.5pt; white-space: nowrap; }

        /* El texto conserva los saltos de línea que capturó el usuario: si se
           perdieran, una lista escrita renglón por renglón saldría como un
           párrafo corrido. */
        .detalle { color: #334155; white-space: pre-line; }
        .desenlace { color: #64748b; border-left: 1.5pt solid #cbd5e1; padding-left: 5px; margin-top: 3px; white-space: pre-line; }

        .vencido { color: #b91c1c; }
        .vacio { color: #94a3b8; font-style: italic; }
    </style>
</head>
<body>
    <div class="pie">
        {{ $project->code }} · {{ config('branding.name') }}
        <span class="der">{{ __('initiation.generated_on', ['date' => $generatedAt->format('d/m/Y H:i')]) }}</span>
    </div>

    <div class="portada">
        <div class="rotulo">{{ $project->name }}</div>
        <h1>{{ $title }}</h1>
        <div class="clave">{{ $project->code }}</div>
    </div>

    <div class="cifras">
        <span>{{ __('logs.total') }} <strong>{{ $summary['total'] }}</strong></span>
        <span>{{ __('logs.open') }} <strong>{{ $summary['open'] }}</strong></span>
        @if (in_array('due', $fields, true))
            <span>{{ __('logs.overdue') }} <strong>{{ $summary['overdue'] }}</strong></span>
        @endif
    </div>

    @if ($entries->isEmpty())
        <p class="vacio">{{ __('logs.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width: 16mm">{{ __('logs.reference') }}</th>
                    <th style="width: 18mm">{{ __('logs.occurred_on') }}</th>
                    <th>{{ __('logs.entry_title') }}</th>
                    @if (in_array('owner', $fields, true))
                        <th style="width: 32mm">{{ __('logs.owner') }}</th>
                    @endif
                    @if (in_array('due', $fields, true))
                        <th style="width: 20mm">{{ __('logs.due_on') }}</th>
                    @endif
                    @if (in_array('priority', $fields, true))
                        <th style="width: 18mm">{{ __('logs.priority') }}</th>
                    @endif
                    <th style="width: 26mm">{{ __('common.status') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($entries as $row)
                    @php $isClosed = in_array($row->status, $closed, true); @endphp
                    <tr>
                        <td class="num">{{ $row->reference() }}</td>
                        <td class="fecha">{{ $row->occurred_on?->format('d/m/Y') }}</td>

                        <td>
                            <strong>{{ $row->title }}</strong>
                            @if ($row->detail)
                                <div class="detalle">{{ $row->detail }}</div>
                            @endif
                            @if ($row->outcome)
                                <div class="desenlace">{{ __('logs.outcome') }}: {{ $row->outcome }}</div>
                            @endif
                        </td>

                        @if (in_array('owner', $fields, true))
                            <td>{{ $row->owner?->name ?? __('logs.owner_none') }}</td>
                        @endif

                        @if (in_array('due', $fields, true))
                            <td class="fecha {{ $row->due_on && ! $isClosed && $row->due_on->isPast() ? 'vencido' : '' }}">
                                {{ $row->due_on?->format('d/m/Y') ?? '—' }}
                            </td>
                        @endif

                        @if (in_array('priority', $fields, true))
                            <td>{{ $row->priority ? __("logs.priority_{$row->priority}") : '—' }}</td>
                        @endif

                        <td>{{ __("logs.status_{$row->status}") }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
