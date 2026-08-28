@extends('layouts.app')

@section('title', __('users.edit_title'))
@section('heading', __('users.edit_title'))

@section('content')
    <form method="POST" action="{{ route('admin.users.update', $user) }}" class="max-w-2xl rounded-lg bg-surface p-6 ring-1 ring-slate-200">
        @csrf
        @method('PUT')

        @include('admin.users._form')

        <div class="mt-6 flex gap-3">
            <button type="submit"
                    class="btn btn-primary">
                {{ __('common.save') }}
            </button>
            <a href="{{ route('admin.users.index') }}"
               class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-hud-500">
                {{ __('common.cancel') }}
            </a>
        </div>
    </form>

    @can('resetPassword', $user)
        <section class="mt-6 max-w-2xl rounded-lg bg-surface p-6 ring-1 ring-slate-200">
            <h2 class="text-base font-semibold">{{ __('users.password_title') }}</h2>
            <p class="mt-1 text-sm text-slate-600">{{ __('users.password_help') }}</p>

            <form method="POST" action="{{ route('admin.users.password.update', $user) }}" class="mt-4 space-y-4">
                @csrf
                @method('PUT')

                <x-form-field name="password" :label="__('users.password_new')" type="password"
                              required autocomplete="new-password" />
                <x-form-field name="password_confirmation" :label="__('users.password_confirm')" type="password"
                              required autocomplete="new-password" />

                <div class="space-y-1">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="must_change_password" value="1"
                               @checked(old('must_change_password', true))
                               class="rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-hud-500">
                        {{ __('users.password_force_change') }}
                    </label>
                    <p class="text-xs text-slate-500">{{ __('users.password_force_change_help') }}</p>
                </div>

                <button type="submit" class="btn btn-primary">{{ __('users.password_set_action') }}</button>
            </form>

            <form method="POST" action="{{ route('admin.users.password.reset', $user) }}"
                  class="mt-6 border-t border-slate-200 pt-4"
                  onsubmit="return confirm('{{ __('users.password_reset_confirm') }}')">
                @csrf
                <button type="submit"
                        class="rounded-md px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-hud-500">
                    {{ __('users.password_reset_action') }}
                </button>
                <p class="mt-2 text-xs text-slate-500">{{ __('users.password_reset_help') }}</p>
            </form>
        </section>
    @endcan
@endsection
