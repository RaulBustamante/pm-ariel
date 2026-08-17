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
@endsection
