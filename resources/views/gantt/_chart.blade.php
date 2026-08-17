@php
    /** @var \App\Support\Scheduling\GanttLayout $layout */
    $rowIndex = $layout->rowIndexById();
    $barHeight = 12;
    $rowHeight = \App\Support\Scheduling\GanttLayout::ROW_HEIGHT;
@endphp

<svg width="{{ max(320, $layout->width) }}" height="{{ $layout->height }}"
     viewBox="0 0 {{ max(320, $layout->width) }} {{ $layout->height }}"
     role="img"
     aria-label="{{ __('gantt.chart_label', ['project' => $project->name, 'count' => $tasks->count()]) }}"
     class="block">

    {{-- La descripción larga es lo que hace utilizable el diagrama con lector de
         pantalla. Un dibujo sin ella es una imagen vacía para quien no la ve. --}}
    <desc>{{ __('gantt.chart_description') }}</desc>

    <defs>
        <marker id="arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M 0 1 L 7 4 L 0 7 z" fill="#64748b" />
        </marker>
    </defs>

    {{-- Fines de semana al fondo: sin ellos, una barra que "salta" dos días
         parece un error de cálculo. --}}
    @foreach ($layout->weekendBands() as $band)
        <rect x="{{ $band['x'] }}" y="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
              width="{{ $band['width'] }}" height="{{ $layout->height }}"
              fill="#f1f5f9" />
    @endforeach

    {{-- Escala de tiempo --}}
    <rect x="0" y="0" width="{{ max(320, $layout->width) }}" height="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}" fill="#f8fafc" />
    <line x1="0" y1="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
          x2="{{ max(320, $layout->width) }}" y2="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
          stroke="#e2e8f0" />

    @foreach ($layout->ticks() as $tick)
        <line x1="{{ $tick['x'] }}" y1="{{ $tick['major'] ? 14 : 26 }}"
              x2="{{ $tick['x'] }}" y2="{{ $layout->height }}"
              stroke="{{ $tick['major'] ? '#cbd5e1' : '#e2e8f0' }}" stroke-width="1" />
        <text x="{{ $tick['x'] + 3 }}" y="{{ $tick['major'] ? 12 : 24 }}"
              font-size="9" fill="#64748b" font-family="system-ui, sans-serif">{{ $tick['label'] }}</text>
    @endforeach

    {{-- Hoy --}}
    @if (($todayX = $layout->todayX()) !== null)
        <line x1="{{ $todayX }}" y1="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
              x2="{{ $todayX }}" y2="{{ $layout->height }}"
              stroke="#dc2626" stroke-width="1.5" stroke-dasharray="3 3" />
        <text x="{{ $todayX + 3 }}" y="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT - 4 }}"
              font-size="9" fill="#dc2626" font-family="system-ui, sans-serif">{{ __('gantt.today') }}</text>
    @endif

    {{-- Flechas de dependencia, con ruteo ortogonal: salen del fin de la
         predecesora, bajan y entran al inicio de la sucesora. --}}
    @foreach ($dependencies as $link)
        @php
            $fromRow = $rowIndex[(int) $link->predecessor_id] ?? null;
            $toRow = $rowIndex[(int) $link->successor_id] ?? null;
        @endphp

        @continue($fromRow === null || $toRow === null)

        @php
            $from = $tasks[$fromRow];
            $to = $tasks[$toRow];

            $x1 = $layout->x($from->early_finish);
            $y1 = $layout->y($fromRow) + $rowHeight / 2;
            $x2 = $layout->x($to->early_start);
            $y2 = $layout->y($toRow) + $rowHeight / 2;

            // Si la sucesora arranca antes que el fin de la predecesora (lead),
            // el codo se traza por detrás para que la flecha no se cruce encima
            // de las barras y se vuelva ilegible.
            $elbow = $x2 > $x1 + 8 ? $x2 - 6 : $x1 + 8;
        @endphp

        <path d="M {{ $x1 }} {{ $y1 }} H {{ $elbow }} V {{ $y2 }} H {{ $x2 - 2 }}"
              fill="none" stroke="#94a3b8" stroke-width="1" marker-end="url(#arrow)" />
    @endforeach

    {{-- Barras --}}
    @foreach ($tasks as $index => $task)
        @php
            $x = $layout->x($task->early_start);
            $width = $layout->barWidth($task->early_start, $task->early_finish);
            $y = $layout->y($index) + ($rowHeight - $barHeight) / 2;
            $label = $task->name.' · '.($task->early_start?->format('d/m/y') ?? '')
                .' → '.($task->early_finish?->format('d/m/y') ?? '');
        @endphp

        @if ($task->is_summary)
            {{-- Un resumen no es una barra: es un corchete. La forma distinta
                 evita confundir el paquete con una tarea de verdad. --}}
            <path d="M {{ $x }} {{ $y + 4 }} L {{ $x }} {{ $y }} L {{ $x + $width }} {{ $y }} L {{ $x + $width }} {{ $y + 4 }}
                     L {{ $x + $width - 3 }} {{ $y + 8 }} L {{ $x + $width - 3 }} {{ $y + 3 }}
                     L {{ $x + 3 }} {{ $y + 3 }} L {{ $x + 3 }} {{ $y + 8 }} Z"
                  fill="#334155">
                <title>{{ $label }}</title>
            </path>
        @elseif ($task->isMilestone())
            @php $cx = $x; $cy = $y + $barHeight / 2; @endphp
            <path d="M {{ $cx }} {{ $cy - 6 }} L {{ $cx + 6 }} {{ $cy }} L {{ $cx }} {{ $cy + 6 }} L {{ $cx - 6 }} {{ $cy }} Z"
                  fill="{{ $task->is_critical ? '#dc2626' : '#1d4ed8' }}">
                <title>{{ $label }}</title>
            </path>
        @else
            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $barHeight }}" rx="2"
                  fill="{{ $task->is_critical ? '#dc2626' : '#2563eb' }}">
                <title>{{ $label }}</title>
            </rect>

            @if ((float) $task->percent_complete > 0)
                {{-- El avance va dentro de la barra, más oscuro: una segunda
                     barra encima haría creer que son dos tareas. --}}
                <rect x="{{ $x }}" y="{{ $y + 3 }}"
                      width="{{ $width * min(1, (float) $task->percent_complete / 100) }}" height="{{ $barHeight - 6 }}"
                      fill="#0f172a" fill-opacity="0.45" />
            @endif
        @endif
    @endforeach
</svg>
