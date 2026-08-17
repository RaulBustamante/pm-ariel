@extends('layouts.guest')

@section('title', __('auth.reset_title'))

@section('content')
    <h2 class="mb-2 text-lg font-semibold">{{ __('auth.reset_title') }}</h2>
    <p class="mb-6 text-sm text-slate-600">{{ __('auth.reset_intro') }}</p>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-md bg-[var(--color-badge-ok-bg)] px-3 py-2 text-sm text-[var(--color-badge-ok-fg)] ring-1 ring-[var(--color-badge-ok-line)]">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form-field name="email" :label="__('auth.email')" type="email" required autocomplete="username" autofocus />

        <button type="submit"
                class="w-full btn btn-primary">
            {{ __('auth.reset_send_link') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-brand-700 underline hover:text-brand-800 focus:outline-none focus:ring-2 focus:ring-hud-500">
            {{ __('auth.back_to_login') }}
        </a>
    </p>
@endsection
