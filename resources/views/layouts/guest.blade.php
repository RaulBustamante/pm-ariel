<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        <div class="w-full max-w-md">
            <div class="mb-8 text-center">
                <h1 class="text-2xl font-semibold tracking-tight">{{ config('branding.name') }}</h1>
                @if (config('branding.tagline'))
                    <p class="mt-1 text-sm text-slate-600">{{ config('branding.tagline') }}</p>
                @endif
            </div>

            <div class="rounded-lg bg-surface p-6 shadow-sm ring-1 ring-slate-200">
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
