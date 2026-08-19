@extends('layouts.app')

@section('title', __('initiation.title'))
@section('heading', $project->name)

@section('content')
    @include('initiation._stepper')

    <p class="mb-5 max-w-3xl text-sm text-slate-600">
        {{ __('initiation.stakeholders_intro') }}
        <x-help-term term="stakeholder" />
    </p>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include('initiation._findings')

            {{-- Alta de interesado --}}
            <form method="POST" action="{{ route('projects.stakeholders.store', $project) }}"
                  class="space-y-4 rounded-lg bg-surface p-5 ring-1 ring-slate-200">
                @csrf

                <h2 class="text-sm font-semibold text-slate-900">{{ __('initiation.add_stakeholder') }}</h2>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="name" :label="__('initiation.stakeholder_name')"
                                  :help="__('initiation.stakeholder_name_help')" required />
                    <x-form-field name="role_title" :label="__('initiation.stakeholder_role')" />
                    <x-form-field name="organization" :label="__('initiation.stakeholder_organization')" />
                    <x-form-field name="email" type="email" :label="__('initiation.stakeholder_email')" />
                    <x-form-field name="phone" :label="__('initiation.stakeholder_phone')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-initiation.scale name="power" :label="__('initiation.stakeholder_power')"
                                        :help="__('initiation.stakeholder_power_help')" term="power" />
                    <x-initiation.scale name="interest" :label="__('initiation.stakeholder_interest')"
                                        :help="__('initiation.stakeholder_interest_help')" term="interest" />
                </div>

                {{-- Qué espera y qué se va a hacer con esta persona.
                     Las tres columnas —teléfono, expectativas y estrategia—
                     existían desde la Etapa 2, estaban en las reglas de
                     validación y **ninguna pantalla las capturaba**. La
                     estrategia incluso se mostraba en el paquete de inicio, así
                     que llevaba siete etapas saliendo siempre vacía. --}}
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="expectations" class="field-label">{{ __('initiation.stakeholder_expectations') }}</label>
                        <textarea id="expectations" name="expectations" rows="2" class="field">{{ old('expectations') }}</textarea>
                        <p class="field-help">{{ __('initiation.stakeholder_expectations_help') }}</p>
                    </div>

                    <div class="space-y-1">
                        <label for="engagement_strategy" class="field-label">{{ __('initiation.stakeholder_strategy') }}</label>
                        <textarea id="engagement_strategy" name="engagement_strategy" rows="2" class="field">{{ old('engagement_strategy') }}</textarea>
                        <p class="field-help">{{ __('initiation.stakeholder_strategy_help') }}</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <button type="submit"
                            class="btn btn-primary">
                        {{ __('common.create') }}
                    </button>
                    <p class="text-xs text-slate-500">{{ __('initiation.stakeholder_strategy_help') }}</p>
                </div>
            </form>

            {{-- Lista --}}
            @if ($project->stakeholders->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-300 bg-surface p-6 text-center text-sm text-slate-600">
                    {{ __('initiation.no_stakeholders') }}
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($project->stakeholders as $person)
                        <li class="rounded-lg bg-surface p-4 ring-1 ring-slate-200">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $person->name }}</p>
                                    <p class="text-xs text-slate-600">
                                        {{ collect([$person->role_title, $person->organization])->filter()->implode(' · ') ?: '—' }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-3 text-xs">
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 font-medium text-slate-700">
                                        {{ __('initiation.stakeholder_power') }} {{ $person->power }} ·
                                        {{ __('initiation.stakeholder_interest') }} {{ $person->interest }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 font-medium
                                        @class([
                                            'bg-[var(--color-badge-danger-bg)] text-[var(--color-badge-danger-fg)]' => $person->quadrant() === \App\Models\Stakeholder::QUADRANT_MANAGE_CLOSELY,
                                            'bg-[var(--color-badge-warn-bg)] text-[var(--color-badge-warn-fg)]' => $person->quadrant() === \App\Models\Stakeholder::QUADRANT_KEEP_SATISFIED,
                                            'bg-brand-100 text-brand-800' => $person->quadrant() === \App\Models\Stakeholder::QUADRANT_KEEP_INFORMED,
                                            'bg-slate-100 text-slate-700' => $person->quadrant() === \App\Models\Stakeholder::QUADRANT_MONITOR,
                                        ])">
                                        {{ __("initiation.quadrant_{$person->quadrant()}") }}
                                    </span>

                                    <form method="POST" action="{{ route('projects.stakeholders.destroy', [$project, $person]) }}"
                                          onsubmit="return confirm('{{ __('common.confirm_title') }}')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="rounded text-[var(--color-badge-danger-fg)] underline hover:text-[var(--color-badge-danger-fg)] focus:outline-none focus:ring-2 focus:ring-[var(--color-badge-danger-fg)]">
                                            {{ __('common.delete') }}<span class="sr-only"> — {{ $person->name }}</span>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            @if (filled($person->engagement_strategy))
                                <p class="mt-2 border-t border-slate-100 pt-2 text-xs text-slate-700">
                                    <span class="font-medium">{{ __('initiation.stakeholder_strategy') }}:</span>
                                    {{ $person->engagement_strategy }}
                                </p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <form method="POST" action="{{ route($step->route().'.update', $project) }}"
                  class="rounded-lg bg-surface p-5 ring-1 ring-slate-200">
                @csrf
                @method('PUT')
                @include('initiation._actions')
            </form>
        </div>

        <aside class="space-y-4">
            @include('initiation._matrix')

            @if ($canSuggest)
                <form method="POST" action="{{ route('projects.stakeholders.suggest', $project) }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-hud-500 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-hud-500">
                        {{ __('initiation.suggest_stakeholders') }}
                    </button>
                </form>
            @endif
        </aside>
    </div>
@endsection
