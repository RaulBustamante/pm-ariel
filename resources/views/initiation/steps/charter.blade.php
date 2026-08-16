@extends('layouts.app')

@section('title', __('initiation.title'))
@section('heading', $project->name)

@section('content')
    @include('initiation._stepper')

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route($step->route().'.update', $project) }}"
              class="space-y-6 rounded-lg bg-white p-6 ring-1 ring-slate-200 lg:col-span-2">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-base font-semibold text-slate-900">
                    {{ $step->title() }}
                    <x-help-term term="charter" />
                </h2>
                <p class="mt-1 text-sm text-slate-600">{{ __('initiation.charter_intro') }}</p>
            </div>

            <x-initiation.text-field name="objectives" :charter="$charter" :project="$project" :step="$step"
                                     term="objective" rows="3" :can-suggest="$canSuggest" />

            <x-initiation.text-field name="deliverables" :charter="$charter" :project="$project" :step="$step"
                                     term="deliverable" rows="4" :can-suggest="$canSuggest" />

            <x-initiation.text-field name="success_criteria" :charter="$charter" :project="$project" :step="$step"
                                     term="success_criteria" rows="3" :can-suggest="$canSuggest" />

            <div class="space-y-1.5">
                <label for="sponsor-field" class="block text-sm font-medium text-slate-800">
                    {{ __('initiation.field_sponsor') }}
                    <x-help-term term="sponsor" />
                </label>
                <p class="text-xs leading-relaxed text-slate-600">{{ __('initiation.help_sponsor') }}</p>
                <select id="sponsor-field" name="sponsor_id"
                        class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                    <option value="">—</option>
                    @foreach ($members as $member)
                        <option value="{{ $member->id }}" @selected((int) old('sponsor_id', $charter->sponsor_id ?? 0) === $member->id)>
                            {{ $member->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <x-initiation.text-field name="out_of_scope" :charter="$charter" :project="$project" :step="$step"
                                     term="out_of_scope" rows="3" />

            <x-initiation.text-field name="assumptions" :charter="$charter" :project="$project" :step="$step"
                                     term="assumption" rows="3" />

            <x-initiation.text-field name="constraints" :charter="$charter" :project="$project" :step="$step"
                                     term="constraint" rows="3" />

            <x-initiation.text-field name="high_level_milestones" :charter="$charter" :project="$project" :step="$step"
                                     rows="4" />

            @include('initiation._actions')
        </form>

        <aside class="space-y-6">
            @include('initiation._findings')
        </aside>
    </div>

    @stack('outside-form')
@endsection
