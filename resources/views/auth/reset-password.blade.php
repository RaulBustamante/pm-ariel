@extends('layouts.guest')

@section('title', __('auth.reset_new_title'))

@section('content')
    <h2 class="mb-6 text-lg font-semibold">{{ __('auth.reset_new_title') }}</h2>

    <form method="POST" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        <x-form-field name="email" :label="__('auth.email')" type="email" :value="$email" required autocomplete="username" />
        <x-form-field name="password" :label="__('auth.new_password')" type="password" required autocomplete="new-password" :help="__('auth.password_rules')" autofocus />
        <x-form-field name="password_confirmation" :label="__('auth.confirm_password')" type="password" required autocomplete="new-password" />

        <button type="submit"
                class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            {{ __('auth.reset_submit') }}
        </button>
    </form>
@endsection
