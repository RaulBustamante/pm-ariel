@extends('layouts.app')

@section('title', __('reports.documents'))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    <p class="mb-4 max-w-3xl text-sm text-slate-600">{{ __('reports.documents_intro') }}</p>

    @php
        /*
        | El juego de documentos, y **cuándo se emite cada uno**.
        |
        | La columna que importa aquí no es el formato sino el momento: un acta
        | constitutiva se firma una vez al arrancar y no se vuelve a tocar; un
        | corte semanal se emite cada lunes y el de la semana pasada ya no sirve.
        | Poner los dos en una lista de «descargas» sin decir eso invita a mandar
        | el equivocado.
        */
        $documents = [
            [
                'title' => __('initiation.package'),
                'when' => __('reports.when_charter'),
                'answers' => __('reports.answers_charter'),
                'route' => route('projects.initiation.package', $project),
                'icon' => 'clipboard',
                'primary' => false,
            ],
            [
                'title' => __('reports.weekly'),
                'when' => __('reports.when_weekly'),
                'answers' => __('reports.answers_weekly'),
                'route' => route('projects.reports.weekly', $project),
                'icon' => 'chart',
                'primary' => true,
            ],
            [
                'title' => __('reports.complete'),
                'when' => __('reports.when_complete'),
                'answers' => __('reports.answers_complete'),
                'route' => route('projects.reports.complete', $project),
                'icon' => 'folder',
                'primary' => false,
            ],
            [
                'title' => __('reports.download_csv'),
                'when' => __('reports.when_csv'),
                'answers' => __('reports.answers_csv'),
                'route' => route('projects.reports.csv', $project),
                'icon' => 'folder',
                'primary' => false,
            ],
        ];
    @endphp

    <div class="grid gap-3 md:grid-cols-2">
        @foreach ($documents as $index => $document)
            <a href="{{ $document['route'] }}"
               class="card card-interactive hud-in hud-in-{{ min(4, $index + 1) }} block p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-900">{{ $document['title'] }}</p>
                        <p class="mt-0.5 text-xs text-slate-500">{{ $document['when'] }}</p>
                    </div>

                    @if ($document['primary'])
                        <span class="badge badge-brand shrink-0">{{ __('reports.weekly_badge') }}</span>
                    @endif
                </div>

                {{-- Qué pregunta responde. Es lo que evita mandar el documento
                     equivocado, mucho más que el nombre del archivo. --}}
                <p class="mt-2 text-xs leading-relaxed text-slate-600">{{ $document['answers'] }}</p>
            </a>
        @endforeach
    </div>

    <section class="card mt-4 p-4">
        <h2 class="card-title">{{ __('reports.coming_documents') }}</h2>
        <p class="mt-1 max-w-3xl text-xs leading-relaxed text-slate-600">{{ __('reports.coming_documents_help') }}</p>

        <ul class="mt-3 space-y-1.5 text-xs text-slate-600">
            @foreach ([__('reports.doc_closure'), __('reports.doc_archive'), __('reports.doc_costs')] as $pending)
                <li class="flex items-start gap-2">
                    <span aria-hidden="true" class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-slate-400"></span>
                    {{ $pending }}
                </li>
            @endforeach
        </ul>
    </section>
@endsection
