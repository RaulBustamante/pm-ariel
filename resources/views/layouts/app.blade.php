<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:shadow">
        {{ __('common.skip_to_content') }}
    </a>

    <div class="flex min-h-full">
        {{-- Navegación lateral oscura. El contraste con el lienzo claro es lo que
             da estructura: sin él, la pantalla es una hoja en blanco donde nada
             indica dónde empieza el contenido. --}}
        <aside class="hidden w-56 shrink-0 flex-col bg-slate-900 lg:flex">
            <div class="flex h-14 items-center gap-2 border-b border-slate-800 px-4">
                <span class="flex h-7 w-7 items-center justify-center rounded bg-brand-700 text-xs font-bold text-white">
                    {{ mb_substr(config('branding.short_name'), 0, 2) }}
                </span>
                <span class="truncate text-sm font-semibold text-white">{{ config('branding.short_name') }}</span>
            </div>

            <nav class="flex-1 overflow-y-auto p-2" aria-label="{{ __('common.dashboard') }}">
                <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">
                    {{ __('common.dashboard') }}
                </x-nav-link>

                @can('viewAny', App\Models\Project::class)
                    <p class="nav-section">{{ __('common.projects') }}</p>

                    <x-nav-link :href="route('projects.index')"
                                :active="request()->routeIs('projects.index') || request()->routeIs('projects.create')"
                                icon="folder">
                        {{ __('initiation.projects') }}
                    </x-nav-link>
                @endcan

                @canany(['viewAny'], [App\Models\User::class])
                    <p class="nav-section">{{ __('common.administration') }}</p>

                    <x-nav-link :href="route('admin.users.index')" :active="request()->routeIs('admin.users.*')" icon="users">
                        {{ __('common.users') }}
                    </x-nav-link>

                    <x-nav-link :href="route('admin.hierarchy.index')" :active="request()->routeIs('admin.hierarchy.*')" icon="sitemap">
                        {{ __('hierarchy.title') }}
                    </x-nav-link>
                @endcanany

                @can('viewAny', App\Models\OrgUnit::class)
                    <x-nav-link :href="route('admin.org-units.index')" :active="request()->routeIs('admin.org-units.*')" icon="building">
                        {{ __('org_units.title') }}
                    </x-nav-link>
                @endcan

                @can('viewAny', App\Models\User::class)
                    <x-nav-link :href="route('admin.audit.index')" :active="request()->routeIs('admin.audit.*')" icon="clipboard">
                        {{ __('common.audit_log') }}
                    </x-nav-link>
                @endcan
            </nav>

            <div class="border-t border-slate-800 p-2">
                <a href="{{ route('onboarding') }}" class="nav-item" @if (request()->routeIs('onboarding*')) aria-current="page" @endif>
                    <x-icon name="clipboard" />
                    {{ __('onboarding.title') }}
                </a>

                <a href="{{ route('preferences.edit') }}" class="nav-item" @if (request()->routeIs('preferences.*')) aria-current="page" @endif>
                    <x-icon name="cog" />
                    {{ __('common.preferences') }}
                </a>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Barra superior: identidad de quien está dentro, modo activo y
                 salida. En pantalla chica también hace de navegación. --}}
            <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-white/95 px-4 backdrop-blur lg:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <span class="flex h-7 w-7 items-center justify-center rounded bg-brand-700 text-xs font-bold text-white lg:hidden">
                        {{ mb_substr(config('branding.short_name'), 0, 2) }}
                    </span>
                    <h1 class="truncate text-base font-semibold tracking-tight text-slate-900">@yield('heading')</h1>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    <a href="{{ route('preferences.edit') }}"
                       class="hidden items-center gap-2 rounded-md px-2 py-1.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 sm:flex">
                        <span class="max-w-[10rem] truncate">{{ auth()->user()->name }}</span>
                        <span class="badge {{ auth()->user()->expert_mode ? 'badge-brand' : 'badge-neutral' }}">
                            {{ auth()->user()->expert_mode ? __('common.expert_mode') : __('common.simple_mode') }}
                        </span>
                    </a>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="btn btn-ghost btn-sm">{{ __('auth.sign_out') }}</button>
                    </form>
                </div>
            </header>

            {{-- Navegación en pantalla chica: la lateral se oculta, pero el
                 acceso no puede desaparecer con ella. --}}
            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-white px-4 py-2 lg:hidden" aria-label="{{ __('common.dashboard') }}">
                @foreach ([
                    ['route' => 'dashboard', 'label' => __('common.dashboard'), 'pattern' => 'dashboard'],
                    ['route' => 'projects.index', 'label' => __('initiation.projects'), 'pattern' => 'projects.*'],
                    ['route' => 'admin.users.index', 'label' => __('common.users'), 'pattern' => 'admin.users.*'],
                    ['route' => 'admin.hierarchy.index', 'label' => __('hierarchy.title'), 'pattern' => 'admin.hierarchy.*'],
                    ['route' => 'admin.org-units.index', 'label' => __('org_units.title'), 'pattern' => 'admin.org-units.*'],
                ] as $link)
                    <a href="{{ route($link['route']) }}"
                       @if (request()->routeIs($link['pattern'])) aria-current="page" @endif
                       class="shrink-0 rounded-md px-3 py-1.5 text-sm font-medium
                              {{ request()->routeIs($link['pattern']) ? 'bg-brand-700 text-white' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <main id="main-content" class="min-w-0 flex-1 px-4 py-5 lg:px-6 lg:py-6">
                <div class="mx-auto max-w-[1600px] space-y-4">
                    @foreach ([
                        'status' => ['bg-emerald-50 text-emerald-900 ring-emerald-200', 'status'],
                        'warning' => ['bg-amber-50 text-amber-900 ring-amber-200', 'alert'],
                        'error' => ['bg-red-50 text-red-900 ring-red-200', 'alert'],
                    ] as $key => [$classes, $role])
                        @if (session($key))
                            <div role="{{ $role }}" class="rounded-md px-4 py-2.5 text-sm ring-1 {{ $classes }}">
                                {{ session($key) }}
                            </div>
                        @endif
                    @endforeach

                    @yield('content')
                </div>
            </main>
        </div>
    </div>
</body>
</html>
