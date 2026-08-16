@extends('layouts.app')

@section('title', __('initiation.new_project'))
@section('heading', __('initiation.new_project'))

@section('content')
    <p class="mb-5 max-w-2xl text-sm text-slate-600">{{ __('initiation.intro') }}</p>

    <form method="POST" action="{{ route('projects.store') }}"
          class="max-w-2xl space-y-5 rounded-lg bg-white p-6 ring-1 ring-slate-200">
        @csrf

        <x-form-field name="name" :label="__('initiation.project_name')"
                      :help="__('initiation.project_name_help')" required autofocus />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-form-field name="code" :label="__('initiation.project_code')"
                          :help="__('initiation.project_code_help')" required />

            <div class="space-y-1">
                <label for="org-unit-field" class="block text-sm font-medium text-slate-700">{{ __('common.org_unit') }}</label>
                <select id="org-unit-field" name="org_unit_id"
                        class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">
                    <option value="">—</option>
                    @foreach ($orgUnits as $unit)
                        <option value="{{ $unit->id }}" @selected((int) old('org_unit_id') === $unit->id)>
                            {{ str_repeat('— ', $unit->depth) }}{{ $unit->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="space-y-1">
            <label for="description-field" class="block text-sm font-medium text-slate-700">
                {{ __('initiation.project_description') }}
            </label>
            <textarea id="description-field" name="description" rows="2"
                      class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600">{{ old('description') }}</textarea>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium text-slate-700">{{ __('initiation.project_type') }}</legend>
            <p class="text-xs text-slate-600">{{ __('initiation.project_type_help') }}</p>

            <div class="space-y-2">
                @foreach ($templates as $template)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 text-sm hover:border-blue-400 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                        <input type="radio" name="template_id" value="{{ $template->id }}"
                               @checked((int) old('template_id') === $template->id)
                               class="mt-0.5 border-slate-300 text-blue-700 focus:ring-2 focus:ring-blue-600">
                        <span>
                            <span class="block font-medium text-slate-900">{{ $template->name }}</span>
                            <span class="block text-xs text-slate-600">{{ $template->description }}</span>
                        </span>
                    </label>
                @endforeach

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 text-sm hover:border-blue-400 has-[:checked]:border-blue-600 has-[:checked]:bg-blue-50">
                    <input type="radio" name="template_id" value=""
                           @checked(old('template_id') === '' || old('template_id') === null)
                           class="mt-0.5 border-slate-300 text-blue-700 focus:ring-2 focus:ring-blue-600">
                    <span class="font-medium text-slate-900">{{ __('initiation.no_template') }}</span>
                </label>
            </div>
        </fieldset>

        <div class="flex gap-3 border-t border-slate-100 pt-5">
            <button type="submit"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('initiation.start') }}
            </button>
            <a href="{{ route('projects.index') }}"
               class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
