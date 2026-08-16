<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-50 text-slate-900 antialiased">
    {{-- Primer elemento enfocable: sin esto, quien navega por teclado recorre
         todo el menú antes de llegar al contenido, en cada página. --}}
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow focus:ring-2 focus:ring-blue-600">
        {{ __('common.skip_to_content') }}
    </a>

    <header class="border-b border-slate-200 bg-white">
        <nav class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3" aria-label="{{ __('common.dashboard') }}">
            <div class="flex items-center gap-6">
                <a href="{{ route('dashboard') }}" class="font-semibold tracking-tight">
                    {{ config('branding.short_name') }}
                </a>

                <div class="hidden gap-1 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('common.dashboard') }}
                    </x-nav-link>

                    @can('viewAny', App\Models\User::class)
                        <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')">
                            {{ __('common.users') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', App\Models\User::class)
                        <x-nav-link :href="route('admin.hierarchy.index')" :active="request()->routeIs('admin.hierarchy.*')">
                            {{ __('hierarchy.title') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', App\Models\OrgUnit::class)
                        <x-nav-link :href="route('admin.org-units.index')" :active="request()->routeIs('admin.org-units.*')">
                            {{ __('org_units.title') }}
                        </x-nav-link>
                    @endcan

                    @can('viewAny', App\Models\User::class)
                        <x-nav-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.*')">
                            {{ __('common.audit_log') }}
                        </x-nav-link>
                    @endcan
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="{{ route('preferences.edit') }}"
                   @if (request()->routeIs('preferences.*')) aria-current="page" @endif
                   class="rounded px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-blue-600">
                    {{ auth()->user()->name }}
                    {{-- El modo activo se ve sin entrar a preferencias: si alguien
                         no encuentra una columna, aquí está la razón. --}}
                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs text-slate-600">
                        {{ auth()->user()->expert_mode ? __('common.expert_mode') : __('common.simple_mode') }}
                    </span>
                </a>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded px-3 py-1.5 text-sm font-medium text-slate-700 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-blue-600">
                        {{ __('auth.sign_out') }}
                    </button>
                </form>
            </div>
        </nav>
    </header>

    <main id="main-content" class="mx-auto max-w-7xl px-4 py-8">
        @if (session('status'))
            <div role="status" class="mb-6 rounded-md bg-emerald-50 px-4 py-3 text-sm text-emerald-900 ring-1 ring-emerald-200">
                {{ session('status') }}
            </div>
        @endif

        <h1 class="mb-6 text-xl font-semibold tracking-tight">@yield('heading')</h1>

        @yield('content')
    </main>
</body>
</html>
