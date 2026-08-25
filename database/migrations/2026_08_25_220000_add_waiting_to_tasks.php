<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * La espera: por qué una tarea no avanza cuando la razón está afuera.
 *
 * Tres columnas y no una. `waiting_on` dice de qué tipo es la espera y sirve
 * para contar y filtrar; `waiting_since` la fecha desde la que lleva ahí, que es
 * lo único que permite dar seguimiento; y `waiting_note` a quién se espera, que
 * no se puede catalogar.
 *
 * `waiting_since` es columna del sistema, no del usuario: si la escribiera la
 * gente, pondría la fecha de hoy cada vez que tocara la tarea y el seguimiento
 * no serviría para nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->string('waiting_on', 32)->nullable()->after('percent_complete');
            $table->dateTime('waiting_since')->nullable()->after('waiting_on');
            $table->string('waiting_note', 255)->nullable()->after('waiting_since');

            // El índice es para la regla del Asesor y el filtro: las dos
            // preguntan «cuáles están esperando» sobre la tabla entera, y en un
            // portafolio grande eso es un recorrido completo sin él.
            $table->index('waiting_on');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex(['waiting_on']);
            $table->dropColumn(['waiting_on', 'waiting_since', 'waiting_note']);
        });
    }
};
