@extends('layouts.app')

@section('title', __('documents.title'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    {{-- La cobertura, arriba de todo.
         Es el número que contesta «¿qué tan documentado está este proyecto según
         el PMI?», y es la razón de ser de esta pantalla: sin él, la lista de
         setenta renglones es un catálogo; con él, es un estado. --}}
    <section class="card card-hud hud-in mb-4 p-4">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="stat-label">{{ __('documents.coverage') }}</p>
                <p class="stat-value">
                    {{ $coverage['ready'] }}<span class="stat-unit"> / {{ $coverage['total'] }}</span>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="badge badge-ok">{{ $coverage['ready'] }} · {{ __('documents.state_ready') }}</span>
                <span class="badge badge-warn">{{ $coverage['partial'] }} · {{ __('documents.state_partial') }}</span>
                <span class="badge badge-neutral">{{ $coverage['planned'] }} · {{ __('documents.state_planned') }}</span>
            </div>
        </div>

        <div class="meter mt-3 h-2">
            <div class="meter-fill" style="width: {{ $coverage['percent'] }}%"></div>
        </div>

        <p class="field-help mt-2 max-w-3xl">
            {{ __('documents.coverage_help', ['ready' => $coverage['ready'], 'total' => $coverage['total']]) }}
        </p>
    </section>

    {{-- ------------------------------------------------------------------
         7.23 · Qué debería existir ya, según en qué fase va este proyecto

         La cobertura de arriba dice cuántos de los setenta se pueden emitir, y
         desde que el catálogo está completo eso es siempre setenta. La pregunta
         que queda sin contestar es la del proyecto: **de los que ya se le
         debieron haber emitido, cuáles faltan**.

         La fase se deduce del avance y no se captura. Un campo de fase es algo
         que alguien tiene que acordarse de mover, y en cuanto se queda atrás el
         aviso miente en la dirección más peligrosa: diciendo que todo está bien.
         ------------------------------------------------------------------ --}}
    <section class="card hud-in mb-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="card-title">{{ __('documents.phase_title') }}</h2>
                <p class="mt-1 text-xs text-slate-600">
                    {{ __('documents.phase_now', [
                        'phase' => __("documents.group_{$phase['phase']}"),
                        'progress' => $phase['progress'],
                    ]) }}
                </p>
            </div>

            <span class="badge shrink-0 {{ $phase['missing'] > 0 ? 'badge-warn' : 'badge-ok' }}">
                {{ $phase['missing'] > 0
                    ? __('documents.phase_missing', ['count' => $phase['missing']])
                    : __('documents.phase_complete') }}
            </span>
        </div>

        <div class="mt-3 flex flex-wrap gap-1.5">
            @foreach ($phase['expected'] as $item)
                {{-- Emitido, pendiente-y-ya-tocaba, o todavía no le toca. Los
                     tres se distinguen: marcar como faltante algo de una fase
                     que el proyecto no ha alcanzado enciende el aviso desde el
                     primer día, y un aviso siempre encendido deja de leerse. --}}
                @php
                    $due = ! $item['issued']
                        && array_search($item['group'], \App\Support\Documents\DocumentCatalogue::GROUPS, true)
                           <= array_search($phase['phase'], \App\Support\Documents\DocumentCatalogue::GROUPS, true);
                @endphp

                <span class="badge {{ $item['issued'] ? 'badge-ok' : ($due ? 'badge-warn' : 'badge-neutral') }}"
                      title="{{ __("documents.group_{$item['group']}") }}">
                    <span aria-hidden="true">{{ $item['issued'] ? '✓' : ($due ? '!' : '·') }}</span>
                    {{ __("documents.doc_{$item['code']}") }}
                </span>
            @endforeach
        </div>

        <p class="field-help mt-3 max-w-3xl">{{ __('documents.phase_help') }}</p>
    </section>

    <p class="mb-4 max-w-3xl text-sm text-slate-600">{{ __('documents.intro') }}</p>

    @php
        $badges = [
            'ready' => 'badge-ok',
            'partial' => 'badge-warn',
            'planned' => 'badge-neutral',
        ];
    @endphp

    <div class="space-y-4">
        @foreach ($groups as $group => $documents)
            @continue($documents === [])

            <section class="card">
                <div class="card-header">
                    <h2 class="card-title">{{ __("documents.group_{$group}") }}</h2>
                    <span class="text-xs text-slate-500">{{ count($documents) }}</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="data-table">
                        <caption class="sr-only">{{ __("documents.group_{$group}") }}</caption>
                        <thead>
                            <tr>
                                <th scope="col">{{ __('documents.title') }}</th>
                                <th scope="col" class="w-40">{{ __('common.status') }}</th>
                                <th scope="col" class="w-44">{{ __('documents.blocked_by') }}</th>
                                <th scope="col" class="w-24"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($documents as $document)
                                <tr>
                                    <td>
                                        <span class="font-medium text-slate-900">{{ $document['name'] }}</span>
                                        {{-- La especie explica **cómo** se produce, que es lo que
                                             dice si hay que sentarse a escribirlo o si sale solo. --}}
                                        <span class="ml-2 text-[11px] text-slate-500">
                                            {{ __("documents.kind_{$document['kind']}") }}
                                        </span>
                                    </td>

                                    <td>
                                        <span class="badge {{ $badges[$document['state']] }}">
                                            {{ __("documents.state_{$document['state']}") }}
                                        </span>
                                    </td>

                                    <td class="text-xs text-slate-600">
                                        @if ($document['source'])
                                            {{ __("documents.source_{$document['source']}") }}
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endif
                                    </td>

                                    <td>
                                        @if ($document['url'])
                                            <a href="{{ $document['url'] }}" class="btn btn-secondary btn-sm">
                                                {{ __('documents.open') }}
                                            </a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endforeach
    </div>

    {{-- ------------------------------------------------------------------
         Emitir y archivar
         ------------------------------------------------------------------ --}}
    @can('update', $project)
        <section class="card hud-in mt-4 p-4">
            <h2 class="card-title">{{ __('documents.issue') }}</h2>
            <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __('documents.issue_help') }}</p>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['weekly', 'complete', 'sheet'] as $what)
                    <form method="POST" action="{{ route('projects.documents.issue', [$project, $what]) }}">
                        @csrf
                        <button type="submit" class="btn btn-secondary btn-sm">{{ __("documents.issue_{$what}") }}</button>
                    </form>
                @endforeach
            </div>
        </section>
    @endcan

    {{-- ------------------------------------------------------------------
         El expediente: todo lo emitido en un solo paquete
         ------------------------------------------------------------------ --}}
    <section class="card hud-in mt-4 p-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <h2 class="card-title">{{ __('archive.title') }}</h2>
                <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __('archive.help') }}</p>
            </div>

            {{-- El botón solo aparece si hay algo que empacar. Ofrecerlo vacío
                 hace que alguien descargue un ZIP, lo abra, y solo entonces
                 descubra que no había nada emitido. --}}
            @if ($issues->isNotEmpty())
                <a href="{{ route('projects.documents.archive', $project) }}" class="btn btn-primary btn-sm shrink-0">
                    {{ __('archive.download') }}
                </a>
            @endif
        </div>

        <p class="mt-2 text-xs text-slate-500">
            {{ $issues->isEmpty()
                ? __('archive.empty')
                : __('archive.count', ['count' => $issues->count()]) }}
        </p>
    </section>

    <section class="card hud-in mt-4">
        <div class="card-header">
            <h2 class="card-title">{{ __('documents.issued_versions') }}</h2>
            <span class="text-xs text-slate-500">{{ $issues->count() }}</span>
        </div>

        @if ($issues->isEmpty())
            <p class="p-5 text-sm leading-relaxed text-slate-500">{{ __('documents.issued_empty') }}</p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <caption class="sr-only">{{ __('documents.issued_versions') }}</caption>
                    <thead>
                        <tr>
                            <th scope="col">{{ __('documents.title') }}</th>
                            <th scope="col" class="w-20">{{ __('documents.version') }}</th>
                            <th scope="col" class="w-32">{{ __('documents.issued_on') }}</th>
                            <th scope="col" class="w-40">{{ __('documents.issued_by') }}</th>
                            <th scope="col" class="w-28"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($issues as $issue)
                            <tr>
                                <td>
                                    <span class="font-medium text-slate-900">{{ $issue->label() }}</span>
                                    {{-- Las cifras de portada, congeladas: dejan
                                         encontrar la version correcta sin abrir
                                         siete PDF, y sobreviven al archivo. --}}
                                    @if ($issue->summary)
                                        <span class="block text-[11px] tabular text-slate-500">
                                            @isset($issue->summary['progress'])
                                                {{ __('dashboard.progress') }} {{ $issue->summary['progress'] }} %
                                            @endisset
                                            @isset($issue->summary['late'])
                                                &middot; {{ __('reports.late') }} {{ $issue->summary['late'] }}
                                            @endisset
                                            @if (($issue->summary['slip_days'] ?? null) !== null)
                                                &middot; {{ __('reports.slip') }} {{ $issue->summary['slip_days'] }} d
                                            @endif
                                        </span>
                                    @endif
                                </td>
                                <td class="tabular font-mono text-xs text-slate-600">v{{ $issue->version }}</td>
                                <td class="text-xs text-slate-600">{{ $issue->issued_at?->format('d/m/Y H:i') }}</td>
                                <td class="text-xs text-slate-600">{{ $issue->issuedBy?->name ?? '\u2014' }}</td>
                                <td>
                                    <a href="{{ route('projects.documents.download', [$project, $issue]) }}"
                                       class="btn btn-secondary btn-sm">{{ __('documents.download') }}</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection
