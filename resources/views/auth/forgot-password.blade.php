@extends('layouts.guest')

@section('title', __('auth.reset_title'))

@section('content')
    <h2 class="mb-2 text-lg font-semibold">{{ __('auth.reset_title') }}</h2>
    <p class="mb-6 text-sm text-slate-600">{{ __('auth.reset_intro') }}</p>

    @if (session('status'))
        <div role="status" class="mb-4 rounded-md bg-emerald-50 px-3 py-2 text-sm text-emerald-900 ring-1 ring-emerald-200">
            {{ session('status') }}
        </div>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf

        <x-form-field name="email" :label="__('auth.email')" type="email" required autocomplete="username" autofocus />

        <button type="submit"
                class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-medium text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:ring-offset-2">
            {{ __('auth.reset_send_link') }}
        </button>
    </form>

    <p class="mt-4 text-center text-sm">
        <a href="{{ route('login') }}" class="text-blue-700 underline hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
            {{ __('auth.back_to_login') }}
        </a>
    </p>
@endsection
