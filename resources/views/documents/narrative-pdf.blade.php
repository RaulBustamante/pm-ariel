<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ $title }}</title>
    <style>
        /* Estilos propios, como el resto de los PDF: dompdf entiende un
           subconjunto de CSS y traer Tailwind aquí daría una hoja rota. */
        @page { margin: 22mm 18mm 20mm 18mm; }

        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #0f172a; line-height: 1.5; }

        .pie {
            position: fixed; bottom: -12mm; left: 0; right: 0;
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

        /* El texto conserva los saltos de línea que capturó el usuario: si se
           perdieran, una lista escrita renglón por renglón saldría como un
           párrafo corrido y dejaría de leerse como lista. */
        .texto { white-space: pre-line; }

        .vacio { color: #94a3b8; font-style: italic; }

        /* Una sección necesaria y vacía se marca. Un documento que sale limpio
           escondiendo sus huecos hace creer que está terminado. */
        .falta { color: #b45309; font-style: italic; }

        section { page-break-inside: avoid; }
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

    @foreach ($sections as $section)
        <section>
            <h2>{{ $section['title'] }}</h2>

            @if ($section['value'] !== null)
                <div class="texto">{{ $section['value'] }}</div>
            @elseif ($section['required'])
                <div class="falta">{{ __('sections.empty_section') }}</div>
            @else
                <div class="vacio">—</div>
            @endif
        </section>
    @endforeach
</body>
</html>
