<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // En el grupo web completo, no en rutas sueltas: así ningún módulo que
        // se agregue después puede saltarse el cambio de contraseña temporal ni
        // seguir atendiendo a una cuenta ya desactivada.
        //
        // El orden importa: primero se comprueba que la cuenta siga vigente. No
        // tiene sentido mandar a cambiar su contraseña a alguien que acaba de
        // dejar la empresa.
        $middleware->web(append: [
            SetLocale::class,
            EnsureAccountIsActive::class,
            EnsurePasswordIsChanged::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
