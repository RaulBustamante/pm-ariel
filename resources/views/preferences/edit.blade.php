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

            {{-- El tema, con muestra a la vista.
                 Tres nombres en una lista desplegable obligan a elegir a ciegas
                 y a guardar para ver qué pasó. Con la muestra al lado, se
                 escoge mirando. --}}
            <fieldset class="space-y-1">
                <legend class="block text-sm font-medium text-slate-700">{{ __('preferences.theme') }}</legend>

                <div class="grid gap-2 sm:grid-cols-3" role="radiogroup">
                    @foreach ([
                        \App\Models\User::THEME_SYSTEM => ['#e2e8f0', '#0f172a', __('preferences.theme_system')],
                        \App\Models\User::THEME_LIGHT => ['#ffffff', '#e2e8f0', __('preferences.theme_light')],
                        \App\Models\User::THEME_DARK => ['#0e1526', '#22d3ee', __('preferences.theme_dark')],
                    ] as $value => [$fill, $stroke, $label])
                        @php $checked = old('theme', $me->theme ?? \App\Models\User::THEME_SYSTEM) === $value; @endphp

                        <label class="relative flex cursor-pointer items-center gap-3 rounded-lg border p-3 transition-colors
                                      {{ $checked ? 'border-hud-500 bg-brand-50' : 'border-slate-300 hover:border-slate-400' }}">
                            <input type="radio" name="theme" value="{{ $value }}" @checked($checked)
                                   class="sr-only">

                            {{-- Una ventanita: fondo del tema y una barra de
                                 acento. Dice más que la palabra. --}}
                            <span aria-hidden="true"
                                  class="flex h-8 w-11 shrink-0 flex-col justify-end gap-1 overflow-hidden rounded border border-slate-300 p-1"
                                  style="background: {{ $value === \App\Models\User::THEME_SYSTEM ? 'linear-gradient(135deg, #ffffff 50%, #0e1526 50%)' : $fill }}">
                                <span class="block h-1 w-full rounded-full" style="background: {{ $stroke }}"></span>
                                <span class="block h-1 w-2/3 rounded-full" style="background: {{ $stroke }}; opacity: .5"></span>
                            </span>

                            <span class="text-sm font-medium text-slate-900">{{ $label }}</span>

                            @if ($checked)
                                <span aria-hidden="true" class="absolute right-2 top-2 text-hud-500">✓</span>
                            @endif
                        </label>
                    @endforeach
                </div>

                <p class="field-help">{{ __('preferences.theme_help') }}</p>

                @error('theme')
                    <p role="alert" class="text-sm text-red-700">{{ $message }}</p>
                @enderror
            </fieldset>

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
            <div class="rounded-md border border-slate-200 bg-surface p-4">
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
