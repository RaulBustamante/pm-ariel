<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mientras la contraseña siga siendo la temporal, toda ruta lleva a cambiarla.
 *
 * Va en el grupo `web` completo, no en rutas sueltas: así ningún módulo que se
 * agregue después puede saltársela por olvido.
 */
final class EnsurePasswordIsChanged
{
    /**
     * Las únicas rutas que un usuario con contraseña temporal puede tocar.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'password.change',
        'password.change.update',
        'logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::ALLOWED_ROUTES, strict: true)) {
            return $next($request);
        }

        return redirect()->route('password.change');
    }
}
