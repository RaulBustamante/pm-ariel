@php
    /** @var \App\Support\Scheduling\GanttLayout $layout */
    $rowHeight = \App\Support\Scheduling\GanttLayout::ROW_HEIGHT;
    $headerHeight = \App\Support\Scheduling\GanttLayout::HEADER_HEIGHT;
    $barHeight = 11;

    // Alto de esta hoja, no del proyecto entero.
    $height = $headerHeight + ($pageTasks->count() * $rowHeight) + 4;
    $width = max(320, $layout->width);
@endphp

<svg width="{{ $width }}" height="{{ $height }}" viewBox="0 0 {{ $width }} {{ $height }}"
     role="img" aria-label="{{ __('gantt.chart_label', ['project' => $project->name, 'count' => $pageTasks->count()]) }}">

    @foreach ($layout->weekendBands() as $band)
        <rect x="{{ $band['x'] }}" y="{{ $headerHeight }}" width="{{ $band['width'] }}" height="{{ $height }}" fill="#f1f5f9" />
    @endforeach

    {{-- El encabezado de tiempo se repite en cada hoja. Sin él, a partir de la
         página 2 las barras flotan sin referencia. --}}
    <rect x="0" y="0" width="{{ $width }}" height="{{ $headerHeight }}" fill="#f8fafc" />
    <line x1="0" y1="{{ $headerHeight }}" x2="{{ $width }}" y2="{{ $headerHeight }}" stroke="#cbd5e1" />

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
</svg>
