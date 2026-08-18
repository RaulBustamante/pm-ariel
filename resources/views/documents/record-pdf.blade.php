<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ $record->reference() }}</title>
    <style>
        /* Estilos propios, como el resto de los PDF: dompdf entiende un
           subconjunto de CSS y traer Tailwind aquí daría una hoja rota. */
        @page { margin: 24mm 20mm 22mm 20mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.55; }

        .pie {
            position: fixed; bottom: -14mm; left: 0; right: 0;
            font-size: 7.5pt; color: #64748b;
            border-top: 0.5pt solid #cbd5e1; padding-top: 3px;
        }
        .pie .der { float: right; }

        .portada { border-bottom: 2pt solid #0f172a; padding-bottom: 10px; margin-bottom: 18px; }
        .portada .rotulo { font-size: 7.5pt; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .portada h1 { font-size: 17pt; margin: 4px 0 2px; }
        .portada .clave { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; color: #475569; }

        h2 {
            font-size: 9.5pt; text-transform: uppercase; letter-spacing: 0.8px;
            color: #334155; margin: 16px 0 4px; border-bottom: 0.5pt solid #e2e8f0; padding-bottom: 2px;
        }

        .texto { white-space: pre-line; }
        .vacio { color: #94a3b8; font-style: italic; }

        /* La respuesta es lo primero que alguien busca al abrir un acta. */
        .respuesta {
            display: inline-block; padding: 4px 10px; font-size: 11pt; font-weight: bold;
            border: 1pt solid #0f172a;
        }
        .respuesta.reservas { border-color: #b45309; color: #b45309; }
        .respuesta.rechazo { border-color: #b91c1c; color: #b91c1c; }

        .reservas {
            border-left: 2pt solid #fde68a; background: #fffbeb;
            padding: 6px 9px; margin-top: 6px;
        }

        table.datos { width: 100%; border-collapse: collapse; margin-top: 4px; }
        table.datos td { padding: 3px 0; vertical-align: top; }
        table.datos td.et { width: 42mm; color: #64748b; }

        /* El bloque de la firma va al final y **no imita una firma manuscrita**:
           es un recuadro que dice qué se registró y qué no. Un sello que promete
           más de lo que vale es peor que no tener sello. */
        .firma { margin-top: 22px; border: 0.75pt solid #cbd5e1; padding: 10px 12px; }
        .firma .titulo { font-size: 8pt; text-transform: uppercase; letter-spacing: 1px; color: #64748b; }
        .firma .nombre { font-size: 12pt; font-weight: bold; margin-top: 4px; }
        .firma .nota { font-size: 7.5pt; color: #64748b; margin-top: 8px; border-top: 0.5pt solid #e2e8f0; padding-top: 6px; }
        .firma .huella { font-family: DejaVu Sans Mono, monospace; font-size: 7pt; color: #94a3b8; }

        .borrador {
            border: 1pt solid #b45309; background: #fffbeb; color: #b45309;
            padding: 6px 9px; margin-bottom: 14px; font-weight: bold; font-size: 9pt;
        }
    </style>
</head>
<body>
    @php
        $class = match ($record->decision) {
            'accepted_with_reservations' => 'respuesta reservas',
            'rejected' => 'respuesta rechazo',
            default => 'respuesta',
        };
    @endphp

    <div class="pie">
        {{ $project->code }} · {{ config('branding.name') }}
        <span class="der">{{ __('initiation.generated_on', ['date' => $generatedAt->format('d/m/Y H:i')]) }}</span>
    </div>

    <div class="portada">
        <div class="rotulo">{{ $project->name }}</div>
        <h1>{{ $title }}</h1>
        <div class="clave">{{ $project->code }} · {{ $record->reference() }}</div>
    </div>

    {{-- Un borrador impreso que no se distinga de una firmada acabaría en una
         junta pasando por definitivo. --}}
    @unless ($record->isSigned())
        <div class="borrador">{{ __('records.draft') }} — {{ __('records.draft_warning') }}</div>
    @endunless

    <h2>{{ __('records.subject') }}</h2>
    <p><strong>{{ $record->subject }}</strong></p>

    @if ($record->task)
        <p class="vacio">{{ __('records.deliverable') }}: {{ $record->task->name }}</p>
    @endif

    @if ($record->detail)
        <div class="texto">{{ $record->detail }}</div>
    @endif

    <h2>{{ __('records.decision') }}</h2>
    <p><span class="{{ $class }}">{{ __("records.decision_{$record->decision}") }}</span></p>

    @if ($record->reservations)
        <div class="reservas">
            <strong>{{ __('records.reservations') }}</strong>
            <div class="texto">{{ $record->reservations }}</div>
        </div>
    @endif

    <h2>{{ __('records.accepted_by') }}</h2>

    <table class="datos">
        <tr>
            <td class="et">{{ __('records.accepted_by_name') }}</td>
            <td><strong>{{ $record->accepted_by_name }}</strong></td>
        </tr>
        <tr>
            <td class="et">{{ __('records.accepted_by_role') }}</td>
            <td>{{ $record->accepted_by_role ?? '—' }}</td>
        </tr>
        <tr>
            <td class="et">{{ __('records.accepted_by_org') }}</td>
            <td>{{ $record->accepted_by_org ?? '—' }}</td>
        </tr>
        <tr>
            <td class="et">{{ __('records.accepted_on') }}</td>
            <td>{{ $record->accepted_on?->format('d/m/Y') }}</td>
        </tr>
    </table>

    <div class="firma">
        <div class="titulo">{{ __('records.accepted_by') }}</div>
        <div class="nombre">{{ $record->accepted_by_name }}</div>

        @if ($record->isSigned())
            <div>
                {{ __('records.signed_on', ['date' => $record->signed_at?->format('d/m/Y H:i')]) }} ·
                {{ __('records.recorded_by', ['who' => $record->signedBy?->name ?? '—']) }}
            </div>
            <div class="huella">{{ __('records.checksum') }}: {{ $record->checksum }}</div>
        @else
            <div class="vacio">{{ __('records.not_signed_yet') }}</div>
        @endif

        <div class="nota">{{ __('records.sign_disclaimer') }}</div>
    </div>
</body>
</html>
