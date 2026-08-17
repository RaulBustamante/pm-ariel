<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Contracts\Identity\IdentityProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Una cuenta desactivada deja de servir en la siguiente petición, no en la
 * siguiente sesión.
 *
 * `is_active` se comprobaba solo en el momento de entrar. Eso alcanza para
 * impedir que alguien inicie sesión, pero no para sacar a quien ya está dentro:
 * el día que se desactiva a una persona porque dejó la empresa, su sesión
 * abierta seguía funcionando hasta que el navegador se cerrara. Para un sistema
 * cuyo primer módulo fue el control de acceso, eso no es un detalle.
 *
 * Va en el grupo `web` entero por la misma razón que
 * {@see EnsurePasswordIsChanged}: una ruta nueva no puede quedar fuera por
 * olvido de quien la escribió.
 */
final class EnsureAccountIsActive
{
    public function __construct(
        private readonly IdentityProvider $identity,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_active) {
            return $next($request);
        }

        // Se cierra la sesión en lugar de solo negar el paso: dejarla viva
        // significa que cada petición vuelve a consultar la base para negar lo
        // mismo, y que quien reactive la cuenta reanuda una sesión de hace
        // semanas sin volver a escribir su contraseña.
        $this->identity->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('auth.inactive'),
        ]);
    }
}
