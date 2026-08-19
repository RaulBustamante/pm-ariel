<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ $title }}</title>
    <style>
        /* Estilos propios, como el resto de los PDF: dompdf entiende un
           subconjunto de CSS y traer Tailwind aquí daría una hoja rota. */
        @page { margin: 16mm 14mm 18mm 14mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 8pt; color: #0f172a; line-height: 1.4; }

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
        .portada .ayuda { font-size: 7.5pt; color: #475569; margin-top: 5px; line-height: 1.35; }

        table { width: 100%; border-collapse: collapse; }
        th {
            text-align: left; font-size: 6.5pt; text-transform: uppercase; letter-spacing: 0.5px;
            color: #475569; border-bottom: 0.75pt solid #94a3b8; padding: 3px 4px;
        }
        td { padding: 3px 4px; border-bottom: 0.5pt solid #e2e8f0; vertical-align: top; }
        tr { page-break-inside: avoid; }

        .der { text-align: right; font-family: DejaVu Sans Mono, monospace; }
        .paquete { background: #f8fafc; font-weight: bold; }
        .mal { color: #b91c1c; font-weight: bold; }
        .vacio { color: #94a3b8; }

        /* El texto conserva los saltos de línea que capturó el usuario. */
        .texto { white-space: pre-line; }
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
        <div class="ayuda">{{ __("derived.help_{$code}") }}</div>
    </div>

    @if ($rows === [])
        {{-- Un documento vacío dice por qué lo está. Salir en blanco haría creer
             que el proyecto no tiene riesgos, o recursos, o lecciones. --}}
        <p class="vacio">{{ __('derived.empty') }} {{ __("derived.empty_{$code}") }}</p>
    @else
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th class="{{ $derived->isNumeric($code, $column) ? 'der' : '' }}">
                            {{ __("derived.col_{$column}") }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="{{ ($row['is_summary'] ?? false) ? 'paquete' : '' }}">
                        @foreach ($columns as $column)
                            @php $value = $row[$column] ?? null; @endphp
                            <td class="{{ $derived->isNumeric($code, $column) ? 'der' : '' }}
                                       {{ ($column === 'variance' && ($row['is_over'] ?? false))
                                          || ($column === 'level' && ($row['is_high'] ?? false)) ? 'mal' : '' }}">
                                @if ($value === null || $value === '')
                                    <span class="vacio">—</span>
                                @else
                                    <span class="texto">{{ $value }}</span>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
