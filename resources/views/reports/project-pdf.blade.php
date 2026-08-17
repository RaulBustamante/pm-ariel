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
        .aviso { border-left: 2pt solid #94a3b8; padding-left: 6px; margin-bottom: 7px; }
        .aviso.grave { border-left-color: #b91c1c; }
        .aviso.medio { border-left-color: #d97706; }
        .aviso .titulo { font-weight: bold; }
        .aviso .causa { color: #475569; font-size: 8.5pt; }
        .aviso .quienes { color: #475569; font-size: 8pt; margin-top: 2px; }

        /*
        | Los indicadores de la primera hoja.
        |
        | Se arman con una tabla y no con `flex` o `grid`: dompdf entiende un
        | subconjunto de CSS de hace quince años, y una caja moderna sale apilada
        | en una columna. Feo en pantalla, inservible impreso.
        */
        .kpis { margin: 4px 0 6px; border-collapse: separate; border-spacing: 4px 0; }
        .kpis td {
            border: 0.5pt solid #e2e8f0; background: #f8fafc;
            padding: 6px 8px; width: 20%; vertical-align: top;
        }
        .kpis .rotulo { font-size: 6.5pt; letter-spacing: 0.8px; text-transform: uppercase; color: #64748b; }
        .kpis .cifra { font-size: 15pt; font-weight: bold; color: #0f172a; }
        .kpis .cifra .unidad { font-size: 9pt; font-weight: normal; color: #64748b; }
        .kpis .alerta .cifra { color: #b91c1c; }

        /* La barra de avance. Dos divs anidados: lo que dompdf sí dibuja bien. */
        .barra { height: 7px; background: #e2e8f0; margin-top: 5px; }
        .barra div { height: 7px; background: #1d4ed8; }

        .hoja-nueva { page-break-before: always; }
        .gantt-hoja { page-break-inside: avoid; margin-bottom: 10px; }
        .gantt-hoja .nombres { font-size: 7pt; }
        .gantt-hoja .nombres td { border: none; padding: 0 4px 0 0; height: 26px; vertical-align: middle; }
    </style>
</head>
<body>
    <div class="pie">
        {{ $project->code }} · {{ config('branding.name') }}
        {{-- La numeración no va aquí: `counter(pages)` de CSS sale en cero en
             dompdf y el pie decía «1/0» en cada hoja. La escribe el lienzo al
             terminar de maquetar, que es el único momento en que se sabe el
             total. Ver ReportController::stampPageNumbers(). --}}
        <span class="der">{{ __('initiation.generated_on', ['date' => $generatedAt->format('d/m/Y H:i')]) }}</span>
    </div>

    <div class="portada">
        <div class="rotulo">{{ __('reports.project_sheet') }}</div>
        <h1>{{ $project->name }}</h1>
        <div class="clave">{{ $project->code }}</div>
    </div>

    {{-- Los números primero.
         Quien recibe esto no lee de arriba abajo: mira, decide si le preocupa y
         entonces lee. Si lo primero que encuentra es la lista de interesados,
         ya perdió el interés antes de llegar al dato. --}}
    <table class="kpis">
        <tr>
            <td>
                <div class="rotulo">{{ __('reports.kpi_progress') }}</div>
                <div class="cifra">{{ $kpis['progress'] }}<span class="unidad"> %</span></div>
                <div class="barra"><div style="width: {{ min(100, max(0, $kpis['progress'])) }}%"></div></div>
            </td>
            <td>
                <div class="rotulo">{{ __('reports.kpi_finish') }}</div>
                <div class="cifra" style="font-size: 11pt">{{ $kpis['finish']?->format('d/m/Y') ?? '—' }}</div>
            </td>
            <td>
                <div class="rotulo">{{ __('reports.kpi_tasks') }}</div>
                <div class="cifra">{{ $kpis['task_count'] }}</div>
            </td>
            <td>
                <div class="rotulo">{{ __('reports.kpi_critical') }}</div>
                <div class="cifra">{{ $kpis['critical'] }}</div>
            </td>
            <td class="{{ $kpis['overdue'] > 0 ? 'alerta' : '' }}">
                <div class="rotulo">{{ __('reports.kpi_overdue') }}</div>
                <div class="cifra">{{ $kpis['overdue'] }}</div>
            </td>
        </tr>
    </table>

    @if ($digest !== [])
        {{-- Lo que merece atención va **antes** del detalle, no al final.
             Enterrado en la página seis, nadie lo lee; y un documento que se
             presenta como sano cuando el sistema detectó problemas es peor que
             no tener el documento. --}}
        <h2>{{ __('advisor.heading') }}</h2>

        @foreach ($digest as $group)
            <div class="aviso {{ $group['severity'] === \App\Models\ProjectFinding::SEVERITY_CRITICAL ? 'grave' : ($group['severity'] === \App\Models\ProjectFinding::SEVERITY_WARNING ? 'medio' : '') }}">
                <div class="titulo">{{ $group['headline'] }}</div>
                <div class="causa">{{ $group['why'] }}</div>
                @if ($group['subjects'] !== [])
                    <div class="quienes">{{ __('reports.affects') }}: {{ implode(' · ', $group['subjects']) }}</div>
                @endif
            </div>
        @endforeach
    @endif

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

    @if (($ganttPages ?? null) && $ganttPages->isNotEmpty())
        {{-- El diagrama, dentro del mismo archivo.
             Va en hoja aparte y con el ancho ajustado a la página: en pantalla
             el Gantt se desplaza a lo ancho, pero una hoja no se desplaza. Se
             recalculan los píxeles por día para que el proyecto entero quepa en
             vez de recortarlo por la derecha. --}}
        <div class="hoja-nueva">
            <h2>{{ __('reports.schedule_chart') }}</h2>

            @foreach ($ganttPages as $pageIndex => $pageTasks)
                <div class="gantt-hoja">
                    <table style="width:100%">
                        <tr>
                            <td style="width:26%; border:none; padding:0; vertical-align:top">
                                <table class="nombres">
                                    {{-- Un renglón vacío a la altura del encabezado de
                                         tiempo, para que los nombres queden a la altura
                                         de su barra y no un renglón arriba. --}}
                                    <tr><td style="height: {{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}px"></td></tr>
                                    @foreach ($pageTasks as $task)
                                        <tr>
                                            <td style="padding-left: {{ ($task->outline_depth ?? 0) * 6 }}px">
                                                @if ($task->is_critical && ! $task->is_summary)
                                                    <span class="critica">·</span>
                                                @endif
                                                {{ \Illuminate\Support\Str::limit($task->name, 34) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </table>
                            </td>
                            <td style="border:none; padding:0; vertical-align:top">
                                {{-- Imagen y no `<svg>` en línea: dompdf ignora el
                                     SVG escrito dentro del HTML, sin avisar. La
                                     hoja salía en blanco y el archivo se generaba
                                     sin un solo error. --}}
                                <img src="{{ $ganttImages[$pageIndex] }}"
                                     style="width: {{ $ganttLayout->width }}px" alt="">
                            </td>
                        </tr>
                    </table>
                </div>
            @endforeach
        </div>
    @endif

</body>
</html>
