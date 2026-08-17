@php
    /** @var \App\Support\Scheduling\GanttLayout $layout */
    $rowHeight = \App\Support\Scheduling\GanttLayout::ROW_HEIGHT;
    $headerHeight = \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT;
    $barHeight = 11;

    /*
    | Dos modos, y por eso los parámetros.
    |
    | La impresión desde el navegador usa hoja apaisada y pone los nombres en una
    | columna de HTML al lado; ahí el diagrama va derecho y sin nombres, que es
    | como nació esta pieza.
    |
    | El PDF no puede hacer eso: dompdf fija la orientación **una vez para todo el
    | documento**, así que una hoja apaisada suelta no existe. La salida es girar
    | el dibujo 90° sobre una hoja vertical —se lee volteando el papel, como
    | cualquier plano— y para eso los nombres tienen que ir **dentro** del SVG:
    | girado el dibujo, una columna de HTML al lado dejaría de alinearse.
    */
    $nameWidth = $nameWidth ?? 0;
    $rotate = $rotate ?? false;

    /*
    | Alto de renglón variable, para que el proyecto entero quepa en una hoja.
    |
    | Con 26 px fijos, cincuenta y cuatro tareas eran tres hojas, y un diagrama
    | repartido en tres hojas ya no se lee como diagrama: se pierde justo lo que
    | uno va a buscar, que es ver el proyecto completo de un golpe.
    |
    | Se comprime hasta 9 px. Por debajo de eso el texto deja de ser legible
    | impreso, así que a partir de ~70 tareas se vuelve a paginar en vez de
    | seguir apretando: preferible dos hojas legibles que una ilegible.
    */
    $rowHeight = $compactRowHeight ?? $rowHeight;
    $barHeight = max(4, min(11, $rowHeight - 5));
    $fontSize = $rowHeight >= 20 ? 7.5 : max(4.8, $rowHeight * 0.55);

    $chartWidth = max(320, $layout->width);
    $width = $nameWidth + $chartWidth;
    $height = $headerHeight + ($pageTasks->count() * $rowHeight) + 4;

    // Girado, el dibujo ocupa una caja con los lados intercambiados.
    $outerWidth = $rotate ? $height : $width;
    $outerHeight = $rotate ? $width : $height;
@endphp

