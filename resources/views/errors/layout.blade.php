<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="flex h-full items-center justify-center p-6">
    {{-- Un error explicado en el idioma del usuario, con qué hacer a
         continuación. La pantalla cruda de Laravel le dice a quien no es
         programador que el sistema se rompió sin remedio, y eso no es cierto
         casi nunca. --}}
    <main class="w-full max-w-md text-center">
        <p class="font-mono text-sm text-slate-400">@yield('code')</p>

        <h1 class="mt-2 text-xl font-semibold tracking-tight text-slate-900">@yield('title')</h1>

        <p class="mt-3 text-sm leading-relaxed text-slate-600">@yield('message')</p>

        <p class="mt-4 rounded-md bg-surface px-4 py-3 text-sm text-slate-700 ring-1 ring-slate-200">
            @yield('what_to_do')
        </p>

        <div class="mt-6 flex justify-center gap-3">
            <a href="{{ url()->previous() }}" class="btn btn-secondary">{{ __('errors.go_back') }}</a>
            <a href="{{ route('dashboard') }}" class="btn btn-primary">{{ __('errors.go_home') }}</a>
        </div>
    </main>
</body>
</html>
