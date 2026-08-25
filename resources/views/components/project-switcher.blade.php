{{-- El título de la pantalla y el selector son la misma cosa.

     Con un solo proyecto no hay nada que elegir y queda el título de siempre: un
     desplegable que solo se abre para mostrar dónde ya estabas es ruido.

     Funciona sin JavaScript. Es un `<details>` con una lista de enlaces reales,
     igual que la hoja de atajos; lo que agrega el script es el filtrado, cerrar
     con Esc y cerrar al hacer clic afuera. --}}
@if ($projects->count() < 2)
    <h1 class="truncate text-base font-semibold tracking-tight text-slate-900">{{ $project->name }}</h1>
@else
    <details data-project-switcher class="relative min-w-0">
        <summary class="-mx-2 flex min-w-0 cursor-pointer list-none items-center gap-1.5 rounded-md px-2 py-1 hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-hud-500"
                 title="{{ __('projects.switch') }}">
            <h1 class="truncate text-base font-semibold tracking-tight text-slate-900">{{ $project->name }}</h1>
            <x-icon name="chevron-down" class="text-slate-500" />
            <span class="sr-only">— {{ __('projects.switch') }}</span>
        </summary>

        <div class="absolute left-0 top-9 z-30 w-80 max-w-[calc(100vw-2rem)] rounded-lg border border-slate-200 bg-surface p-2 shadow-raised">
            <p class="px-2 pb-1.5 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                {{ __('projects.switch') }}
            </p>

            @if ($showFilter)
                <input type="search"
                       data-project-switcher-filter
                       autocomplete="off"
                       placeholder="{{ __('projects.switch_search') }}"
                       aria-label="{{ __('projects.switch_search') }}"
                       class="mb-1.5 w-full rounded-md border border-slate-300 bg-surface px-2 py-1.5 text-sm text-slate-900 placeholder:text-slate-500 focus:border-brand-600 focus:outline-none focus:ring-1 focus:ring-brand-600">

                {{-- El aviso de «no hay coincidencias» va en la región viva: quien
                     filtra con el teclado no ve la lista, la oye. --}}
                <p data-project-switcher-empty hidden role="status" class="px-2 py-3 text-sm text-slate-600">
                    {{ __('projects.switch_no_matches') }}
                </p>
            @endif

            <ul class="max-h-80 overflow-y-auto" role="list">
                @foreach ($projects as $item)
                    <li data-project-switcher-item data-search="{{ mb_strtolower($item['name'].' '.$item['code']) }}">
                        <a href="{{ $item['url'] }}"
                           @if ($item['current']) aria-current="true" @endif
                           class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-hud-500
                                  {{ $item['current']
                                        ? 'bg-slate-100 font-semibold text-slate-900'
                                        : 'text-slate-700 hover:bg-slate-100 hover:text-slate-900' }}">
                            {{-- La marca del actual no es solo el fondo gris: un
                                 fondo es lo primero que se pierde en una pantalla
                                 mal calibrada o a plena luz. --}}
                            <span aria-hidden="true" class="w-3 shrink-0 text-center text-brand-700">{{ $item['current'] ? '✓' : '' }}</span>
                            <span class="min-w-0 flex-1 truncate">{{ $item['name'] }}</span>
                            <span class="shrink-0 font-mono text-[11px] text-slate-500">{{ $item['code'] }}</span>
                            @if ($item['current'])
                                <span class="sr-only">({{ __('projects.switch_current') }})</span>
                            @endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </details>
@endif
