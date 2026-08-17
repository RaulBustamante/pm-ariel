@extends('layouts.app')

@section('title', __("documents.doc_{$code}"))
@section('heading', $project->name)

@section('content')
    @include('tasks._tabs', ['active' => 'documents'])

    <div class="mb-4 flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-base font-semibold text-slate-900">{{ __("documents.doc_{$code}") }}</h2>

            @if ($document?->updated_at)
                <p class="mt-0.5 text-xs text-slate-500">
                    {{ __('sections.last_updated', [
                        'date' => $document->updated_at->format('d/m/Y H:i'),
                        'who' => $document->updatedBy?->name ?? '—',
                    ]) }}
                </p>
            @else
                <p class="mt-0.5 text-xs text-slate-500">{{ __('sections.never_written') }}</p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2">
            {{-- El estado, sin dramatizar. Faltar secciones opcionales no cuenta:
                 marcar como incompleto lo que no lo está entrena a la gente a
                 ignorar el aviso. --}}
            @if ($missing === 0)
                <span class="badge badge-ok">{{ __('sections.complete') }}</span>
            @else
                <span class="badge badge-warn">{{ __('sections.missing', ['count' => $missing]) }}</span>
            @endif

            <a href="{{ route('projects.documents.narrative.pdf', [$project, $code]) }}" class="btn btn-secondary btn-sm">
                {{ __('sections.download') }}
            </a>

            <a href="{{ route('projects.documents', $project) }}" class="btn btn-ghost btn-sm">
                {{ __('documents.title') }}
            </a>
        </div>
    </div>

    <form method="POST" action="{{ route('projects.documents.narrative.update', [$project, $code]) }}"
          class="space-y-3">
        @csrf
        @method('PUT')

        @foreach ($sections as $index => $section)
            <section class="card hud-in hud-in-{{ min(4, $index + 1) }} p-4">
                <div class="mb-2 flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <label for="section-{{ $section['key'] }}" class="text-sm font-semibold text-slate-900">
                            {{ $section['title'] }}
                            @if ($section['required'])
                                <span class="ml-1 text-[11px] font-normal text-slate-500">· {{ __('sections.required') }}</span>
                            @endif
                        </label>

                        {{-- La ayuda no describe el campo: dice qué hace útil
                             llenarlo. Es lo que evita una frase vacía. --}}
                        <p class="mt-0.5 max-w-3xl text-xs leading-relaxed text-slate-600">{{ $section['help'] }}</p>
                    </div>

                    @can('update', $project)
                        <button type="submit"
                                form="suggest-{{ $section['key'] }}"
                                class="btn btn-ghost btn-sm shrink-0">
                            {{ __('sections.suggest') }}
                        </button>
                    @endcan
                </div>

                <textarea id="section-{{ $section['key'] }}"
                          name="sections[{{ $section['key'] }}]"
                          rows="{{ $section['rows'] }}"
                          class="field"
                          @cannot('update', $project) readonly @endcannot
                          placeholder="{{ __('sections.empty_section') }}">{{ old("sections.{$section['key']}", $section['value']) }}</textarea>
            </section>
        @endforeach

        @can('update', $project)
            <div class="flex items-center gap-3">
                <button type="submit" class="btn btn-primary">{{ __('common.save') }}</button>
                <p class="field-help max-w-2xl">{{ __('sections.suggest_help') }}</p>
            </div>
        @endcan
    </form>

    {{-- Los formularios de sugerencia van **fuera** del formulario principal:
         un `<form>` dentro de otro no es HTML válido, y el navegador se come el
         de adentro sin avisar. --}}
    @can('update', $project)
        @foreach ($sections as $section)
            <form id="suggest-{{ $section['key'] }}" method="POST" class="hidden"
                  action="{{ route('projects.documents.narrative.suggest', [$project, $code, $section['key']]) }}">
                @csrf
            </form>
        @endforeach
    @endcan
@endsection
