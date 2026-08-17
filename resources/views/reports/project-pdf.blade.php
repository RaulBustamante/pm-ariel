<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <title>{{ $project->code }} · {{ $project->name }}</title>
    <style>
        /*
        | Estilos propios y no los de la aplicación: dompdf entiende un
        | subconjunto de CSS, y traer Tailwind aquí daría una hoja rota. Un PDF
        | que sale mal impreso es peor que no tenerlo — se manda a dirección.
        */
        @page { margin: 22mm 16mm 20mm 16mm; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 9.5pt;
            color: #0f172a;
            line-height: 1.45;
        }

        /* Numeración en cada hoja. Un reporte de doce páginas sin números es
           imposible de citar en una junta. */
        .pie {
            position: fixed;
            bottom: -14mm; left: 0; right: 0;
            font-size: 7.5pt;
            color: #64748b;
            border-top: 0.5pt solid #cbd5e1;
            padding-top: 3px;
        }
        .pie .der { float: right; }

        /* dompdf resuelve estos contadores al terminar de maquetar, que es el
           unico momento en que sabe cuantas paginas hay. */
        .pagina:before { content: counter(page); }
        .paginas:before { content: counter(pages); }

        .portada { border-bottom: 2pt solid #0f172a; padding-bottom: 10px; margin-bottom: 16px; }
        .portada .rotulo { font-size: 7.5pt; letter-spacing: 1.5px; text-transform: uppercase; color: #64748b; }
        .portada h1 { font-size: 18pt; margin: 4px 0 2px; }
        .portada .clave { font-family: DejaVu Sans Mono, monospace; font-size: 9pt; color: #475569; }

        h2 {
            font-size: 9pt; text-transform: uppercase; letter-spacing: 0.8px;
            color: #334155; margin: 16px 0 5px; border-bottom: 0.5pt solid #e2e8f0; padding-bottom: 2px;
        }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 7.5pt; text-transform: uppercase; color: #475569;
             border-bottom: 0.75pt solid #94a3b8; padding: 3px 4px; }
        td { padding: 3px 4px; border-bottom: 0.4pt solid #e2e8f0; vertical-align: top; }

        /* Un renglón no se parte entre dos hojas. */
        tr { page-break-inside: avoid; }

        .ficha td { border: none; padding: 1px 0; }
        .ficha .etiqueta { color: #64748b; width: 32%; }

        .critica { color: #b91c1c; font-weight: bold; }
        .num { text-align: right; }
        .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8pt; color: #64748b; }
        .texto { white-space: pre-line; }
        .aviso { border-left: 2pt solid #94a3b8; padding-left: 6px; margin-bottom: 6px; }
        .aviso .causa { color: #475569; font-size: 8.5pt; }
    </style>
</head>
<body>
    <div class="pie">
        {{ $project->code }} · {{ config('branding.name') }}
        <span class="der">
            {{ __('reports.page') }} <span class="pagina"></span>/<span class="paginas"></span> ·
            {{ __('initiation.generated_on', ['date' => $generatedAt->format('d/m/Y H:i')]) }}
        </span>
    </div>

    <div class="portada">
        <div class="rotulo">{{ __('reports.project_sheet') }}</div>
        <h1>{{ $project->name }}</h1>
        <div class="clave">{{ $project->code }}</div>
    </div>

    <table class="ficha">
        @foreach ([
            'common.org_unit' => $project->orgUnit?->name,
            'initiation.prepared_by' => $project->owner?->name,
            'initiation.field_sponsor' => $charter?->sponsor?->name,
            'tasks.project_start' => $project->planned_start?->format('d/m/Y'),
            'tasks.project_finish' => $lastRun?->project_finish?->format('d/m/Y'),
            'common.status' => __("projects.status_{$project->status}"),
        ] as $label => $value)
            <tr>
                <td class="etiqueta">{{ __($label) }}</td>
                <td>{{ $value ?: '—' }}</td>
            </tr>
        @endforeach
    </table>

    @if ($charter)
        @foreach (['problem_statement', 'expected_benefit', 'objectives', 'deliverables', 'success_criteria', 'out_of_scope'] as $field)
            @if (filled($charter->{$field}))
                <h2>{{ __("initiation.field_{$field}") }}</h2>
                <div class="texto">{{ $charter->{$field} }}</div>
            @endif
        @endforeach
    @endif

    <h2>{{ __('tasks.title') }}</h2>

    @if ($tasks->isEmpty())
        <p>{{ __('tasks.empty') }}</p>
    @else
        <table>
            <thead>
                <tr>
                    <th style="width:8%">WBS</th>
                    <th>{{ __('tasks.name') }}</th>
                    <th style="width:10%">{{ __('tasks.duration') }}</th>
                    <th style="width:13%">{{ __('tasks.start') }}</th>
                    <th style="width:13%">{{ __('tasks.finish') }}</th>
                    <th style="width:8%" class="num">%</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($tasks as $task)
                    <tr>
                        <td class="mono">{{ $task->wbs_code }}</td>
                        <td style="padding-left: {{ 4 + ($task->outline_depth ?? 0) * 10 }}px">
                            {{ $task->name }}
                            @if ($task->is_critical && ! $task->is_summary)
                                <span class="critica">· {{ __('tasks.critical') }}</span>
                            @endif
                        </td>
                        <td>{{ $task->is_summary ? '—' : $durations->toHuman((int) $task->duration_minutes) }}</td>
                        <td>{{ $task->early_start?->format('d/m/Y') ?? '—' }}</td>
                        <td>{{ $task->early_finish?->format('d/m/Y') ?? '—' }}</td>
                        <td class="num">{{ (int) $task->percent_complete }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if ($findings->isNotEmpty())
        {{-- Los avisos se imprimen con el reporte. Un documento que se presenta
             como sano cuando el sistema detectó problemas es peor que no tener
             el documento. --}}
        <h2>{{ __('advisor.heading') }}</h2>

        @foreach ($findings as $finding)
            <div class="aviso">
                <div>{{ $finding->message }}</div>
                <div class="causa">{{ $finding->why }}</div>
            </div>
        @endforeach
    @endif

</body>
</html>
