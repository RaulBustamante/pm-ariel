@extends('layouts.guest')

@section('title', __('auth.sign_in'))

@section('content')
    <h2 class="mb-6 text-lg font-semibold">{{ __('auth.sign_in') }}</h2>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-md bg-[var(--color-badge-ok-bg)] px-3 py-2 text-sm text-[var(--color-badge-ok-fg)] ring-1 ring-[var(--color-badge-ok-line)]">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-form-field name="email" :label="__('auth.email')" type="email" required autocomplete="username" autofocus />
        <x-form-field name="password" :label="__('auth.password_label')" type="password" required autocomplete="current-password" />

        <div class="flex items-center gap-2">
            <input type="checkbox" id="remember" name="remember" value="1"
                   class="rounded border-slate-300 text-brand-600 focus:ring-2 focus:ring-hud-500">
            <label for="remember" class="text-sm text-slate-700">{{ __('auth.remember_me') }}</label>
        </div>

        <button type="submit"
                class="w-full btn btn-primary">
            {{ __('auth.sign_in') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('password.request') }}" class="text-brand-700 underline hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-hud-500">
            {{ __('auth.forgot_password') }}
        </a>
    </p>
@endsection
