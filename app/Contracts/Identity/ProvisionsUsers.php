<?php

declare(strict_types=1);

namespace App\Contracts\Identity;

use App\Models\User;

/**
 * Cómo nace y se actualiza una cuenta.
 *
 * Con usuarios locales el administrador da de alta y el sistema genera una
 * contraseña temporal. Con SSO, el alta la dispara el primer inicio de sesión
 * del proveedor y no hay contraseña que generar. El resto del sistema no
 * necesita saber cuál de los dos está operando.
 */
interface ProvisionsUsers
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, temporaryPassword: ?string}
     */
    public function provision(array $attributes): array;

    /**
     * @return string|null La contraseña temporal generada, o null si el
     *                     proveedor no administra contraseñas.
     */
    public function resetPassword(User $user): ?string;
}
