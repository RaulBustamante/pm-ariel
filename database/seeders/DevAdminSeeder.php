<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Un administrador local con contraseña conocida, para desarrollo.
 *
 * Existe porque el primer administrador nace con contraseña temporal que se
 * imprime una sola vez (`DatabaseSeeder`), y eso es correcto para producción
 * pero insoportable en local: perder la contraseña obligaba a rehacer la base.
 *
 * Se puede correr las veces que haga falta. Si la cuenta ya existe, le
 * restablece la contraseña en vez de fallar.
 *
 *   artisan db:seed --class=DevAdminSeeder
 *
 * **No corre en producción.** Ahí una cuenta con contraseña escrita en el
 * repositorio sería exactamente el agujero que el resto del sistema evita.
 */
final class DevAdminSeeder extends Seeder
{
    private const DEFAULT_EMAIL = 'admin@localhost';

    private const DEFAULT_PASSWORD = 'Ariel2026!Raul';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException(
                'DevAdminSeeder no corre en produccion: crearia una cuenta con contrasena conocida.',
            );
        }

        // Los roles tienen que existir antes de asignarlos. Llamarlo aquí deja
        // que este seeder funcione solo, sin recordar el orden.
        if (! Role::query()->where('name', Role::ADMIN)->exists()) {
            $this->call(RolesAndPermissionsSeeder::class);
        }

        $email = (string) env('DEV_ADMIN_EMAIL', self::DEFAULT_EMAIL);
        $password = (string) env('DEV_ADMIN_PASSWORD', self::DEFAULT_PASSWORD);

        $user = User::withTrashed()->firstOrNew(['email' => $email]);

        $user->forceFill([
            'name' => $user->name ?? 'Administrador local',
            'password' => $password,
            'locale' => $user->locale ?? 'es',
            'timezone' => $user->timezone ?? config('app.timezone', 'UTC'),
            'is_active' => true,
            // Sin cambio obligatorio: en local la fricción no protege nada.
            'must_change_password' => false,
            'deleted_at' => null,
        ])->save();

        $adminRoleId = Role::query()->where('name', Role::ADMIN)->value('id');

        if ($adminRoleId !== null && ! $user->roles()->where('roles.id', $adminRoleId)->exists()) {
            $user->roles()->attach($adminRoleId);
        }

        // El limitador de intentos vive en el caché. Tras varios intentos
        // fallidos la cuenta queda bloqueada un minuto, y restablecer la
        // contraseña sin limpiarlo dejaría al usuario creyendo que falló otra vez.
        cache()->clear();

        if (isset($this->command)) {
            $this->command->newLine();
            $this->command->info('Administrador local listo.');
            $this->command->table(['Correo', 'Contrasena'], [[$email, $password]]);
            $this->command->comment('Solo para desarrollo. Cambia DEV_ADMIN_PASSWORD en .env si quieres otra.');
        }
    }
}
