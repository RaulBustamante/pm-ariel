<!DOCTYPE html>
@php
    // El tema lo escribe el servidor, no un script.
    //
    // Si lo decidiera JavaScript al cargar, la página aparecería un instante con
    // el tema equivocado antes de corregirse — y ese parpadeo blanco es
    // exactamente lo que delata a un tema oscuro mal hecho. Sin atributo manda
    // `prefers-color-scheme`, que es lo que significa «Sistema».
    $theme = auth()->user()?->theme ?? \App\Models\User::THEME_SYSTEM;

    // El proyecto abierto, si la pantalla es de un proyecto. Sale de la
    // dirección y no de la vista: las treinta pantallas de proyecto ya ponen el
    // nombre en `heading` y ninguna tiene que enterarse de esto.
    $currentProject = request()->route('project');
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"
      @if ($theme !== \App\Models\User::THEME_SYSTEM) data-theme="{{ $theme }}" @endif
      class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    <link rel="icon" type="image/png" href="{{ asset(config('branding.mark')) }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full">
    <a href="#main-content"
       class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-4 focus:py-2 focus:shadow">
        {{ __('common.skip_to_content') }}
    </a>

    <div class="flex min-h-full">
        {{-- Navegación lateral. Va un tono **más oscura** que el lienzo, no más
             clara: hunde el chasis y deja el contenido al frente. Usa los tokens
             `shell` y no la escala `slate`, porque esa escala está invertida
             (ver la nota en app.css) y aquí hace falta un valor absoluto. --}}
        <aside class="hidden w-56 shrink-0 flex-col border-r border-slate-200 bg-shell lg:flex">
            <div class="flex h-14 items-center gap-2 border-b border-slate-200 px-4">
                <img src="{{ asset(config('branding.mark')) }}" alt="" class="h-8 w-8 object-contain">
                <span class="truncate text-sm font-semibold tracking-tight text-white">{{ config('branding.short_name') }}</span>
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

                    <x-nav-link :href="route('admin.positions.index')" :active="request()->routeIs('admin.positions.*')" icon="users">
                        {{ __('positions.title') }}
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

                    @if (auth()->user()->hasRole(\App\Models\Role::ADMIN))
                        <x-nav-link :href="route('admin.buzon.index')" :active="request()->routeIs('admin.buzon.*')" icon="clipboard">
                            Buzón
                        </x-nav-link>
                    @endif
                @endcan
            </nav>

            <div class="border-t border-slate-200 p-2">
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
            <header class="sticky top-0 z-20 flex h-14 shrink-0 items-center justify-between gap-3 border-b border-slate-200 bg-surface/95 px-4 backdrop-blur lg:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <img src="{{ asset(config('branding.mark')) }}" alt="" class="h-8 w-8 object-contain lg:hidden">
                    {{-- Dentro de un proyecto el título es también la puerta
                         para cambiarse a otro: el nombre ya estaba ahí, y
                         cambiarse costaba salir al listado y volver a entrar.
                         Fuera de un proyecto no hay nada que elegir. --}}
                    @if ($currentProject instanceof \App\Models\Project)
                        <x-project-switcher :project="$currentProject" />
                    @else
                        <h1 class="truncate text-base font-semibold tracking-tight text-slate-900">@yield('heading')</h1>
                    @endif
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
            <nav class="flex gap-1 overflow-x-auto border-b border-slate-200 bg-surface px-4 py-2 lg:hidden" aria-label="{{ __('common.dashboard') }}">
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
                              {{ request()->routeIs($link['pattern']) ? 'bg-hud-500 text-[#04141a] font-semibold' : 'text-slate-600 hover:bg-slate-100' }}">
                        {{ $link['label'] }}
                    </a>
                @endforeach
            </nav>

            <main id="main-content" class="min-w-0 flex-1 px-4 py-5 lg:px-6 lg:py-6">
                <div class="mx-auto max-w-[1600px] space-y-4">
                    {{-- Los avisos no usan la escala `slate` invertida sino
                         valores propios: son de las pocas superficies con color
                         y necesitan calibrarse a mano contra el lienzo oscuro.
                         El filo encendido a la izquierda hace el trabajo que
                         hacía el fondo de color cuando el lienzo era blanco. --}}
                    @foreach ([
                        'status' => ['badge-ok shadow-[inset_3px_0_0_0_var(--color-fill-ok)]', 'status'],
                        'warning' => ['badge-warn shadow-[inset_3px_0_0_0_var(--color-fill-warn)]', 'alert'],
                        'error' => ['badge-danger shadow-[inset_3px_0_0_0_var(--color-fill-danger)]', 'alert'],
                    ] as $key => [$classes, $role])
                        @if (session($key))
                            <div role="{{ $role }}" class="hud-in block rounded-md border px-4 py-2.5 text-sm {{ $classes }}">
                                {{ session($key) }}
                            </div>
                        @endif
                    @endforeach

                    @yield('content')
                </div>
            </main>
        </div>
    </div>

    @auth
        <x-buzon-widget />
    @endauth

    {{-- Scripts que empujan las vistas. Va al final del cuerpo para que el DOM
         ya exista cuando corran: sin esto, un `@push('scripts')` no se pinta en
         ningun lado y el script desaparece sin avisar. --}}
    @stack('scripts')
</body>
</html>
