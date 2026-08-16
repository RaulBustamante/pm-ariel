<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use RuntimeException;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        $this->createFirstAdministrator();
    }

    /**
     * La contraseña se genera aquí y se imprime una sola vez. No se escribe en
     * ningún archivo del repositorio, y nace marcada como temporal: mientras
     * siga vigente, dos personas conocen la cuenta.
     */
    private function createFirstAdministrator(): void
    {
        $email = (string) config('branding.admin_email');

        if (User::query()->where('email', $email)->exists()) {
            if (isset($this->command)) {
                $this->command->warn("El administrador {$email} ya existe; no se toca.");
            }

            return;
        }

        // Sin consola no hay dónde imprimir la contraseña, y una cuenta cuya
        // contraseña nadie vio no sirve de nada. Mejor fallar que crearla a ciegas.
        if (! isset($this->command)) {
            throw new RuntimeException(
                'El primer administrador solo puede sembrarse desde consola: la contraseña temporal se imprime una sola vez.',
            );
        }

        $password = Str::password(16);

        $admin = User::query()->create([
            'name' => 'Administrador',
            'email' => $email,
            'password' => $password,
            'locale' => 'es',
            'is_active' => true,
            'must_change_password' => true,
        ]);

        $admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        $this->command->newLine();
        $this->command->info('Administrador creado.');
        $this->command->table(
            ['Correo', 'Contrasena temporal'],
            [[$email, $password]],
        );
        $this->command->warn('Anotala ahora: no se vuelve a mostrar y debe cambiarse al primer acceso.');
    }
}
