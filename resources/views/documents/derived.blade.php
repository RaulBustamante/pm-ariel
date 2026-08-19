@extends('layouts.app')

@section('title', __("documents.doc_{$code}"))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900">{{ __("documents.doc_{$code}") }}</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __("derived.help_{$code}") }}</p>
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('projects.documents.derived.pdf', [$project, $code]) }}" class="btn btn-secondary btn-sm">
                {{ __('derived.download') }}
            </a>
            <a href="{{ route('projects.documents', $project) }}" class="btn btn-ghost btn-sm">
                {{ __('documents.title') }}
            </a>
        </div>
    </div>

    <section class="card hud-in">
        <div class="card-header">
            <h2 class="card-title">{{ __("documents.doc_{$code}") }}</h2>
            <span class="text-xs text-slate-500">{{ count($rows) }}</span>
        </div>

        @if ($rows === [])
            {{-- Un derivado vacío no se arregla capturándolo aquí: se arregla
                 capturando el dato en su pantalla. Se dice cuál, en vez de
                 ofrecer teclearlo por segunda vez en otro lado. --}}
            <div class="p-8 text-center">
                <p class="text-sm text-slate-600">{{ __('derived.empty') }}</p>
                <p class="mx-auto mt-1 max-w-xl text-xs leading-relaxed text-slate-500">
                    {{ __("derived.empty_{$code}") }}
                </p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <caption class="sr-only">{{ __("documents.doc_{$code}") }}</caption>
                    <thead>
                        <tr>
                            @foreach ($columns as $column)
                                <th scope="col" class="{{ $derived->isNumeric($code, $column) ? 'text-right' : '' }}">
                                    {{ __("derived.col_{$column}") }}
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            {{-- Los paquetes se distinguen de las tareas: un
                                 diccionario de la EDT donde el encabezado se ve
                                 igual que su contenido no se lee como un árbol. --}}
                            <tr class="{{ ($row['is_summary'] ?? false) ? 'bg-slate-50/60 font-medium' : '' }}">
                                @foreach ($columns as $column)
                                    @php $value = $row[$column] ?? null; @endphp
                                    <td class="{{ $derived->isNumeric($code, $column) ? 'text-right tabular' : '' }}
                                               {{ ($column === 'variance' && ($row['is_over'] ?? false))
                                                  || ($column === 'level' && ($row['is_high'] ?? false))
                                                    ? 'font-medium text-[var(--color-badge-danger-fg)]' : '' }}">
                                        @if ($value === null || $value === '')
                                            <span class="text-slate-400">—</span>
                                        @else
                                            {{-- Se conservan los saltos de línea que capturó
                                                 alguien: una lista escrita renglón por renglón
                                                 saldría como párrafo corrido. --}}
                                            <span class="whitespace-pre-line">{{ $value }}</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
