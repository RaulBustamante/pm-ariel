@extends('layouts.app')

@section('title', __('kanban.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'kanban'])

    @include('tasks._filters', ['filterRoute' => 'projects.kanban'])

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <p class="max-w-2xl text-xs text-slate-600">{{ __('kanban.intro') }}</p>

        <div class="flex items-center gap-1">
            <span class="text-xs text-slate-500">{{ __('kanban.group_by') }}:</span>
            @foreach (['none' => __('kanban.no_grouping'), 'package' => __('kanban.by_package')] as $value => $label)
                <a href="{{ route('projects.kanban', ['project' => $project, 'lane' => $value]) }}"
                   @if ($swimlane === $value) aria-current="true" @endif
                   class="rounded-md px-2.5 py-1 text-xs font-medium
                          {{ $swimlane === $value ? 'bg-brand-700 text-white' : 'bg-surface text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>

    @if ($total === 0)
        <div class="card p-8 text-center text-sm text-slate-600">{{ __('kanban.empty') }}</div>
    @elseif ($lanes !== null)
        <div class="space-y-5">
            @foreach ($lanes as $lane)
                <section class="card overflow-hidden">
                    <div class="card-header">
                        <h2 class="card-title">{{ $lane['name'] }}</h2>
                        <span class="badge badge-neutral">
                            {{ collect($lane['columns'])->sum(fn ($c) => $c->count()) }}
                        </span>
                    </div>
                    <div class="p-3">
                        @include('kanban._board', ['columns' => $lane['columns'], 'compact' => true])
                    </div>
                </section>
            @endforeach
        </div>
    @else
        @include('kanban._board', ['columns' => $columns, 'compact' => false])
    @endif

    @can('update', $project)
        {{-- El arrastre suelta aquí y envía un formulario normal, como el del
             Gantt: sin peticiones a mano, sin estado paralelo en el navegador y
             sin una pantalla que se quede diciendo algo distinto de lo guardado.

             La dirección se arma en el JavaScript con el identificador de la
             tarea, porque una sola ruta sirve para todas las tarjetas. --}}
        <form method="POST" class="hidden" data-kanban-move-form
              data-action-template="{{ route('projects.kanban.move', [$project, '__TASK__']) }}">
            @csrf
            <input type="hidden" name="column" value="">
        </form>

        {{-- Lo que el arrastre hace se anuncia, porque arrastrar no dice nada a
             quien no ve la pantalla moverse. --}}
        <p class="sr-only" role="status" data-kanban-live></p>

        <p class="mt-3 text-[11px] text-slate-500">{{ __('kanban.drag_hint') }}</p>
    @endcan
@endsection
