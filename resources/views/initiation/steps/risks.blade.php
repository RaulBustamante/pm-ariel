@extends('layouts.app')

@section('title', __('initiation.title'))
@section('heading', $project->name)

@section('content')
    @include('initiation._stepper')

    <p class="mb-5 max-w-3xl text-sm text-slate-600">
        {{ __('initiation.risks_intro') }}
        <x-help-term term="risk" />
    </p>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @include('initiation._findings')

            <form method="POST" action="{{ route('projects.risks.store', $project) }}"
                  class="space-y-4 rounded-lg bg-surface p-5 ring-1 ring-slate-200">
                @csrf

                <h2 class="text-sm font-semibold text-slate-900">{{ __('initiation.add_risk') }}</h2>

                <div class="space-y-1.5">
                    <label for="description-field" class="block text-sm font-medium text-slate-800">
                        {{ __('initiation.risk_description') }} <span class="text-red-600" aria-hidden="true">*</span>
                    </label>
                    <p class="text-xs text-slate-600">{{ __('initiation.risk_description_help') }}</p>
                    <textarea id="description-field" name="description" rows="2" required
                              class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">{{ old('description') }}</textarea>
                    @error('description') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form-field name="cause" :label="__('initiation.risk_cause')" />
                    <x-form-field name="effect" :label="__('initiation.risk_effect')" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-initiation.scale name="probability" :label="__('initiation.risk_probability')"
                                        :help="__('initiation.risk_probability_help')" term="probability" />
                    <x-initiation.scale name="impact" :label="__('initiation.risk_impact')"
                                        :help="__('initiation.risk_impact_help')" term="impact" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-1">
                        <label for="kind-field" class="block text-sm font-medium text-slate-800">
                            {{ __('initiation.risk_kind') }}
                            <x-help-term term="opportunity" />
                        </label>
                        <select id="kind-field" name="kind"
                                class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                            <option value="threat" @selected(old('kind', 'threat') === 'threat')>{{ __('initiation.risk_kind_threat') }}</option>
                            <option value="opportunity" @selected(old('kind') === 'opportunity')>{{ __('initiation.risk_kind_opportunity') }}</option>
                        </select>
                    </div>

                    <div class="space-y-1">
                        <label for="owner-field" class="block text-sm font-medium text-slate-800">
                            {{ __('initiation.risk_owner') }}
                            <x-help-term term="risk_owner" />
                        </label>
                        <select id="owner-field" name="owner_id"
                                class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                            <option value="">—</option>
                            @foreach ($members as $member)
                                <option value="{{ $member->id }}" @selected((int) old('owner_id') === $member->id)>{{ $member->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="submit"
                        class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                    {{ __('common.create') }}
                </button>
            </form>

            @if ($project->risks->isEmpty())
                <div class="rounded-lg border border-dashed border-slate-300 bg-surface p-6 text-center text-sm text-slate-600">
                    {{ __('initiation.no_risks') }}
                </div>
            @else
                <ul class="space-y-3">
                    @foreach ($project->risks as $risk)
                        @include('initiation._risk-card', ['risk' => $risk])
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
            @include('initiation._risk-matrix')

            @if ($canSuggest)
                <form method="POST" action="{{ route('projects.risks.suggest', $project) }}">
                    @csrf
                    <button type="submit"
                            class="w-full rounded-md border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:border-blue-600 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-600">
                        {{ __('initiation.suggest_risks') }}
                    </button>
                </form>
            @endif
        </aside>
    </div>
@endsection
