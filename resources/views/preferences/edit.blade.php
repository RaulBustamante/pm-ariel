@extends('layouts.app')

@section('title', __('preferences.title'))
@section('heading', __('preferences.heading'))

@section('content')
    @php
        /** @var \App\Models\User $me */
        $me = auth()->user();
    @endphp

    <p class="mb-6 max-w-2xl text-sm text-slate-600">{{ __('preferences.intro') }}</p>

    <form method="POST" action="{{ route('preferences.update') }}" class="max-w-2xl space-y-8">
        @csrf
        @method('PUT')

        <fieldset class="space-y-4">
            <legend class="text-sm font-semibold text-slate-900">{{ __('preferences.display') }}</legend>

            <div class="space-y-1">
                <label for="locale-field" class="block text-sm font-medium text-slate-700">
                    {{ __('common.language') }}
                </label>
                <select id="locale-field" name="locale"
                        @if ($errors->has('locale')) aria-invalid="true" aria-describedby="locale-field-error" @endif
                        class="block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-2 focus:ring-blue-600 sm:text-sm">
                    @foreach (config('app.supported_locales') as $locale)
                        <option value="{{ $locale }}" @selected(old('locale', $me->locale) === $locale)>
                            {{ __('common.locale_'.$locale) }}
                        </option>
                    @endforeach
                </select>
                @error('locale')
                    <p id="locale-field-error" role="alert" class="text-sm text-red-700">{{ $message }}</p>
                @enderror
            </div>

            <x-form-field name="timezone"
                          :label="__('common.timezone')"
                          :value="$me->timezone ?? config('app.timezone')"
                          :help="__('preferences.timezone_help')"
                          required />
        </fieldset>

        <fieldset class="space-y-3">
            <legend class="text-sm font-semibold text-slate-900">{{ __('preferences.detail_level') }}</legend>

            <p class="text-xs text-slate-500">{{ __('common.mode_help') }}</p>

            {{-- Una casilla y no dos botones de opción: el Modo Simple es el
                 estado normal, y el Experto es lo que se activa a propósito. --}}
            <div class="rounded-md border border-slate-200 bg-white p-4">
                <label class="flex items-start gap-3 text-sm">
                    <input type="checkbox" name="expert_mode" value="1"
                           @checked(old('expert_mode', $me->expert_mode))
                           class="mt-0.5 rounded border-slate-300 text-blue-700 focus:ring-2 focus:ring-blue-600">
                    <span>
                        <span class="font-medium text-slate-900">{{ __('common.expert_mode') }}</span>
                        <span class="mt-1 block text-slate-600">{{ __('preferences.expert_mode_help') }}</span>
                    </span>
                </label>

                <p class="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    {{ $me->expert_mode ? __('preferences.current_expert') : __('preferences.current_simple') }}
                    @unless ($me->expert_mode)
                        {{ __('preferences.simple_mode_help') }}
                    @endunless
                </p>
            </div>
        </fieldset>

        <div class="flex items-center gap-3">
            <button type="submit"
                    class="rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
                {{ __('common.save') }}
            </button>

            <a href="{{ route('dashboard') }}"
               class="rounded-md px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>
@endsection
