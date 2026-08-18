<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que la tarea **de verdad costó**.
 *
 * Hasta aquí el sistema sabía dos cosas: lo que una tarea costaría según su
 * plan, y qué parte de eso ya se ganó con el avance capturado. Con esas dos no
 * se puede calcular valor ganado: el CPI es valor ganado entre **costo real**, y
 * el costo real no estaba en ninguna parte.
 *
 * Deducirlo del plan —«si va al 40 %, se habrá gastado el 40 % del
 * presupuesto»— daría un CPI de exactamente 1.00 en todos los proyectos, para
 * siempre. Un indicador que nunca puede dar mala noticia no es un indicador: es
 * un adorno, y uno que hace creer que el costo está bajo control.
 *
 * Va nulo a propósito. `null` significa «no se ha capturado» y **no** «costó
 * cero», y esa diferencia es la que permite decirle a quien lee el informe que
 * los índices están calculados sobre datos incompletos, en vez de darle un
 * número redondo que no puede saber que es mentira.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->decimal('actual_cost', 14, 2)->nullable()->after('cost');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn('actual_cost');
        });
    }
};
