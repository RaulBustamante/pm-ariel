<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\IdentityProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

final class LocalIdentityProvider implements IdentityProvider
{
    public const NAME = 'local';

    public function name(): string
    {
        return self::NAME;
    }

    /**
     * @param  array<string, string>  $credentials
     */
    public function attempt(array $credentials, bool $remember = false): bool
    {
        // Una cuenta desactivada no entra, aunque la contraseña sea correcta.
        return Auth::attempt(
            [...$credentials, 'is_active' => true, 'auth_provider' => self::NAME],
            $remember,
        );
    }

    public function managesPasswords(): bool
    {
        return true;
    }

    public function logout(): void
    {
        Auth::logout();
    }

    public function user(): ?User
    {
        /** @var User|null $user */
        $user = Auth::user();

        return $user;
    }
}
