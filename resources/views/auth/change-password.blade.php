@extends('layouts.guest')

@section('title', __('auth.change_password_title'))

@section('content')
    <h2 class="mb-2 text-lg font-semibold">{{ __('auth.change_password_title') }}</h2>
    <p class="mb-6 text-sm text-slate-600">{{ __('auth.change_password_intro') }}</p>

    <form method="POST" action="{{ route('password.change.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <x-form-field name="current_password" :label="__('auth.current_password')" type="password" required autocomplete="current-password" autofocus />
        <x-form-field name="password" :label="__('auth.new_password')" type="password" required autocomplete="new-password" :help="__('auth.password_rules')" />
        <x-form-field name="password_confirmation" :label="__('auth.confirm_password')" type="password" required autocomplete="new-password" />

        <button type="submit"
                class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            {{ __('common.save') }}
        </button>
    </form>
@endsection
