<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * El idioma sale de la preferencia del usuario; si no ha entrado, de la
 * configuración de la organización. Agregar un idioma no debe tocar esta clase:
 * basta con agregar sus archivos de traducción y listarlo en la configuración.
 */
final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var list<string> $supported */
        $supported = config('app.supported_locales', ['es', 'en']);

        $locale = $request->user()?->locale ?? (string) config('app.locale');

        if (in_array($locale, $supported, strict: true)) {
            App::setLocale($locale);
        }

        return $next($request);
    }
}
