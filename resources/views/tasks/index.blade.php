@extends('layouts.app')

@section('title', __('tasks.title'))
@section('heading', $project->name)

@php
    // El identificador del formulario de grupo. Lo comparten la casilla de cada
    // renglón y el formulario que vive fuera de la tabla; si se escribe a mano
    // en los dos lados, el día que cambie uno las casillas dejan de enviarse
    // sin que nada avise.
    $bulkFormId = 'bulk-outline';
    $anchor = \App\Http\Controllers\TaskController::NEW_TASK_ANCHOR;
@endphp

@section('content')
    @include('tasks._tabs', ['active' => 'list'])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-sm text-slate-600">{{ __('tasks.intro') }}</p>

        @can('update', $project)
            <a href="{{ route('projects.tasks.import', $project) }}" class="btn btn-secondary btn-sm">
                {{ __('import.title') }}
            </a>
        @endcan
    </div>

    @include('tasks._filters')

    @if ($capped)
        <div role="status" class="mb-3 rounded-md bg-slate-100 px-4 py-2 text-xs text-slate-700 ring-1 ring-slate-200">
            {{ __('tasks.showing_capped', ['shown' => $maxRows, 'total' => $visibleCount]) }}
        </div>
    @endif

    @if ($tasks->isEmpty())
        <div class="mb-6 rounded-lg border border-dashed border-slate-300 bg-surface p-6 text-center text-sm text-slate-600">
            {{ __('tasks.empty') }}
        </div>
    @else
        <div class="mb-6 overflow-x-auto rounded-lg bg-surface ring-1 ring-slate-200">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <caption class="sr-only">{{ __('tasks.title') }}</caption>
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-600">
                    <tr>
                        @can('update', $project)
                            <th scope="col" class="px-2 py-2">
                                {{-- Marcar todas de una vez. Va oculta hasta que
                                     JavaScript la enciende: sin él sería una
                                     casilla que no hace nada, y eso es peor
                                     que no tenerla. --}}
                                <input type="checkbox" data-bulk-all hidden
                                       aria-label="{{ __('tasks.bulk_select_all') }}"
                                       class="h-3.5 w-3.5 rounded border-slate-300 text-brand-700 focus:ring-2 focus:ring-hud-500">
                            </th>
                        @endcan

                        <th scope="col" class="px-2 py-2 text-right">
                            {{ __('tasks.wbs') }}
                            <x-help-term term="wbs" />
                        </th>
                        <th scope="col" class="px-3 py-2">{{ __('tasks.name') }}</th>
                        <th scope="col" class="px-2 py-2">{{ __('tasks.duration') }}</th>
                        {{-- La columna que nadie entiende sin que se la expliquen:
                             un número suelto y, a veces, dos letras. --}}
                        <th scope="col" class="px-2 py-2">
                            {{ __('tasks.predecessors') }}
                            <x-help-term term="predecessor" />
                        </th>
                        <th scope="col" class="px-3 py-2">{{ __('tasks.start') }}</th>
                        <th scope="col" class="px-3 py-2">{{ __('tasks.finish') }}</th>
                        @expert
                            <th scope="col" class="px-2 py-2">
                                {{ __('tasks.float') }}
                                <x-help-term term="float" />
                            </th>
                        @endexpert
                        <th scope="col" class="px-3 py-2">{{ __('tasks.owner') }}</th>
                        {{-- El avance va en la lista, no solo en el detalle.
                             «¿Ya terminamos?» es la pregunta que más se le hace a
                             esta pantalla, y hasta ahora había que abrir tarea por
                             tarea para contestarla. --}}
                        <th scope="col" class="px-2 py-2">
                            {{ __('tasks.progress') }}
                            <x-help-term term="progress" />
                        </th>
                        <th scope="col" class="px-2 py-2"><span class="sr-only">{{ __('common.actions') }}</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($tasks as $index => $task)
                        @include('tasks._row', ['task' => $task, 'row' => $index + 1])
                    @endforeach
                </tbody>
            </table>
        </div>

        @stack('outside-table')

        {{-- Mover varias al mismo paquete de un golpe.
             Cada flechita es un recálculo del proyecto y una recarga de esta
             pantalla —que trae un formulario por renglón—, así que acomodar las
             diez tareas de una semana eran quince recargas. Marcar y escoger
             destino es una.
             Vive fuera de la tabla por la misma razón que los formularios de
             cada renglón: el navegador no deja un `<form>` entre `<tr>`. Las
             casillas lo alcanzan con el atributo `form`.
             Funciona completo sin JavaScript: son casillas y un `<select>`. --}}
        @can('update', $project)
            <form method="POST" action="{{ route('projects.tasks.reparent', $project) }}"
                  id="{{ $bulkFormId }}"
                  class="mb-3 rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                @csrf

                <div class="flex flex-wrap items-end gap-3">
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-slate-900">{{ __('tasks.bulk_title') }}</h2>
                        <p class="mt-1 max-w-xl text-xs text-slate-500">{{ __('tasks.bulk_hint') }}</p>
                    </div>

                    <div class="ml-auto flex flex-wrap items-end gap-2">
                        <div>
                            <label for="bulk-parent-field" class="block text-xs font-medium text-slate-700">
                                {{ __('tasks.bulk_parent') }}
                            </label>
                            {{-- Los destinos son los renglones que están a la
                                 vista, no el plan entero. Si el filtro esconde
                                 una tarea, ofrecerla aquí haría que la pantalla
                                 dijera dos cosas distintas del mismo filtro —y
                                 nombraría en un desplegable justo lo que se
                                 pidió esconder.
                                 A propósito no se descartan los renglones que
                                 no son paquete: cualquier tarea se vuelve uno
                                 en el momento en que algo se le mete adentro,
                                 que es justo lo que hace este formulario.
                                 La sangría va con espacios duros: el HTML
                                 colapsa los normales y el árbol se veía plano,
                                 sin manera de saber qué cuelga de qué. --}}
                            <select id="bulk-parent-field" name="parent_id"
                                    class="mt-1 block max-w-xs rounded-md border-slate-300 text-sm shadow-sm focus:border-hud-500 focus:ring-2 focus:ring-hud-500">
                                <option value="">{{ __('tasks.bulk_top_level') }}</option>
                                @foreach ($tasks as $candidate)
                                    {{-- El relleno va sin escapar porque es texto
                                         fijo del programa. El nombre de la tarea
                                         nunca: eso lo escribe un usuario y sale
                                         por `{{ }}`, como todo lo demás. --}}
                                    <option value="{{ $candidate->id }}">{!! str_repeat('&nbsp;&nbsp;&nbsp;', (int) ($candidate->outline_depth ?? 0)) !!}{{ $candidate->wbs_code ? $candidate->wbs_code.' · ' : '' }}{{ $candidate->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <button type="submit" class="btn btn-secondary btn-sm">
                            {{ __('tasks.bulk_apply') }}
                        </button>
                    </div>
                </div>

                {{-- Cuántas van marcadas. Lo llena JavaScript; vacío no estorba.
                     El texto viaja en el atributo y no dentro del `.js`: ahí no
                     habría cómo traducirlo, y esta pantalla existe en dos
                     idiomas. --}}
                <p data-bulk-count role="status"
                   data-template="{{ __('tasks.bulk_selected_count') }}"
                   class="mt-2 text-xs font-medium text-brand-700"></p>
            </form>
        @endcan

        <p class="mb-3 text-xs text-slate-500">{{ __('tasks.detail_hint_row') }}</p>

        {{-- Lo que la tabla dice en símbolos, dicho en palabras.
             El Gantt siempre tuvo su leyenda; la Lista no, y es la pantalla donde
             la gente entra primero. Quien nunca ha usado la herramienta veía
             flechas y rombos sin nada que los tradujera.
             Va plegada: quien ya sabe leerla no tiene por qué cargar con ella. --}}
        <details class="mb-6 rounded-lg bg-surface p-4 ring-1 ring-slate-200">
            <summary class="cursor-pointer text-sm font-medium text-slate-800">{{ __('tasks.legend') }}</summary>

            <div class="mt-3 grid gap-x-6 gap-y-2 text-xs text-slate-600 sm:grid-cols-2">
                @foreach ([
                    ['▸', __('tasks.legend_summary')],
                    ['◆', __('tasks.legend_milestone')],
                    [__('tasks.critical'), __('tasks.legend_critical')],
                    ['⋯', __('tasks.legend_detail')],
                    ['✎', __('tasks.legend_notes')],
                    ['+', __('tasks.legend_subtask')],
                    ['☐', __('tasks.legend_bulk')],
                    ['◀ ▶', __('tasks.legend_indent')],
                    ['▲ ▼', __('tasks.legend_move')],
                    ['✕', __('tasks.legend_delete')],
                ] as [$glyph, $meaning])
                    <p class="flex gap-2">
                        <span aria-hidden="true" class="w-14 shrink-0 text-slate-500">{{ $glyph }}</span>
                        <span>{{ $meaning }}</span>
                    </p>
                @endforeach
            </div>

            <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-600">{{ __('tasks.predecessors_help') }}</p>
            <p class="mt-1 text-xs text-slate-600">{{ __('tasks.expression_help') }}</p>
        </details>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Alta rápida: un renglón, siempre visible al final de la lista. Abrir
             una pantalla aparte por cada tarea es lo que hace que capturar un
             plan de sesenta tareas se sienta interminable. --}}
        <form method="POST" action="{{ route('projects.tasks.store', $project) }}"
              id="{{ $anchor }}"
              class="space-y-4 rounded-lg bg-surface p-5 ring-1 ring-slate-200 lg:col-span-2">
            @csrf

            {{-- El paquete dentro del que nace la tarea.
                 `store()` siempre aceptó `parent_id`; lo que faltaba era una
                 forma de decirlo desde aquí. Sin esto, la única manera de armar
                 un nivel era crear plano y después indentar renglón por
                 renglón, y de ahí salía casi todo el trabajo a mano. --}}
            @if ($parent)
                <input type="hidden" name="parent_id" value="{{ $parent->id }}">

                <div>
                    <h2 class="text-sm font-semibold text-slate-900">
                        {{ __('tasks.new_subtask_of', ['name' => $parent->name]) }}
                    </h2>
                    <p class="mt-1 text-xs text-slate-500">{{ __('tasks.new_subtask_hint') }}</p>
                    <a href="{{ route('projects.tasks.index', $project) }}#{{ $anchor }}"
                       class="mt-1 inline-block text-xs font-medium text-brand-700 underline hover:no-underline">
                        {{ __('tasks.new_subtask_exit') }}
                    </a>
                </div>
            @else
                <h2 class="text-sm font-semibold text-slate-900">{{ __('tasks.new_task') }}</h2>
            @endif

            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    {{-- El foco entra solo cuando se viene de un «+»: capturar
                         cinco subtareas seguidas es escribir, Enter, escribir.
                         Al abrir la lista sin paquete no se roba el foco, que
                         ahí estorbaría a quien solo viene a leer el plan. --}}
                    <x-form-field name="name" :label="__('tasks.name')" required
                                  :autofocus="$parent !== null" />
                </div>

                <div class="sm:col-span-1">
                    <x-form-field name="duration" :label="__('tasks.duration')" value="1d" />
                </div>

                <div class="sm:col-span-2">
                    <div class="space-y-1">
                        <label for="owner-field" class="block text-sm font-medium text-slate-700">{{ __('tasks.owner') }}</label>
                        <select id="owner-field" name="owner_id"
                                class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-hud-500 focus:ring-2 focus:ring-hud-500">
                            <option value="">—</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((int) old('owner_id') === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="task-start-field" class="field-label">{{ __('tasks.requested_start') }}</label>
                    <input id="task-start-field" type="date" name="requested_start" class="field"
                           value="{{ old('requested_start') }}">
                    <p class="field-help">{{ __('tasks.requested_start_help') }}</p>
                </div>
                <div>
                    <label for="task-deadline-field" class="field-label">{{ __('tasks.deadline') }}</label>
                    <input id="task-deadline-field" type="date" name="deadline" class="field"
                           value="{{ old('deadline') }}" min="{{ old('requested_start') }}">
                    @error('deadline') <p role="alert" class="mt-1 text-xs text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
                    <p class="field-help">{{ __('tasks.deadline_help') }}</p>
                </div>
            </div>

            <p class="text-xs text-slate-500">{{ __('tasks.duration_help') }}</p>

            <button type="submit"
                    class="btn btn-primary">
                {{ __('tasks.add') }}
            </button>
        </form>

        <aside class="space-y-4">
            <div class="rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('tasks.project_start') }}</dt>
                        <dd class="font-medium">{{ $project->planned_start?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('tasks.project_finish') }}</dt>
                        <dd class="font-medium">{{ $lastRun?->project_finish?->format('d/m/Y') ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-slate-600">{{ __('tasks.critical_path') }}</dt>
                        <dd class="font-medium">{{ $lastRun?->critical_task_count ?? 0 }}</dd>
                    </div>
                </dl>

                <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    @if ($lastRun)
                        {{ __('tasks.last_run', [
                            'when' => $lastRun->created_at?->diffForHumans() ?? '—',
                            'count' => $lastRun->task_count,
                            'ms' => number_format((float) $lastRun->elapsed_ms, 0),
                        ]) }}
                    @else
                        {{ __('tasks.never_calculated') }}
                    @endif
                </p>
            </div>

            <p class="rounded-md bg-slate-50 px-4 py-3 text-xs text-slate-600 ring-1 ring-slate-200">
                {{ __('tasks.critical_explained') }}
            </p>

            <form method="POST" action="{{ route('projects.tasks.recalculate', $project) }}">
                @csrf
                <button type="submit"
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-hud-500 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-hud-500">
                    {{ __('tasks.recalculate') }}
                </button>
            </form>
        </aside>
    </div>
@endsection

@push('scripts')
    <script>
        (() => {
            const start = document.getElementById('task-start-field');
            const deadline = document.getElementById('task-deadline-field');

            if (!start || !deadline) return;

            const syncDeadline = () => {
                deadline.min = start.value;
                if (deadline.value && start.value && deadline.value < start.value) {
                    deadline.value = start.value;
                }
            };

            start.addEventListener('change', syncDeadline);
            syncDeadline();
        })();
    </script>
@endpush
