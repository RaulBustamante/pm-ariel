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

            {{-- Historial: la bitácora de auditoría, mostrada donde se pregunta --}}
            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __('tasks.history') }}</h2>
                </div>

                <div class="p-4">
                    @forelse ($history as $entry)
                        <div class="flex gap-3 border-b border-slate-100 py-2 text-xs last:border-0">
                            <span class="w-28 shrink-0 text-slate-500">
                                {{ $entry->created_at?->format('d/m/y H:i') }}
                            </span>
                            <div class="min-w-0">
                                <p class="text-slate-800">
                                    <span class="font-medium">{{ $entry->user?->name ?? __('audit.system') }}</span>
                                    · {{ __("audit.events.{$entry->event}") }}
                                </p>
                                @if ($entry->new_values)
                                    <p class="mt-0.5 truncate text-slate-500">
                                        {{ implode(', ', array_keys($entry->new_values)) }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500">{{ __('tasks.no_history') }}</p>
                    @endforelse
                </div>
            </section>
        </div>

        <aside class="space-y-4">
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

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('tasks.predecessors') }}</h2></div>
                <div class="p-4 text-sm">
                    @forelse ($predecessors as $link)
                        <p class="truncate py-0.5 text-slate-700">
                            <span class="font-mono text-xs text-slate-400">{{ $link->type }}</span>
                            {{ $link->predecessor?->name }}
                        </p>
                    @empty
                        <p class="text-xs text-slate-500">—</p>
                    @endforelse
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('tasks.successors') }}</h2></div>
                <div class="p-4 text-sm">
                    @forelse ($successors as $link)
                        <p class="truncate py-0.5 text-slate-700">
                            <span class="font-mono text-xs text-slate-400">{{ $link->type }}</span>
                            {{ $link->successor?->name }}
                        </p>
                    @empty
                        <p class="text-xs text-slate-500">—</p>
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
