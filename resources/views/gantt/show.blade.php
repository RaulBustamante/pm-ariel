@extends('layouts.app')

@section('title', __('tasks.gantt_view'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'gantt'])

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
                <div class="w-64 shrink-0 border-r border-slate-200">
                    <div class="h-[42px] border-b border-slate-200 bg-slate-50 px-3 py-2 text-xs font-semibold uppercase tracking-wide text-slate-600">
                        {{ __('tasks.name') }}
                    </div>
                    @foreach ($tasks as $task)
                        <div class="flex h-[26px] items-center gap-1 truncate border-b border-slate-50 px-3 text-xs
                                    {{ $task->is_summary ? 'font-semibold text-slate-900' : 'text-slate-700' }}"
                             style="padding-left: {{ 0.75 + ($task->outline_depth ?? 0) * 0.75 }}rem"
                             title="{{ $task->name }}">
                            @if ($task->is_critical && ! $task->is_summary)
                                <span aria-hidden="true" class="text-red-600">●</span>
                            @endif
                            {{ $task->name }}
                        </div>
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

        <p class="mt-2 max-w-3xl text-xs text-slate-500">{{ __('gantt.reading_help') }}</p>
    @endif
@endsection
