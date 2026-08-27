<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title') · {{ config('branding.name') }}</title>
    <link rel="icon" type="image/png" href="{{ \App\Support\Branding\BrandAsset::url('mark') }}">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-full flex-col items-center justify-center px-4 py-12">
        {{-- El logotipo y la tarjeta comparten ancho y viven en el mismo
             contenedor: antes el logotipo iba a `max-w-sm` y la tarjeta a
             `max-w-md`, y ese desajuste de cuatro rem era la mitad de por qué la
             composición se veía chueca. La otra mitad era el archivo, que traía
             el 64 % de su altura en fondo vacío. --}}
        <div class="brand-glow w-full max-w-md">
            {{-- La placa de la marca. El archivo ya viene recortado al arte, así
                 que aquí solo se redondea, se le pone filo y se le da luz. --}}
            <div class="brand-plate hud-in mb-7">
                <img src="{{ \App\Support\Branding\BrandAsset::url('logo') }}"
                     alt="{{ config('branding.name') }} · {{ config('branding.tagline') }}"
                     width="719" height="295">
            </div>

            {{-- La tarjeta entra después del logotipo, no al mismo tiempo: la
                 mirada aterriza en la marca y luego en el formulario. Con
                 `prefers-reduced-motion` las dos aparecen de golpe, y el
                 contenido nunca depende de que la animación termine. --}}
            <div class="brand-card hud-in hud-in-2 rounded-xl bg-surface p-6 shadow-raised ring-1 ring-slate-200">
                @yield('content')
            </div>
        </div>
    </main>
</body>
</html>
