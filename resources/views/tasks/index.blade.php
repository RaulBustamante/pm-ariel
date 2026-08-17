@extends('layouts.app')

@section('title', __('tasks.title'))
@section('heading', $project->name)

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
                        <th scope="col" class="px-2 py-2 text-right">{{ __('tasks.row') }}</th>
                        <th scope="col" class="px-3 py-2">{{ __('tasks.name') }}</th>
                        <th scope="col" class="px-2 py-2">{{ __('tasks.duration') }}</th>
                        <th scope="col" class="px-2 py-2">{{ __('tasks.predecessors') }}</th>
                        <th scope="col" class="px-3 py-2">{{ __('tasks.start') }}</th>
                        <th scope="col" class="px-3 py-2">{{ __('tasks.finish') }}</th>
                        @expert
                            <th scope="col" class="px-2 py-2">
                                {{ __('tasks.float') }}
                                <x-help-term term="risk" :definition="__('tasks.float_explained')" />
                            </th>
                        @endexpert
                        <th scope="col" class="px-3 py-2">{{ __('tasks.owner') }}</th>
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
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        {{-- Alta rápida: un renglón, siempre visible al final de la lista. Abrir
             una pantalla aparte por cada tarea es lo que hace que capturar un
             plan de sesenta tareas se sienta interminable. --}}
        <form method="POST" action="{{ route('projects.tasks.store', $project) }}"
              class="space-y-4 rounded-lg bg-surface p-5 ring-1 ring-slate-200 lg:col-span-2">
            @csrf

            <h2 class="text-sm font-semibold text-slate-900">{{ __('tasks.new_task') }}</h2>

            <div class="grid gap-4 sm:grid-cols-6">
                <div class="sm:col-span-3">
                    <x-form-field name="name" :label="__('tasks.name')" required />
                </div>

                <div class="sm:col-span-1">
                    <x-form-field name="duration" :label="__('tasks.duration')" value="1d" />
                </div>

                <div class="sm:col-span-2">
                    <div class="space-y-1">
                        <label for="owner-field" class="block text-sm font-medium text-slate-700">{{ __('tasks.owner') }}</label>
                        <select id="owner-field" name="owner_id"
                                class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                            <option value="">—</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((int) old('owner_id') === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>

            <p class="text-xs text-slate-500">{{ __('tasks.duration_help') }}</p>

            <button type="submit"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
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
                        class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    {{ __('tasks.recalculate') }}
                </button>
            </form>
        </aside>
    </div>
@endsection
