@extends('layouts.app')

@section('title', __('initiation.new_project'))
@section('heading', __('initiation.new_project'))

@section('content')
    <p class="mb-5 max-w-2xl text-sm text-slate-600">{{ __('initiation.intro') }}</p>

    <form method="POST" action="{{ route('projects.store') }}"
          class="max-w-2xl space-y-5 rounded-lg bg-surface p-6 ring-1 ring-slate-200">
        @csrf

        <x-form-field name="name" :label="__('initiation.project_name')"
                      :help="__('initiation.project_name_help')" required autofocus />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-form-field name="code" :label="__('initiation.project_code')"
                          :help="__('initiation.project_code_help')" required />

            <div class="space-y-1">
                <label for="org-unit-field" class="block text-sm font-medium text-slate-700">{{ __('common.org_unit') }}</label>
                <select id="org-unit-field" name="org_unit_id"
                        class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-hud-500 focus:ring-2 focus:ring-hud-500">
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
                      class="block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-hud-500 focus:ring-2 focus:ring-hud-500">{{ old('description') }}</textarea>
        </div>

        <fieldset class="space-y-2">
            <legend class="text-sm font-medium text-slate-700">{{ __('initiation.project_type') }}</legend>
            <p class="text-xs text-slate-600">{{ __('initiation.project_type_help') }}</p>

            <div class="space-y-2">
                @foreach ($templates as $template)
                    <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 text-sm hover:border-brand-400 has-[:checked]:border-hud-500 has-[:checked]:bg-brand-50">
                        <input type="radio" name="template_id" value="{{ $template->id }}"
                               @checked((int) old('template_id') === $template->id)
                               class="mt-0.5 border-slate-300 text-brand-700 focus:ring-2 focus:ring-hud-500">
                        <span>
                            <span class="block font-medium text-slate-900">{{ $template->name }}</span>
                            <span class="block text-xs text-slate-600">{{ $template->description }}</span>
                        </span>
                    </label>
                @endforeach

                <label class="flex cursor-pointer items-start gap-3 rounded-md border border-slate-200 p-3 text-sm hover:border-brand-400 has-[:checked]:border-hud-500 has-[:checked]:bg-brand-50">
                    <input type="radio" name="template_id" value=""
                           @checked(old('template_id') === '' || old('template_id') === null)
                           class="mt-0.5 border-slate-300 text-brand-700 focus:ring-2 focus:ring-hud-500">
                    <span class="font-medium text-slate-900">{{ __('initiation.no_template') }}</span>
                </label>
            </div>
        </fieldset>

        {{-- Paso 2 — quién --}}
        <fieldset class="space-y-2 border-t border-slate-100 pt-5">
            <legend class="text-sm font-semibold text-slate-900">{{ __('wizard.step_who') }}</legend>
            <p class="field-help">{{ __('wizard.step_who_help') }}</p>

            <div class="max-h-40 space-y-1 overflow-y-auto rounded-md border border-slate-200 p-2">
                @foreach ($candidates as $candidate)
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="members[]" value="{{ $candidate->id }}"
                               class="rounded border-slate-300 text-brand-700">
                        {{ $candidate->name }}
                    </label>
                @endforeach
            </div>
        </fieldset>

        {{-- Paso 3 — cuándo --}}
        <fieldset class="space-y-2 border-t border-slate-100 pt-5">
            <legend class="text-sm font-semibold text-slate-900">{{ __('wizard.step_when') }}</legend>

            <div class="grid max-w-2xl gap-4 sm:grid-cols-2">
                <div>
                    <label for="start-field" class="field-label">{{ __('tasks.project_start') }}</label>
                    <input id="start-field" type="date" name="planned_start" class="field"
                           value="{{ old('planned_start') }}" required>
                    @error('planned_start') <p role="alert" class="mt-1 text-xs text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
                    <p class="field-help mt-1">{{ __('wizard.step_when_help') }}</p>
                </div>

                <div>
                    <label for="finish-field" class="field-label">{{ __('projects.planned_finish') }}</label>
                    <input id="finish-field" type="date" name="planned_finish" class="field"
                           value="{{ old('planned_finish') }}" min="{{ old('planned_start') }}">
                    @error('planned_finish') <p role="alert" class="mt-1 text-xs text-[var(--color-badge-danger-fg)]">{{ $message }}</p> @enderror
                    <p class="field-help mt-1">{{ __('projects.planned_finish_help') }}</p>
                </div>
            </div>
        </fieldset>

        {{-- Paso 4 — cómo se mide --}}
        <fieldset class="space-y-3 border-t border-slate-100 pt-5">
            <legend class="text-sm font-semibold text-slate-900">{{ __('wizard.step_measure') }}</legend>

            <div>
                <label for="criteria-field" class="field-label">{{ __('initiation.field_success_criteria') }}</label>
                <textarea id="criteria-field" name="success_criteria" rows="2" class="field">{{ old('success_criteria') }}</textarea>
                <p class="field-help mt-1">{{ __('initiation.help_success_criteria') }}</p>
            </div>

            <div>
                <label for="deliverables-field" class="field-label">{{ __('initiation.field_deliverables') }}</label>
                <textarea id="deliverables-field" name="deliverables" rows="4" class="field"
                          placeholder="{{ __('wizard.deliverables_placeholder') }}">{{ old('deliverables') }}</textarea>
                {{-- Se dice antes de escribir, no después: cada renglón se
                     convierte en una tarea del plan. --}}
                <p class="field-help mt-1 font-medium text-brand-800">{{ __('wizard.deliverables_become_tasks') }}</p>
            </div>
        </fieldset>

        <div class="flex gap-3 border-t border-slate-100 pt-5">
            <button type="submit" class="btn btn-primary">{{ __('initiation.start') }}</button>
            <a href="{{ route('projects.index') }}" class="btn btn-ghost">{{ __('common.cancel') }}</a>
        </div>
    </form>
@endsection

@push('scripts')
    <script>
        (() => {
            const start = document.getElementById('start-field');
            const finish = document.getElementById('finish-field');

            if (!start || !finish) return;

            const syncFinish = () => {
                finish.min = start.value;
                if (finish.value && start.value && finish.value < start.value) {
                    finish.value = start.value;
                }
            };

            start.addEventListener('change', syncFinish);
            syncFinish();
        })();
    </script>
@endpush
