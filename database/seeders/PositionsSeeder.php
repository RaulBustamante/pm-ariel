<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Position;
use Illuminate\Database\Seeder;

/**
 * Un catálogo de puestos con el que se pueda empezar.
 *
 * No es la plantilla de Ariel —esa la define Raúl— sino un punto de partida para
 * que el desplegable del alta de usuarios no salga vacío el primer día. Se puede
 * editar y borrar todo desde la pantalla de puestos.
 *
 * Se salta los que ya existen, así que correrlo dos veces no duplica nada.
 */
final class PositionsSeeder extends Seeder
{
    public function run(): void
    {
        // El nivel ordena de mayor a menor jerarquía. **No otorga permisos**:
        // eso lo hacen los roles. Confundirlos sería dar acceso por título en
        // vez de por responsabilidad.
        $positions = [
            1 => ['Dirección General'],
            2 => ['Dirección de Operaciones', 'Dirección de Finanzas', 'Dirección Comercial'],
            3 => ['Gerencia de Sistemas', 'Gerencia de Producción', 'Gerencia de Compras',
                'Gerencia de Calidad', 'Gerencia de Recursos Humanos'],
            4 => ['Jefatura de Área', 'Coordinación de Proyectos'],
            5 => ['Supervisión', 'Analista', 'Especialista'],
            6 => ['Auxiliar', 'Técnico'],
        ];

        foreach ($positions as $level => $names) {
            foreach ($names as $name) {
                Position::query()->firstOrCreate(['name' => $name], ['level' => $level]);
            }
        }

        if (isset($this->command)) {
            $this->command->info('Puestos disponibles: '.Position::query()->count());
        }
    }
}
