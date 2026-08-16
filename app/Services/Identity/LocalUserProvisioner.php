<?php

declare(strict_types=1);

namespace App\Services\Identity;

use App\Contracts\Identity\ProvisionsUsers;
use App\Models\User;
use Illuminate\Support\Str;

final class LocalUserProvisioner implements ProvisionsUsers
{
    private const TEMPORARY_PASSWORD_LENGTH = 16;

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{user: User, temporaryPassword: ?string}
     */
    public function provision(array $attributes): array
    {
        $password = Str::password(self::TEMPORARY_PASSWORD_LENGTH);

        $user = User::query()->create([
            ...$attributes,
            'password' => $password,
            'auth_provider' => LocalIdentityProvider::NAME,
            // Nace temporal: mientras siga vigente la contraseña que puso el
            // administrador, dos personas conocen la cuenta y no se puede
            // sostener ante auditoría que un registro lo firmó su autor.
            'must_change_password' => true,
        ]);

        return ['user' => $user, 'temporaryPassword' => $password];
    }

    public function resetPassword(User $user): ?string
    {
        $password = Str::password(self::TEMPORARY_PASSWORD_LENGTH);

        $user->forceFill([
            'password' => $password,
            'must_change_password' => true,
        ])->save();

        return $password;
    }
}
