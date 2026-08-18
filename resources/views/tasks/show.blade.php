@extends('layouts.app')

@section('title', $task->name)
@section('heading', $task->name)

@section('content')
    <div class="mb-4 flex flex-wrap items-center gap-2 text-xs text-slate-600">
        <a href="{{ route('projects.tasks.index', $project) }}"
           class="rounded text-brand-700 underline hover:text-brand-800">
            ← {{ __('tasks.title') }}
        </a>
        @if ($task->wbs_code)
            <span class="font-mono text-slate-400">{{ $task->wbs_code }}</span>
        @endif
        @unless ($task->is_summary)
            <x-task-state :task="$task" />
        @endunless
        @if ($task->is_critical)
            <span class="badge badge-danger">{{ __('tasks.critical') }}</span>
        @endif
        @if ($task->isMilestone())
            <span class="badge badge-brand">{{ __('tasks.milestone') }}</span>
        @endif
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- Edición --}}
            <form method="POST" action="{{ route('projects.tasks.update', [$project, $task]) }}" class="card">
                @csrf
                @method('PUT')

                <div class="card-header">
                    <h2 class="card-title">{{ __('tasks.name') }}</h2>
                </div>

                <div class="space-y-3 p-4">
                    <div>
                        <label for="name-field" class="field-label">{{ __('tasks.name') }}</label>
                        <input id="name-field" type="text" name="name" value="{{ old('name', $task->name) }}" class="field" required>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3">
                        <div>
                            <label for="duration-field" class="field-label">{{ __('tasks.duration') }}</label>
                            <input id="duration-field" type="text" name="duration"
                                   value="{{ old('duration', $durations->toHuman((int) $task->duration_minutes)) }}" class="field">
                            @error('duration') <p role="alert" class="mt-1 text-xs text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label for="cost-field" class="field-label">{{ __('tasks.cost') }}</label>
                            <input id="cost-field" type="number" step="0.01" min="0" name="cost"
                                   value="{{ old('cost', $task->cost) }}" class="field">
                        </div>

                        <div>
                            <label for="progress-field" class="field-label">{{ __('tasks.progress') }} %</label>
                            <input id="progress-field" type="number" step="1" min="0" max="100" name="percent_complete"
                                   value="{{ old('percent_complete', (int) $task->percent_complete) }}" class="field">
                        </div>
                    </div>

                    {{-- El costo real va junto al avance porque es el mismo acto:
                         quien reporta que una tarea va al 60 % es quien sabe qué
                         se lleva gastado. Vacío significa «todavía no lo sé» y no
                         «salió gratis» — de esa diferencia depende que el valor
                         ganado pueda calcular el índice de costo o tenga que
                         decir que le faltan datos. --}}
                    <div class="sm:max-w-[16rem]">
                        <label for="actual-cost-field" class="field-label">{{ __('evm.actual_cost') }}</label>
                        <input id="actual-cost-field" type="number" step="0.01" min="0" name="actual_cost"
                               value="{{ old('actual_cost', $task->actual_cost) }}" class="field">
                        <p class="field-help">{{ __('evm.actual_cost_help') }}</p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="owner-field" class="field-label">{{ __('tasks.owner') }}</label>
                            <select id="owner-field" name="owner_id" class="field">
                                <option value="">—</option>
                                @foreach ($members as $member)
                                    <option value="{{ $member->id }}" @selected((int) old('owner_id', $task->owner_id) === $member->id)>
                                        {{ $member->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="constraint-field" class="field-label">{{ __('tasks.constraint') }}</label>
                            <select id="constraint-field" name="constraint_type" class="field">
                                @foreach (\App\Support\Scheduling\ConstraintType::cases() as $type)
                                    <option value="{{ $type->value }}" @selected(old('constraint_type', $task->constraint_type) === $type->value)>
                                        {{ __("constraints.{$type->value}") }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- Las notas de la tarea.
                         La columna existía desde la Etapa 3 y ninguna pantalla la
                         escribía: era el mismo caso de las fechas reales, un dato
                         que el modelo prometía y que nadie podía capturar. --}}
                    <div>
                        <label for="description-field" class="field-label">{{ __('tasks.notes') }}</label>
                        <textarea id="description-field" name="description" rows="4" class="field"
                                  @cannot('update', $project) readonly @endcannot>{{ old('description', $task->description) }}</textarea>
                        <p class="field-help">{{ __('tasks.notes_help') }}</p>
                    </div>

                    <div>
                        <label for="constraint-date-field" class="field-label">{{ __('tasks.constraint_date') }}</label>
                        <input id="constraint-date-field" type="date" name="constraint_date"
                               value="{{ old('constraint_date', $task->constraint_date?->format('Y-m-d')) }}" class="field sm:max-w-[12rem]">
                        @error('constraint_date') <p role="alert" class="mt-1 text-xs text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
                    </div>

                    @can('update', $project)
                        <div class="border-t border-slate-100 pt-3">
                            <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                        </div>
                    @endcan
                </div>
            </form>

            {{-- Asignaciones --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('resources.title') }}</h2>
                </div>

                <div class="p-4">
                    @forelse ($assignments as $assignment)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5 text-sm last:border-0">
                            <span class="min-w-0 truncate text-slate-800">
                                {{ $assignment->resource?->name ?? '—' }}
                                @if ($assignment->resource?->is_external)
                                    <span class="badge badge-warn ml-1">{{ __('resources.external') }}</span>
                                @endif
                            </span>
                            <div class="flex items-center gap-3">
                                {{-- Cada tipo en su unidad. Un material con
                                     <<100 %>> seria un dato falso: no tiene
                                     jornada que repartir. --}}
                                @if ($assignment->resource?->isMaterial())
                                    <span class="badge badge-neutral tabular">
                                        {{ rtrim(rtrim(number_format((float) $assignment->quantity, 3), '0'), '.') }}
                                        {{ $assignment->resource->unit_of_measure }}
                                    </span>
                                @else
                                    <span class="badge badge-neutral tabular">{{ $assignment->units_percent }} %</span>
                                @endif

                                @php $line = \App\Support\Costing\TaskCost::ofAssignment($assignment, $task); @endphp
                                @if ($line['cost'] > 0)
                                    <span class="text-xs tabular text-slate-500">{{ number_format($line['cost'], 2) }}</span>
                                @endif
                                @can('update', $project)
                                    <form method="POST" action="{{ route('projects.assignments.destroy', [$project, $task, $assignment->resource_id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('resources.empty') }}</p>
                    @endforelse

                    @can('update', $project)
                        @if ($resources->isNotEmpty())
                            <form method="POST" action="{{ route('projects.assignments.store', [$project, $task]) }}"
                                  class="mt-3 flex flex-wrap items-end gap-2 border-t border-slate-100 pt-3">
                                @csrf
                                <div class="min-w-[10rem] flex-1">
                                    <label for="assign-resource" class="field-label">{{ __('resources.title') }}</label>
                                    {{-- El tipo viaja en el propio <option> para que el
                                         formulario pueda cambiar el campo de medida sin
                                         preguntarle al servidor. La regla de verdad la
                                         aplica el controlador. --}}
                                    <select id="assign-resource" name="resource_id" class="field" required data-assign-resource>
                                        @foreach ($resources as $resource)
                                            <option value="{{ $resource->id }}"
                                                    data-material="{{ $resource->isMaterial() ? '1' : '0' }}"
                                                    data-unit="{{ $resource->unit_of_measure }}">
                                                {{ $resource->name }} · {{ __("resources.type_{$resource->type}") }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div class="w-24" data-assign-units>
                                    <label for="assign-units" class="field-label">{{ __('resources.units') }}</label>
                                    <input id="assign-units" type="number" name="units_percent" value="100" min="1" max="500" class="field">
                                </div>

                                <div class="w-28" data-assign-quantity hidden>
                                    <label for="assign-quantity" class="field-label">
                                        {{ __('resources.quantity') }} <span data-assign-unit-label class="text-slate-500"></span>
                                    </label>
                                    <input id="assign-quantity" type="number" step="0.001" min="0.001" name="quantity" class="field">
                                </div>

                                <button type="submit" class="btn btn-secondary">{{ __('resources.assign') }}</button>
                            </form>
                        @endif
                    @endcan
                </div>
            </section>

            {{-- Archivos --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('attachments.title') }}</h2>
                </div>

                <div class="p-4">
                    @forelse ($attachments as $attachment)
                        <div class="flex items-center justify-between gap-3 border-b border-slate-100 py-1.5 text-sm last:border-0">
                            <a href="{{ route('projects.attachments.download', [$project, $attachment]) }}"
                               class="min-w-0 truncate text-brand-700 underline hover:text-brand-800">
                                {{ $attachment->original_name }}
                            </a>

                            <div class="flex shrink-0 items-center gap-3 text-xs text-slate-500">
                                <span>{{ $attachment->humanSize() }}</span>
                                @can('update', $project)
                                    <form method="POST" action="{{ route('projects.attachments.destroy', [$project, $attachment]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn btn-danger btn-sm">{{ __('common.delete') }}</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('attachments.none') }}</p>
                    @endforelse

                    @can('update', $project)
                        <form method="POST" action="{{ route('projects.attachments.store', [$project, $task]) }}"
                              enctype="multipart/form-data" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                            @csrf
                            <label for="attachment-field" class="field-label">{{ __('attachments.add') }}</label>
                            <input id="attachment-field" type="file" name="file" class="field" required>
                            <p class="field-help">{{ __('attachments.help') }}</p>
                            @error('file') <p role="alert" class="text-xs text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
                            <button type="submit" class="btn btn-secondary">{{ __('attachments.add') }}</button>
                        </form>
                    @endcan
                </div>
            </section>

            {{-- Qué ha pasado aquí: lo que la gente dijo y lo que el sistema
                 registró, en **un solo hilo**.

                 Separados en dos listas obligan a leer las dos y a cruzarlas
                 mentalmente por fecha, y ahí se pierde justo lo que se busca:
                 que el comentario «el proveedor pidió dos días» está al lado
                 del cambio de duración que alguien hizo esa misma tarde. --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('tasks.comments') }}</h2>
                </div>

                <div class="p-4">
                    <p class="mb-3 max-w-2xl text-xs leading-relaxed text-slate-600">{{ __('tasks.comments_help') }}</p>

                    @can('update', $project)
                        <form method="POST" action="{{ route('projects.tasks.comments.store', [$project, $task]) }}"
                              class="mb-4 space-y-2">
                            @csrf
                            <label for="comment-body" class="sr-only">{{ __('tasks.comment_add') }}</label>
                            <textarea id="comment-body" name="body" rows="2" class="field"
                                      placeholder="{{ __('tasks.comment_placeholder') }}" required></textarea>
                            @error('body')
                                <p role="alert" class="text-sm text-[var(--color-badge-danger-fg)]">{{ $message }}</p>
                            @enderror
                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('tasks.comment_add') }}</button>
                        </form>
                    @endcan

                    @forelse ($timeline as $entry)
                        <div class="flex gap-3 border-b border-slate-100 py-2.5 text-xs last:border-0">
                            {{-- El punto distingue de un vistazo lo que alguien
                                 escribió de lo que el sistema anotó, sin tener
                                 que leer el renglón entero. --}}
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full
                                         {{ $entry['kind'] === 'comment' ? 'bg-brand-600' : 'bg-slate-300' }}"
                                  aria-hidden="true"></span>

                            <span class="w-24 shrink-0 text-slate-500">{{ $entry['at']->format('d/m/y H:i') }}</span>

                            <div class="min-w-0 flex-1">
                                <p class="text-slate-800">
                                    <span class="font-medium">{{ $entry['who'] }}</span>
                                    @if ($entry['kind'] === 'change')
                                        · {{ __("audit.events.{$entry['event']}") }}
                                        @if ($entry['fields'] !== [])
                                            <span class="text-slate-500">· {{ implode(', ', $entry['fields']) }}</span>
                                        @endif
                                    @endif
                                </p>

                                @if ($entry['kind'] === 'comment')
                                    <p class="mt-0.5 whitespace-pre-line leading-relaxed text-slate-700">{{ $entry['body'] }}</p>
                                @endif
                            </div>

                            @if ($entry['comment']?->canBeDeletedBy(auth()->user()))
                                <form method="POST" class="shrink-0"
                                      action="{{ route('projects.tasks.comments.destroy', [$project, $task, $entry['comment']]) }}"
                                      onsubmit="return confirm('{{ __('tasks.comment_delete_confirm') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded px-1 text-slate-400 hover:text-[var(--color-badge-danger-fg)]">
                                        <span aria-hidden="true">✕</span>
                                        <span class="sr-only">{{ __('common.delete') }}</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('tasks.timeline_empty') }}</p>
                    @endforelse
                </div>
            </section>

        </div>

        <aside class="space-y-4">
            {{-- ¿Ya arrancó? ¿Ya terminó? ¿Cuándo?
                 Estas dos fechas se anotan solas desde el bloque 6.13 y no se
                 veían en ninguna pantalla. Un dato que el sistema guarda y nunca
                 enseña es, para quien lo usa, un dato que no existe. --}}
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('tasks.real_dates') }}</h2></div>

                <div class="space-y-2 p-4 text-sm">
                    @php $drift = $task->finishDrift(); @endphp

                    @if ($task->actual_finish)
                        <p class="font-medium text-slate-900">
                            {{ __('tasks.finished_on', ['date' => $task->actual_finish->format('d/m/Y')]) }}
                        </p>

                        {{-- Terminar tarde no se esconde, y terminar antes tampoco:
                             las dos cosas dicen algo de cómo se estimó. --}}
                        @if ($drift !== null)
                            <p class="text-xs {{ $drift > 0 ? 'text-[var(--color-badge-danger-fg)]' : 'text-slate-600' }}">
                                {{ $drift > 0
                                    ? __('tasks.finished_late', ['days' => $drift])
                                    : ($drift < 0
                                        ? __('tasks.finished_early', ['days' => abs($drift)])
                                        : __('tasks.finished_on_time')) }}
                            </p>
                        @endif
                    @elseif ($task->actual_start)
                        <p class="font-medium text-slate-900">
                            {{ __('tasks.in_progress_since', ['date' => $task->actual_start->format('d/m/Y')]) }}
                        </p>
                    @else
                        <p class="text-slate-500">{{ __('tasks.not_started_yet') }}</p>
                    @endif

                    <dl class="space-y-2 border-t border-slate-100 pt-2">
                        @foreach ([
                            'tasks.actual_start' => $task->actual_start,
                            'tasks.actual_finish' => $task->actual_finish,
                        ] as $label => $value)
                            <div class="flex justify-between gap-3">
                                <dt class="text-slate-600">{{ __($label) }}</dt>
                                <dd class="font-medium">{{ $value?->format('d/m/Y') ?? '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>

                    <p class="field-help">{{ __('tasks.real_dates_help') }}</p>
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('tasks.dates') }}</h2></div>
                <dl class="space-y-2 p-4 text-sm">
                    @foreach ([
                        'tasks.start' => $task->early_start,
                        'tasks.finish' => $task->early_finish,
                    ] as $label => $value)
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-600">{{ __($label) }}</dt>
                            <dd class="font-medium">{{ $value?->format('d/m/Y') ?? '—' }}</dd>
                        </div>
                    @endforeach

                    @expert
                        <div class="flex justify-between gap-3 border-t border-slate-100 pt-2">
                            <dt class="text-slate-600">{{ __('tasks.total_float') }}</dt>
                            <dd class="font-medium {{ ($task->total_float_minutes ?? 0) < 0 ? 'text-[var(--color-badge-danger-fg)]' : '' }}">
                                {{ $task->total_float_minutes === null ? '—' : $durations->toHuman((int) $task->total_float_minutes) }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-slate-600">{{ __('tasks.free_float') }}</dt>
                            <dd class="font-medium">
                                {{ $task->free_float_minutes === null ? '—' : $durations->toHuman((int) $task->free_float_minutes) }}
                            </dd>
                        </div>
                    @endexpert
                </dl>
            </section>

            {{-- «Depende de», sin códigos.
                 La sintaxis `12FS+2d` de la vista Lista sigue existiendo y es la
                 forma más rápida de capturar una red. Pero exigirla para poder
                 ligar dos tareas deja fuera a casi todo el mundo, así que aquí
                 se escoge de una lista y se lee como una frase. --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('tasks.depends_on') }}</h2>
                    <span class="text-xs text-slate-500">{{ count($predecessors) }}</span>
                </div>

                <div class="p-4">
                    @forelse ($predecessors as $link)
                        @php
                            $type = $link->type;
                            $lagDays = (int) round(((int) $link->lag_minutes) / max(1, $dayMinutes));
                        @endphp
                        <div class="flex items-start justify-between gap-2 border-b border-slate-100 py-2 last:border-0">
                            <p class="min-w-0 text-sm leading-snug text-slate-700">
                                <span class="text-slate-500">{{ __("tasks.rel_{$type}") }}</span>
                                @if ($link->predecessor)
                                    <a href="{{ route('projects.tasks.show', [$project, $link->predecessor]) }}"
                                       class="font-medium text-slate-900 underline decoration-slate-300 hover:text-brand-700">{{ $link->predecessor->name }}</a>
                                @else
                                    <span class="font-medium text-slate-900">—</span>
                                @endif

                                @if ($lagDays !== 0)
                                    <span class="block text-xs text-slate-500">
                                        {{ $lagDays > 0
                                            ? __('tasks.lag_after', ['days' => $lagDays])
                                            : __('tasks.lag_before', ['days' => abs($lagDays)]) }}
                                    </span>
                                @endif
                            </p>

                            @can('update', $project)
                                <form method="POST" class="shrink-0"
                                      action="{{ route('projects.tasks.dependencies.destroy', [$project, $task, $link]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="rounded px-1 text-slate-400 hover:text-[var(--color-badge-danger-fg)]">
                                        <span aria-hidden="true">✕</span>
                                        <span class="sr-only">{{ __('common.delete') }}</span>
                                    </button>
                                </form>
                            @endcan
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('tasks.depends_on_none') }}</p>
                    @endforelse

                    @can('update', $project)
                        <form method="POST" action="{{ route('projects.tasks.dependencies.store', [$project, $task]) }}"
                              class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                            @csrf

                            <div>
                                <label for="predecessor-field" class="field-label">{{ __('tasks.which_task') }}</label>
                                <select id="predecessor-field" name="predecessor_id" class="field" required>
                                    <option value="">—</option>
                                    @foreach ($candidateTasks as $candidate)
                                        <option value="{{ $candidate->id }}">{{ $candidate->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- En Modo Simple no hay relación que escoger: casi
                                 todas las dependencias reales son «esta empieza
                                 cuando aquella termina», y ofrecer las cuatro es
                                 justo la clase de complejidad que hace que la
                                 gente odie estas herramientas. --}}
                            @expert
                                <div>
                                    <label for="type-field" class="field-label">{{ __('tasks.relationship') }}</label>
                                    <select id="type-field" name="type" class="field">
                                        @foreach (\App\Support\Scheduling\DependencyType::cases() as $type)
                                            <option value="{{ $type->value }}" @selected($type->value === 'FS')>
                                                {{ __("tasks.rel_{$type->value}") }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label for="lag-field" class="field-label">{{ __('tasks.lag_days') }}</label>
                                    <input id="lag-field" type="number" step="0.5" name="lag_days" value="0" class="field">
                                    <p class="field-help">{{ __('tasks.lag_days_help') }}</p>
                                </div>
                            @endexpert

                            @simple
                                <input type="hidden" name="type" value="FS">
                            @endsimple

                            <button type="submit" class="btn btn-secondary btn-sm">{{ __('tasks.add_dependency') }}</button>
                            <p class="field-help">{{ __('tasks.depends_on_help') }}</p>
                        </form>
                    @endcan
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('tasks.blocks') }}</h2></div>
                <div class="p-4 text-sm">
                    @forelse ($successors as $link)
                        <p class="truncate py-0.5">
                            <span class="text-xs text-slate-500">{{ __("tasks.rel_{$link->type}_short") }}</span>
                            @if ($link->successor)
                                <a href="{{ route('projects.tasks.show', [$project, $link->successor]) }}"
                                   class="text-slate-800 underline decoration-slate-300 hover:text-brand-700">{{ $link->successor->name }}</a>
                            @endif
                        </p>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('tasks.blocks_none') }}</p>
                    @endforelse
                </div>
            </section>
        </aside>
    </div>
@endsection

@push('scripts')
    {{-- Cambia el campo de medida segun el recurso elegido: porcentaje de
         jornada para quien trabaja, cantidad para lo que se consume.
         Es **solo comodidad**. El controlador vuelve a decidir cual de los dos
         guarda, asi que sin JavaScript se ven los dos campos y el que no aplica
         se ignora --nunca se guarda un dato sin sentido. --}}
    <script>
        document.querySelectorAll('[data-assign-resource]').forEach((select) => {
            const form = select.closest('form');
            const units = form.querySelector('[data-assign-units]');
            const quantity = form.querySelector('[data-assign-quantity]');
            const unitLabel = form.querySelector('[data-assign-unit-label]');

            const apply = () => {
                const option = select.selectedOptions[0];
                const isMaterial = option?.dataset.material === '1';

                units.hidden = isMaterial;
                quantity.hidden = !isMaterial;
                unitLabel.textContent = isMaterial && option.dataset.unit ? '(' + option.dataset.unit + ')' : '';
            };

            select.addEventListener('change', apply);
            apply();
        });
    </script>
@endpush
