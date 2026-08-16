<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // La autorización va siempre en el servidor: ocultar un botón no es un permiso.
    use AuthorizesRequests;
}
