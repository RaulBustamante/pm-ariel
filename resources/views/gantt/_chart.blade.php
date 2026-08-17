@php
    /** @var \App\Support\Scheduling\GanttLayout $layout */
    $rowIndex = $layout->rowIndexById();
    $barHeight = 12;
    $rowHeight = \App\Support\Scheduling\GanttLayout::ROW_HEIGHT;
@endphp

<svg data-gantt data-pixels-per-day="{{ $layout->pixelsPerDay }}"
     width="{{ max(320, $layout->width) }}" height="{{ $layout->height }}"
     viewBox="0 0 {{ max(320, $layout->width) }} {{ $layout->height }}"
     role="img"
     aria-label="{{ __('gantt.chart_label', ['project' => $project->name, 'count' => $tasks->count()]) }}"
     class="block">

    {{-- La descripción larga es lo que hace utilizable el diagrama con lector de
         pantalla. Un dibujo sin ella es una imagen vacía para quien no la ve. --}}
    <desc>{{ __('gantt.chart_description') }}</desc>

    <defs>
        <marker id="arrow" viewBox="0 0 8 8" refX="7" refY="4" markerWidth="6" markerHeight="6" orient="auto-start-reverse">
            <path d="M 0 1 L 7 4 L 0 7 z" class="g-arrow" />
        </marker>
    </defs>

    {{-- Fines de semana al fondo: sin ellos, una barra que "salta" dos días
         parece un error de cálculo. --}}
    @foreach ($layout->weekendBands() as $band)
        <rect x="{{ $band['x'] }}" y="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
              width="{{ $band['width'] }}" height="{{ $layout->height }}"
              class="g-weekend" />
    @endforeach

    {{-- Escala de tiempo --}}
    <rect x="0" y="0" width="{{ max(320, $layout->width) }}" height="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}" class="g-header" />
    <line x1="0" y1="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
          x2="{{ max(320, $layout->width) }}" y2="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
          class="g-rule" />

    @foreach ($layout->ticks() as $tick)
        <line x1="{{ $tick['x'] }}" y1="{{ $tick['major'] ? 14 : 26 }}"
              x2="{{ $tick['x'] }}" y2="{{ $layout->height }}"
              class="{{ $tick['major'] ? 'g-grid-major' : 'g-grid' }}" stroke-width="1" />
        <text x="{{ $tick['x'] + 3 }}" y="{{ $tick['major'] ? 12 : 24 }}"
              font-size="9" class="g-axis" font-family="system-ui, sans-serif">{{ $tick['label'] }}</text>
    @endforeach

    {{-- Hoy --}}
    @if (($todayX = $layout->todayX()) !== null)
        <line x1="{{ $todayX }}" y1="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT }}"
              x2="{{ $todayX }}" y2="{{ $layout->height }}"
              class="g-today" stroke-width="1.5" stroke-dasharray="3 3" />
        <text x="{{ $todayX + 3 }}" y="{{ \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT - 4 }}"
              font-size="9" class="g-today-label" font-family="system-ui, sans-serif">{{ __('gantt.today') }}</text>
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
              fill="none" class="g-link" stroke-width="1" marker-end="url(#arrow)" />
    @endforeach

    {{-- Barras --}}
    @foreach ($tasks as $index => $task)
        @php
            $x = $layout->x($task->early_start);
            $width = $layout->barWidth($task->early_start, $task->early_finish);
            $y = $layout->y($index) + ($rowHeight - $barHeight) / 2;
            $label = $task->name.' · '.($task->early_start?->format('d/m/y') ?? '')
                .' → '.($task->early_finish?->format('d/m/y') ?? '');
            $frozen = $baselineByTask[$task->id] ?? null;
        @endphp

        @if ($frozen && $frozen->start && $frozen->finish && ! $task->is_summary)
            {{-- La línea base va debajo y más delgada, en gris: es referencia, no
                 el plan. Si tuviera el mismo peso, competirían por la atención y
                 la barra real dejaría de leerse de un vistazo. --}}
            <rect x="{{ $layout->x($frozen->start) }}"
                  y="{{ $y + $barHeight - 1 }}"
                  width="{{ $layout->barWidth($frozen->start, $frozen->finish) }}"
                  height="3" rx="1" class="g-summary">
                <title>{{ __('gantt.baseline_bar', [
                    'from' => $frozen->start->format('d/m/y'),
                    'to' => $frozen->finish->format('d/m/y'),
                ]) }}</title>
            </rect>
        @endif

        @if ($task->is_summary)
            {{-- Un resumen no es una barra: es un corchete. La forma distinta
                 evita confundir el paquete con una tarea de verdad. --}}
            <path d="M {{ $x }} {{ $y + 4 }} L {{ $x }} {{ $y }} L {{ $x + $width }} {{ $y }} L {{ $x + $width }} {{ $y + 4 }}
                     L {{ $x + $width - 3 }} {{ $y + 8 }} L {{ $x + $width - 3 }} {{ $y + 3 }}
                     L {{ $x + 3 }} {{ $y + 3 }} L {{ $x + 3 }} {{ $y + 8 }} Z"
                  class="g-bracket">
                <title>{{ $label }}</title>
            </path>
        @elseif ($task->isMilestone())
            @php $cx = $x; $cy = $y + $barHeight / 2; @endphp
            <path d="M {{ $cx }} {{ $cy - 6 }} L {{ $cx + 6 }} {{ $cy }} L {{ $cx }} {{ $cy + 6 }} L {{ $cx - 6 }} {{ $cy }} Z"
                  class="{{ $task->is_critical ? 'g-bar-critical' : 'g-bar' }}">
                <title>{{ $label }}</title>
            </path>
        @else
            {{-- Un solo atributo `class`.
                 Estuvo partido en dos —uno con el cursor de arrastre y otro con
                 el color— y el navegador se queda con el primero y descarta el
                 segundo en silencio: las barras salían sin color y nada fallaba. --}}
            <rect @can('update', $project) data-task-bar data-task-id="{{ $task->id }}" data-task-name="{{ $task->name }}" @endcan
                  x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $barHeight }}" rx="2"
                  class="{{ $task->is_critical ? 'g-bar-critical' : 'g-bar' }}@can('update', $project) cursor-ew-resize @endcan">
                <title>{{ $label }}</title>
            </rect>

            @if ((float) $task->percent_complete > 0)
                {{-- El avance va dentro de la barra, más oscuro: una segunda
                     barra encima haría creer que son dos tareas. --}}
                <rect x="{{ $x }}" y="{{ $y + 3 }}"
                      width="{{ $width * min(1, (float) $task->percent_complete / 100) }}" height="{{ $barHeight - 6 }}"
                      class="g-progress" />
            @endif
        @endif
    @endforeach
</svg>
