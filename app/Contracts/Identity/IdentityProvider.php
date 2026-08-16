<?php

declare(strict_types=1);

namespace App\Contracts\Identity;

use App\Models\User;

/**
 * Cómo se verifica quién es alguien.
 *
 * Existe desde el primer commit aunque hoy solo haya una implementación: el día
 * que llegue Microsoft 365 o Active Directory, se agrega una clase que cumpla
 * este contrato y se registra en el contenedor. Ninguna Policy, ningún modelo y
 * ninguna vista cambian. Ver ARCHITECTURE.md, "Preparación para SSO".
 */
interface IdentityProvider
{
    /**
     * Identificador del proveedor, tal como se guarda en `users.auth_provider`.
     */
    public function name(): string;

    /**
     * @param  array<string, string>  $credentials
     * @return bool Si la identidad quedó verificada y la sesión iniciada.
     */
    public function attempt(array $credentials, bool $remember = false): bool;

    /**
     * Si este proveedor permite cambiar la contraseña dentro de la aplicación.
     * Con SSO la respuesta es no: la contraseña no vive aquí.
     */
    public function managesPasswords(): bool;

    public function logout(): void;

    public function user(): ?User;
}