<svg xmlns="http://www.w3.org/2000/svg"
     width="{{ $outerWidth }}" height="{{ $outerHeight }}"
     viewBox="0 0 {{ $outerWidth }} {{ $outerHeight }}"
     role="img" aria-label="{{ __('gantt.chart_label', ['project' => $project->name, 'count' => $pageTasks->count()]) }}">

    {{-- `translate` y luego `rotate` deja el borde superior del diagrama contra
         el canto derecho de la hoja, que es lo que hace que se lea girando el
         papel en el sentido de las manecillas: el convenio de toda hoja de plano
         metida en un documento vertical. --}}
    <g @if ($rotate) transform="translate({{ $height }} 0) rotate(90)" @endif>

        @if ($nameWidth > 0)
            {{-- Los nombres, dentro del dibujo. --}}
            <rect x="0" y="0" width="{{ $nameWidth }}" height="{{ $headerHeight }}" fill="#f8fafc" />
            <text x="4" y="{{ $headerHeight - 12 }}" font-size="7" fill="#475569"
                  font-family="sans-serif" letter-spacing="0.5">{{ mb_strtoupper(__('tasks.name')) }}</text>

            @foreach ($pageTasks as $index => $task)
                @php
                    $ty = $headerHeight + ($index * $rowHeight) + ($rowHeight / 2) + 3;
                    $indent = 4 + (($task->outline_depth ?? 0) * 6);
                @endphp

                @if ($task->is_critical && ! $task->is_summary)
                    <circle cx="{{ $indent + 2 }}" cy="{{ $ty - $fontSize * 0.35 }}" r="{{ min(2, $rowHeight / 7) }}" fill="#dc2626" />
                @endif

                <text x="{{ $indent + ($task->is_critical && ! $task->is_summary ? 8 : 0) }}" y="{{ $ty }}"
                      font-size="{{ $fontSize }}" font-family="sans-serif"
                      fill="{{ $task->is_summary ? '#0f172a' : '#334155' }}"
                      font-weight="{{ $task->is_summary ? 'bold' : 'normal' }}">{{ \Illuminate\Support\Str::limit($task->name, (int) ($nameWidth / max(3.2, $fontSize * 0.52))) }}</text>
            @endforeach

            <line x1="{{ $nameWidth }}" y1="0" x2="{{ $nameWidth }}" y2="{{ $height }}" stroke="#cbd5e1" stroke-width="0.5" />
        @endif

        {{-- El diagrama se recorre a la derecha de la columna de nombres. Así el
             cálculo de `x` del motor no cambia por dibujar aquí o allá. --}}
        <g @if ($nameWidth > 0) transform="translate({{ $nameWidth }} 0)" @endif>

            {{-- Bandas alternas por renglón.
                 En un diagrama ancho, seguir con la vista desde el nombre hasta
                 su barra es lo que más cuesta, y una regla horizontal a lápiz es
                 lo que la gente acaba dibujando encima del papel. --}}
            @foreach ($pageTasks as $bandIndex => $bandTask)
                @if ($bandIndex % 2 === 1)
                    <rect x="0" y="{{ $headerHeight + ($bandIndex * $rowHeight) }}"
                          width="{{ $chartWidth }}" height="{{ $rowHeight }}" fill="#f8fafc" />
                @endif
            @endforeach

            @foreach ($layout->weekendBands() as $band)
                <rect x="{{ $band['x'] }}" y="{{ $headerHeight }}" width="{{ $band['width'] }}" height="{{ $height }}" fill="#f1f5f9" />
            @endforeach

            {{-- El encabezado de tiempo se repite en cada hoja. Sin él, a partir de la
                 página 2 las barras flotan sin referencia. --}}
            <rect x="0" y="0" width="{{ $chartWidth }}" height="{{ $headerHeight }}" fill="#f8fafc" />
            <line x1="0" y1="{{ $headerHeight }}" x2="{{ $chartWidth }}" y2="{{ $headerHeight }}" stroke="#cbd5e1" />

            @foreach ($layout->ticks() as $tick)
                <line x1="{{ $tick['x'] }}" y1="{{ $tick['major'] ? 14 : 26 }}" x2="{{ $tick['x'] }}" y2="{{ $height }}"
                      stroke="{{ $tick['major'] ? '#cbd5e1' : '#e2e8f0' }}" stroke-width="0.5" />
                <text x="{{ $tick['x'] + 2 }}" y="{{ $tick['major'] ? 11 : 23 }}"
                      font-size="7.5" fill="#64748b" font-family="sans-serif">{{ $tick['label'] }}</text>
            @endforeach

            @if (($todayX = $layout->todayX()) !== null)
                <line x1="{{ $todayX }}" y1="{{ $headerHeight }}" x2="{{ $todayX }}" y2="{{ $height }}"
                      stroke="#dc2626" stroke-width="1" stroke-dasharray="2 2" />
            @endif

            @foreach ($pageTasks as $index => $task)
                @php
                    $x = $layout->x($task->early_start);
                    $w = $layout->barWidth($task->early_start, $task->early_finish);
                    $y = $headerHeight + ($index * $rowHeight) + ($rowHeight - $barHeight) / 2;
                @endphp

                @if ($task->is_summary)
                    <path d="M {{ $x }} {{ $y + 4 }} L {{ $x }} {{ $y }} L {{ $x + $w }} {{ $y }} L {{ $x + $w }} {{ $y + 4 }}
                             L {{ $x + $w - 3 }} {{ $y + 7 }} L {{ $x + $w - 3 }} {{ $y + 3 }}
                             L {{ $x + 3 }} {{ $y + 3 }} L {{ $x + 3 }} {{ $y + 7 }} Z" fill="#334155" />
                @elseif ($task->isMilestone())
                    @php $cy = $y + $barHeight / 2; @endphp
                    <path d="M {{ $x }} {{ $cy - 5 }} L {{ $x + 5 }} {{ $cy }} L {{ $x }} {{ $cy + 5 }} L {{ $x - 5 }} {{ $cy }} Z"
                          fill="{{ $task->is_critical ? '#dc2626' : '#1d4ed8' }}" />
                @else
                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $w }}" height="{{ $barHeight }}" rx="2"
                          fill="{{ $task->is_critical ? '#dc2626' : '#2563eb' }}" />

                    @if ((float) $task->percent_complete > 0)
                        <rect x="{{ $x }}" y="{{ $y + 3 }}"
                              width="{{ $w * min(1, (float) $task->percent_complete / 100) }}" height="{{ $barHeight - 6 }}"
                              fill="#0f172a" fill-opacity="0.45" />
                    @endif
                @endif
            @endforeach
        </g>
    </g>
</svg>
