@php
    /** @var \App\Models\Task $task */
    $depth = (int) ($task->outline_depth ?? 0);
    $predecessors = $predecessorText[$task->id] ?? '';
    $isCritical = (bool) $task->is_critical;
@endphp

{{-- El formulario vive fuera de la tabla: un `<form>` no puede ser hijo directo
     de `<tr>`, y los navegadores lo sacan por su cuenta rompiendo el renglón.
     Los campos lo alcanzan con el atributo `form`, que existe justo para esto y
     permite una hoja de cálculo real: un formulario por renglón. --}}
@push('outside-table')
    <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}" id="task-{{ $task->id }}">
        @csrf
        @method('PUT')
    </form>
@endpush

<tr class="{{ $task->is_summary ? 'bg-slate-50/60 font-medium' : '' }}">
    <td class="px-2 py-1.5 text-right align-middle font-mono text-xs text-slate-400">{{ $row }}</td>

    <td class="px-3 py-1.5">
        <div class="flex items-center gap-1.5" style="padding-left: {{ $depth * 1.25 }}rem">
            @if ($task->is_summary)
                <span aria-hidden="true" class="text-slate-400">▸</span>
            @elseif ($task->isMilestone())
                <span aria-hidden="true" class="text-brand-700" title="{{ __('tasks.milestone') }}">◆</span>
            @endif

            <input type="text" name="name" form="task-{{ $task->id }}" value="{{ $task->name }}"
                   aria-label="{{ __('tasks.name') }} — {{ __('tasks.row') }} {{ $row }}"
                   class="w-full min-w-[10rem] rounded border-transparent bg-transparent px-1 py-0.5 text-sm hover:border-slate-300 focus:border-hud-500 focus:bg-surface focus:ring-1 focus:ring-hud-500">

            @if ($isCritical && ! $task->is_summary)
                {{-- Texto además del color: la ruta crítica no puede depender de
                     distinguir rojo, que es justo la deficiencia más común. --}}
                <span class="shrink-0 rounded bg-[var(--color-badge-danger-bg)] px-1 text-[10px] font-semibold uppercase text-[var(--color-badge-danger-fg)]">
                    {{ __('tasks.critical') }}
                </span>
            @endif
        </div>
    </td>

    <td class="px-2 py-1.5">
        @unless ($task->is_summary)
            <input type="text" name="duration" form="task-{{ $task->id }}"
                   value="{{ $durations->toHuman((int) $task->duration_minutes) }}"
                   aria-label="{{ __('tasks.duration') }} — {{ $task->name }}"
                   class="w-16 rounded border-transparent bg-transparent px-1 py-0.5 text-sm hover:border-slate-300 focus:border-hud-500 focus:bg-surface focus:ring-1 focus:ring-hud-500">
        @else
            <span class="text-xs text-slate-400">—</span>
        @endunless
    </td>

    <td class="px-2 py-1.5">
        @unless ($task->is_summary)
            <input type="text" name="predecessors" form="task-{{ $task->id }}" value="{{ $predecessors }}"
                   placeholder="12FS+2d"
                   aria-label="{{ __('tasks.predecessors') }} — {{ $task->name }}"
                   class="w-24 rounded border-transparent bg-transparent px-1 py-0.5 font-mono text-xs hover:border-slate-300 focus:border-hud-500 focus:bg-surface focus:ring-1 focus:ring-hud-500">
        @endunless
    </td>

    <td class="whitespace-nowrap px-3 py-1.5 text-slate-600">{{ $task->early_start?->format('d/m/y') ?? '—' }}</td>
    <td class="whitespace-nowrap px-3 py-1.5 text-slate-600">{{ $task->early_finish?->format('d/m/y') ?? '—' }}</td>

    @expert
        <td class="whitespace-nowrap px-2 py-1.5 text-xs">
            @php $float = $task->total_float_minutes; @endphp
            @if ($float === null)
                <span class="text-slate-400">—</span>
            @elseif ($float < 0)
                <span class="font-semibold text-[var(--color-badge-danger-fg)]" title="{{ __('tasks.negative_float_explained') }}">
                    {{ $durations->toHuman($float) }}
                </span>
            @else
                <span class="text-slate-600">{{ $durations->toHuman($float) }}</span>
            @endif
        </td>
    @endexpert

    <td class="px-3 py-1.5">
        @unless ($task->is_summary)
            <select name="owner_id" form="task-{{ $task->id }}"
                    aria-label="{{ __('tasks.owner') }} — {{ $task->name }}"
                    class="w-full min-w-[7rem] rounded border-transparent bg-transparent px-1 py-0.5 text-sm hover:border-slate-300 focus:border-hud-500 focus:bg-surface focus:ring-1 focus:ring-hud-500">
                <option value="">—</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((int) $task->owner_id === $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        @endunless
    </td>

    {{-- El avance, capturable aquí mismo.
         Vivía solo en el detalle de la tarea, y el detalle no se alcanzaba desde
         esta pantalla: quien quería decir «ya la terminé» no tenía dónde. --}}
    <td class="px-2 py-1.5">
        @unless ($task->is_summary)
            <div class="flex items-center gap-1.5">
                <input type="number" name="percent_complete" form="task-{{ $task->id }}" min="0" max="100" step="5"
                       value="{{ (int) $task->percent_complete }}"
                       aria-label="{{ __('tasks.progress') }} — {{ $task->name }}"
                       class="w-14 rounded border-transparent bg-transparent px-1 py-0.5 text-right text-sm tabular hover:border-slate-300 focus:border-hud-500 focus:bg-surface focus:ring-1 focus:ring-hud-500">

                <x-task-state :task="$task" :show-percent="false" />
            </div>
        @else
            {{-- Un resumen no se captura: su avance es el de sus hijas. Poner el
                 campo invitaría a teclear un número que el motor pisaría. --}}
            <div class="flex items-center gap-1.5">
                <span class="w-14 pr-1 text-right text-sm tabular text-slate-400">{{ (int) $task->percent_complete }} %</span>
                <div class="meter h-1.5 w-12"><div class="meter-fill" style="width: {{ min(100, (int) $task->percent_complete) }}%"></div></div>
            </div>
        @endunless
    </td>

    <td class="whitespace-nowrap px-2 py-1.5 text-right">
        <div class="flex items-center justify-end gap-0.5">
            {{-- La puerta al detalle. Estaba solo en el Gantt, el calendario y el
                 inicio; desde la lista —que es donde la gente vive— no había
                 forma de llegar a las notas, los adjuntos ni el historial. --}}
            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
               title="{{ __('tasks.open_detail') }}"
               class="rounded px-1 py-0.5 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-hud-500">
                <span aria-hidden="true">{{ $task->hasNotes() ? '✎' : '⋯' }}</span>
                <span class="sr-only">
                    {{ __('tasks.open_detail') }} — {{ $task->name }}@if ($task->hasNotes()) · {{ __('tasks.has_notes') }}@endif
                </span>
            </a>

            <button type="submit" form="task-{{ $task->id }}"
                    class="rounded px-1.5 py-0.5 text-xs font-medium text-brand-700 hover:bg-brand-50 focus:outline-none focus:ring-2 focus:ring-hud-500">
                {{ __('common.save') }}<span class="sr-only"> — {{ $task->name }}</span>
            </button>

            @php
                // La etiqueta va junto al símbolo y no se calcula en la vista:
                // PHP no admite un ternario dentro de la interpolación de cadena,
                // y hacerlo ahí sería además ilegible.
                $outlineActions = [
                    'outdent' => ['◀', __('tasks.outdent')],
                    'indent' => ['▶', __('tasks.indent')],
                    'up' => ['▲', __('tasks.move_up')],
                    'down' => ['▼', __('tasks.move_down')],
                ];
            @endphp

            @foreach ($outlineActions as $action => [$glyph, $label])
                <form method="POST" action="{{ route('projects.tasks.outline', [$project, $task, $action]) }}" class="inline">
                    @csrf
                    <button type="submit" title="{{ $label }}"
                            class="rounded px-1 py-0.5 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-hud-500">
                        <span aria-hidden="true">{{ $glyph }}</span>
                        <span class="sr-only">{{ $label }} — {{ $task->name }}</span>
                    </button>
                </form>
            @endforeach

            <form method="POST" action="{{ route('projects.tasks.destroy', [$project, $task]) }}" class="inline"
                  onsubmit="return confirm('{{ __('tasks.confirm_delete') }}')">
                @csrf
                @method('DELETE')
                <button type="submit"
                        class="rounded px-1 py-0.5 text-xs text-[var(--color-badge-danger-fg)] hover:bg-[var(--color-badge-danger-bg)] focus:outline-none focus:ring-2 focus:ring-[var(--color-badge-danger-fg)]">
                    <span aria-hidden="true">✕</span>
                    <span class="sr-only">{{ __('common.delete') }} — {{ $task->name }}</span>
                </button>
            </form>
        </div>
    </td>
</tr>
