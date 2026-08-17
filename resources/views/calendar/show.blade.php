@extends('layouts.app')

@section('title', __('calendar.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'calendar'])

    @include('tasks._filters', ['filterRoute' => 'projects.calendar'])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
            <a href="{{ route('projects.calendar', ['project' => $project, 'month' => $previousMonth]) }}"
               class="btn btn-secondary btn-sm" aria-label="{{ __('calendar.previous_month') }}">
                <span aria-hidden="true">←</span>
            </a>

            <h2 class="min-w-[10rem] text-center text-sm font-semibold capitalize text-slate-900">
                {{ $month->format('F Y') }}
            </h2>

            <a href="{{ route('projects.calendar', ['project' => $project, 'month' => $nextMonth]) }}"
               class="btn btn-secondary btn-sm" aria-label="{{ __('calendar.next_month') }}">
                <span aria-hidden="true">→</span>
            </a>
        </div>

        <p class="text-xs text-slate-600">{{ __('calendar.intro') }}</p>
    </div>

    @if ($tasks->isEmpty())
        <div class="card p-8 text-center text-sm text-slate-600">{{ __('calendar.empty') }}</div>
    @else
        <div class="card overflow-hidden">
            <table class="w-full table-fixed border-collapse">
                <caption class="sr-only">{{ __('calendar.caption', ['month' => $month->format('F Y'), 'project' => $project->name]) }}</caption>
                <thead>
                    <tr>
                        @foreach (['calendar.mon', 'calendar.tue', 'calendar.wed', 'calendar.thu', 'calendar.fri', 'calendar.sat', 'calendar.sun'] as $day)
                            <th scope="col" class="border-b border-slate-200 bg-slate-50 px-2 py-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-600">
                                {{ __($day) }}
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($weeks as $week)
                        <tr>
                            @foreach ($week as $day)
                                <td class="h-24 border-b border-r border-slate-100 align-top
                                           {{ $day['inMonth'] ? '' : 'bg-slate-50/60' }}
                                           {{ $day['working'] ? '' : 'bg-slate-100/70' }}">
                                    <div class="flex h-full flex-col gap-0.5 p-1">
                                        <div class="flex items-center justify-between">
                                            <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[11px]
                                                         {{ $day['today'] ? 'bg-brand-700 font-bold text-white' : ($day['inMonth'] ? 'text-slate-700' : 'text-slate-400') }}">
                                                {{ $day['date']->format('j') }}
                                            </span>

                                            @if (count($day['tasks']) > 3)
                                                <span class="text-[10px] text-slate-400">+{{ count($day['tasks']) - 3 }}</span>
                                            @endif
                                        </div>

                                        @foreach (array_slice($day['tasks'], 0, 3) as $entry)
                                            @php $task = $entry['task']; @endphp
                                            {{-- Las puntas redondeadas dicen si la tarea empieza o termina ese
                                                 día; una barra recta significa que solo pasa por ahí. --}}
                                            <a href="{{ route('projects.tasks.show', [$project, $task]) }}"
                                               title="{{ $task->name }}"
                                               class="block truncate px-1 py-0.5 text-[10px] font-medium leading-tight text-white
                                                      {{ $task->is_critical ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-600 hover:bg-brand-700' }}
                                                      {{ $entry['starts'] ? 'rounded-l' : '' }} {{ $entry['ends'] ? 'rounded-r' : '' }}">
                                                {{ $entry['starts'] ? $task->name : '' }}
                                                @unless ($entry['starts'])
                                                    <span class="sr-only">{{ $task->name }}</span>&nbsp;
                                                @endunless
                                            </a>
                                        @endforeach
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-3 flex flex-wrap gap-4 text-xs text-slate-600">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-6 rounded-sm bg-brand-600" aria-hidden="true"></span>
                {{ __('gantt.legend_task') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-6 rounded-sm bg-red-600" aria-hidden="true"></span>
                {{ __('tasks.critical') }}
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-6 rounded-sm bg-slate-200 ring-1 ring-slate-300" aria-hidden="true"></span>
                {{ __('calendar.non_working') }}
            </span>
        </div>
    @endif
@endsection
