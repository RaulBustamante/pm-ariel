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
                <span aria-hidden="true" class="text-blue-700" title="{{ __('tasks.milestone') }}">◆</span>
            @endif

            <input type="text" name="name" form="task-{{ $task->id }}" value="{{ $task->name }}"
                   aria-label="{{ __('tasks.name') }} — {{ __('tasks.row') }} {{ $row }}"
                   class="w-full min-w-[10rem] rounded border-transparent bg-transparent px-1 py-0.5 text-sm hover:border-slate-300 focus:border-blue-600 focus:bg-surface focus:ring-1 focus:ring-blue-600">

            @if ($isCritical && ! $task->is_summary)
                {{-- Texto además del color: la ruta crítica no puede depender de
                     distinguir rojo, que es justo la deficiencia más común. --}}
                <span class="shrink-0 rounded bg-red-100 px-1 text-[10px] font-semibold uppercase text-red-800">
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
                   class="w-16 rounded border-transparent bg-transparent px-1 py-0.5 text-sm hover:border-slate-300 focus:border-blue-600 focus:bg-surface focus:ring-1 focus:ring-blue-600">
        @else
            <span class="text-xs text-slate-400">—</span>
        @endunless
    </td>

    <td class="px-2 py-1.5">
        @unless ($task->is_summary)
            <input type="text" name="predecessors" form="task-{{ $task->id }}" value="{{ $predecessors }}"
                   placeholder="12FS+2d"
                   aria-label="{{ __('tasks.predecessors') }} — {{ $task->name }}"
                   class="w-24 rounded border-transparent bg-transparent px-1 py-0.5 font-mono text-xs hover:border-slate-300 focus:border-blue-600 focus:bg-surface focus:ring-1 focus:ring-blue-600">
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
                <span class="font-semibold text-red-700" title="{{ __('tasks.negative_float_explained') }}">
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
                    class="w-full min-w-[7rem] rounded border-transparent bg-transparent px-1 py-0.5 text-sm hover:border-slate-300 focus:border-blue-600 focus:bg-surface focus:ring-1 focus:ring-blue-600">
                <option value="">—</option>
                @foreach ($members as $member)
                    <option value="{{ $member->id }}" @selected((int) $task->owner_id === $member->id)>{{ $member->name }}</option>
                @endforeach
            </select>
        @endunless
    </td>

    <td class="whitespace-nowrap px-2 py-1.5 text-right">
        <div class="flex items-center justify-end gap-0.5">
            <button type="submit" form="task-{{ $task->id }}"
                    class="rounded px-1.5 py-0.5 text-xs font-medium text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-600">
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
                            class="rounded px-1 py-0.5 text-xs text-slate-500 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
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
                        class="rounded px-1 py-0.5 text-xs text-red-600 hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-red-600">
                    <span aria-hidden="true">✕</span>
                    <span class="sr-only">{{ __('common.delete') }} — {{ $task->name }}</span>
                </button>
            </form>
        </div>
    </td>
</tr>
