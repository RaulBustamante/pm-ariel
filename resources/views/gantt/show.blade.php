@extends('layouts.app')

@section('title', __('tasks.gantt_view'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'gantt'])

    @include('tasks._filters', ['filterRoute' => 'projects.gantt'])

    <div class="mb-4 flex flex-wrap items-center gap-2">
        <span class="text-sm text-slate-600">{{ __('gantt.zoom') }}:</span>
        @foreach ([
            \App\Support\Scheduling\GanttLayout::ZOOM_DAY => __('gantt.zoom_day'),
            \App\Support\Scheduling\GanttLayout::ZOOM_WEEK => __('gantt.zoom_week'),
            \App\Support\Scheduling\GanttLayout::ZOOM_MONTH => __('gantt.zoom_month'),
        ] as $value => $label)
            <a href="{{ route('projects.gantt', ['project' => $project, 'zoom' => $value]) }}"
               @if ($zoom === $value) aria-current="true" @endif
               class="rounded-md px-3 py-1.5 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-blue-600
                      {{ $zoom === $value ? 'bg-blue-700 text-white' : 'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>

    @if ($tasks->isEmpty())
        <div class="rounded-lg border border-dashed border-slate-300 bg-white p-8 text-center text-sm text-slate-600">
            {{ __('gantt.empty') }}
        </div>
    @else
        <div class="rounded-lg bg-white ring-1 ring-slate-200">
            <div class="flex">
                {{-- Los nombres van en una tabla aparte y no dentro del SVG: así
                     se pueden leer, copiar y buscar con Ctrl+F, que dentro de un
                     dibujo no se puede. --}}
                {{-- Cada renglón es un enlace enfocable con Tab. Un Gantt que solo
                     se puede leer con el ratón deja fuera a quien navega por
                     teclado y a quien usa lector de pantalla — y el dibujo, por
                     definición, no se puede tabular. --}}
                <div class="w-64 shrink-0 border-r border-slate-200">
                    <div class="h-[42px] border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        {{ __('tasks.name') }}
                    </div>
                    @foreach ($tasks as $task)
                        <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                           class="flex h-[26px] items-center gap-1 truncate border-b border-slate-50 px-3 text-xs transition-colors hover:bg-brand-50
                                  {{ $task->is_summary ? 'font-semibold text-slate-900' : 'text-slate-700' }}"
                           style="padding-left: {{ 0.75 + ($task->outline_depth ?? 0) * 0.75 }}rem"
                           title="{{ $task->name }} · {{ $task->early_start?->format('d/m/y') }} → {{ $task->early_finish?->format('d/m/y') }}">
                            @if ($task->is_critical && ! $task->is_summary)
                                <span aria-hidden="true" class="text-red-600">●</span>
                            @endif
                            <span class="truncate">{{ $task->name }}</span>
                            {{-- Lo que el dibujo no puede decir, se dice aquí. --}}
                            <span class="sr-only">
                                {{ __('gantt.row_summary', [
                                    'from' => $task->early_start?->format('d/m/Y') ?? '',
                                    'to' => $task->early_finish?->format('d/m/Y') ?? '',
                                    'state' => $task->is_critical ? __('tasks.critical') : '',
                                ]) }}
                            </span>
                        </a>
                    @endforeach
                </div>

                <div class="overflow-x-auto">
                    @include('gantt._chart')
                </div>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-4 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-6 rounded-sm bg-blue-600" aria-hidden="true"></span>
                {{ __('gantt.legend_task') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-6 rounded-sm bg-red-600" aria-hidden="true"></span>
                {{ __('tasks.critical') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-6 bg-slate-700" aria-hidden="true"></span>
                {{ __('tasks.summary') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span aria-hidden="true" class="text-blue-700">◆</span>
                {{ __('tasks.milestone') }}
            </span>

            @if ($baseline)
                <span class="inline-flex items-center gap-1.5">
                    <span class="inline-block h-1 w-6 rounded-sm bg-slate-400" aria-hidden="true"></span>
                    <a href="{{ route('projects.baselines.compare', [$project, $baseline]) }}"
                       class="rounded text-brand-700 underline hover:text-brand-800">
                        {{ $baseline->name }}
                    </a>
                </span>
            @endif
        </div>

        @can('update', $project)
            {{-- Formulario normal: al soltar una barra se envia como cualquier
                 otro, sin peticiones a mano ni estado paralelo en el navegador. --}}
            <form method="POST" action="{{ route('projects.gantt.move', $project) }}" class="hidden"
                  data-gantt-move-form data-confirm="{{ __('gantt.drag_confirm', ['days' => ':days']) }}">
                @csrf
                <input type="hidden" name="task" value="">
                <input type="hidden" name="days" value="">
            </form>

            <p role="status" aria-live="polite" class="sr-only" data-gantt-live></p>
        @endcan

        <p class="mt-2 max-w-3xl text-xs text-slate-500">{{ __('gantt.reading_help') }}</p>
        <p class="mt-1 max-w-3xl text-xs text-slate-500">{{ __('gantt.keyboard_help') }}</p>
        @can('update', $project)
            <p class="mt-1 max-w-3xl text-xs text-slate-500">{{ __('gantt.drag_help') }}</p>
        @endcan
    @endif
@endsection
