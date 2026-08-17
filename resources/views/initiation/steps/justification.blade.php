@extends('layouts.app')

@section('title', __('initiation.title'))
@section('heading', $project->name)

@section('content')
    @include('initiation._stepper')

    <div class="grid gap-6 lg:grid-cols-3">
        <form method="POST" action="{{ route($step->route().'.update', $project) }}"
              class="space-y-6 rounded-lg bg-surface p-6 ring-1 ring-slate-200 lg:col-span-2">
            @csrf
            @method('PUT')

            <div>
                <h2 class="text-base font-semibold text-slate-900">{{ $step->title() }}</h2>
                <p class="mt-1 text-sm text-slate-600">{{ $step->purpose() }}</p>
            </div>

            <x-initiation.text-field name="problem_statement" :charter="$charter" :project="$project" :step="$step"
                                     term="justification" rows="4" :example="__('initiation.example_problem_statement')"
                                     :can-suggest="$canSuggest" />

            <x-initiation.text-field name="expected_benefit" :charter="$charter" :project="$project" :step="$step"
                                     rows="3" :example="__('initiation.example_expected_benefit')"
                                     :can-suggest="$canSuggest" />

            <x-initiation.text-field name="opportunity" :charter="$charter" :project="$project" :step="$step"
                                     term="opportunity" rows="3" />

            <x-initiation.text-field name="alignment" :charter="$charter" :project="$project" :step="$step" rows="3" />

            @include('initiation._actions')
        </form>

        <aside class="space-y-6">
            @include('initiation._findings')
        </aside>
    </div>

    @stack('outside-form')
@endsection
