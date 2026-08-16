@extends('layouts.guest')

@section('title', __('auth.sign_in'))

@section('content')
    <h2 class="mb-6 text-lg font-semibold">{{ __('auth.sign_in') }}</h2>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-900 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf

        <x-form-field name="email" :label="__('auth.email')" type="email" required autocomplete="username" autofocus />
        <x-form-field name="password" :label="__('auth.password_label')" type="password" required autocomplete="current-password" />

        <div class="flex items-center gap-2">
            <input type="checkbox" id="remember" name="remember" value="1"
                   class="rounded border-slate-300 text-blue-700 focus:ring-2 focus:ring-blue-600">
            <label for="remember" class="text-sm text-slate-700">{{ __('auth.remember_me') }}</label>
        </div>

        <button type="submit"
                class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            {{ __('auth.sign_in') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('password.request') }}" class="text-blue-700 underline hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
            {{ __('auth.forgot_password') }}
        </a>
    </p>
@endsection
