@extends('layouts.app')

@section('title', __('onboarding.title'))
@section('heading', __('onboarding.heading'))

@section('content')
    <p class="mb-6 max-w-3xl text-sm leading-relaxed text-slate-600">{{ __('onboarding.intro') }}</p>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="space-y-4 lg:col-span-2">
            {{-- El recorrido: cinco pasos en el orden en que se usa el sistema,
                 no en el orden en que se construyó. --}}
            <ol class="space-y-3">
                @foreach ([
                    ['n' => 1, 'key' => 'start', 'route' => 'projects.create'],
                    ['n' => 2, 'key' => 'initiation', 'route' => null],
                    ['n' => 3, 'key' => 'plan', 'route' => null],
                    ['n' => 4, 'key' => 'views', 'route' => null],
                    ['n' => 5, 'key' => 'advisor', 'route' => null],
                ] as $step)
                    <li class="card p-4">
                        <div class="flex gap-3">
                            <span aria-hidden="true"
                                  class="mt-0.5 inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-700 text-xs font-bold text-white">
                                {{ $step['n'] }}
                            </span>
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-slate-900">
                                    {{ __("onboarding.step_{$step['key']}") }}
                                </h2>
                                <p class="mt-1 text-sm leading-relaxed text-slate-600">
                                    {{ __("onboarding.step_{$step['key']}_body") }}
                                </p>

                                @if ($step['route'] && $canCreate)
                                    <a href="{{ route($step['route']) }}" class="btn btn-secondary btn-sm mt-2">
                                        {{ __('initiation.new_project') }}
                                    </a>
                                @endif
                            </div>
                        </div>
                    </li>
                @endforeach
            </ol>

            <section class="card p-4">
                <h2 class="text-sm font-semibold text-slate-900">{{ __('onboarding.vocabulary') }}</h2>
                <p class="mt-1 text-xs text-slate-600">{{ __('onboarding.vocabulary_help') }}</p>

                <dl class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach (['charter', 'stakeholder', 'risk', 'deliverable', 'success_criteria', 'sponsor'] as $term)
                        <div>
                            <dt class="text-xs font-semibold text-slate-900">
                                {{ __("glossary.{$term}_label") }}
                                <x-help-term :term="$term" />
                            </dt>
                            <dd class="mt-0.5 text-xs leading-relaxed text-slate-600">{{ __("glossary.{$term}") }}</dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </div>

        <aside class="space-y-4">
            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('onboarding.demo') }}</h2></div>

                <div class="space-y-3 p-4">
                    <p class="text-xs leading-relaxed text-slate-600">{{ __('onboarding.demo_help') }}</p>

                    @if ($demo)
                        <a href="{{ route('projects.dashboard', $demo) }}" class="btn btn-primary w-full">
                            {{ __('onboarding.demo_open') }}
                        </a>

                        @can('create', App\Models\Project::class)
                            {{-- Borrarlo de un clic. Un demo que no se puede
                                 quitar acaba conviviendo con los proyectos de
                                 verdad hasta que alguien lo confunde. --}}
                            <form method="POST" action="{{ route('onboarding.demo.destroy') }}"
                                  onsubmit="return confirm('{{ __('onboarding.demo_confirm') }}')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-secondary w-full">
                                    {{ __('onboarding.demo_remove') }}
                                </button>
                            </form>
                        @endcan
                    @else
                        @can('create', App\Models\Project::class)
                            <form method="POST" action="{{ route('onboarding.demo.store') }}">
                                @csrf
                                <button type="submit" class="btn btn-primary w-full">
                                    {{ __('onboarding.demo_load') }}
                                </button>
                            </form>
                        @else
                            <p class="text-xs text-slate-500">{{ __('onboarding.demo_admin_only') }}</p>
                        @endcan
                    @endif
                </div>
            </section>

            <section class="card">
                <div class="card-header"><h2 class="card-title">{{ __('shortcuts.title') }}</h2></div>
                <dl class="space-y-1 p-4">
                    @foreach ([
                        'd' => __('dashboard.title'),
                        'l' => __('tasks.list_view'),
                        'g' => __('tasks.gantt_view'),
                        'k' => __('kanban.title'),
                        'c' => __('calendar.title'),
                        'a' => __('advisor.title'),
                        '?' => __('shortcuts.this_sheet'),
                    ] as $key => $label)
                        <div class="flex items-center justify-between gap-2 text-xs">
                            <dt class="text-slate-600">{{ $label }}</dt>
                            <dd><kbd class="rounded border border-slate-300 bg-slate-50 px-1.5 py-0.5 font-mono text-[11px]">{{ $key }}</kbd></dd>
                        </div>
                    @endforeach
                </dl>
            </section>
        </aside>
    </div>
@endsection
