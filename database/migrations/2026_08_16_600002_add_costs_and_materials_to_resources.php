<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Materiales, y con ellos el costo de verdad.
 *
 * Hasta aquí un recurso era alguien que da horas: se medía en porcentaje de
 * jornada y costaba por hora. Eso cubre a las personas y al equipo, pero no a
 * **lo que se consume**. Media tonelada de acero no se asigna «al 60 % de la
 * jornada»: se asigna en cantidad, y cuesta por unidad.
 *
 * Por eso las dos formas de asignar conviven en `task_assignments` en lugar de
 * forzar una sola: `units_percent` para quien trabaja, `quantity` para lo que se
 * consume. Intentar expresar «300 kg» como un porcentaje daría un número que no
 * significa nada y que nadie podría auditar contra una factura.
 *
 * Todas las columnas son anulables. Nada de lo que ya existe cambia de sentido:
 * un recurso sin `cost_per_unit` sigue siendo exactamente lo que era.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('resources', function (Blueprint $table): void {
            // Pieza, metro, litro, kilogramo, caja. Texto libre y no un catálogo
            // cerrado: la unidad la dicta el proveedor en su factura, y adivinar
            // hoy la lista completa de unidades de Ariel sería inventarla.
            $table->string('unit_of_measure', 30)->nullable()->after('capacity_percent');

            // Lo que cuesta **una** unidad. Convive con `cost_per_hour`, no lo
            // reemplaza: un servicio de un proveedor puede cobrarse por hora, y
            // el material que ese proveedor entrega, por pieza.
            $table->decimal('cost_per_unit', 12, 2)->nullable()->after('cost_per_hour');

            $table->string('supplier')->nullable()->after('cost_per_unit');

            // Interno o de fuera. Importa para el reporte de costos —lo que sale
            // de la empresa se presenta aparte de lo que ya está en la nómina— y
            // no se deduce del tipo: hay personal externo y equipo propio.
            $table->boolean('is_external')->default(false)->after('supplier');

            $table->index(['project_id', 'type']);
        });

        Schema::table('task_assignments', function (Blueprint $table): void {
            // Cuánto material lleva esta tarea. Anulable porque una asignación de
            // trabajo no tiene cantidad, igual que una de material no tiene
            // porcentaje de jornada.
            $table->decimal('quantity', 12, 3)->nullable()->after('units_percent');
        });
    }

    public function down(): void
    {
        Schema::table('task_assignments', function (Blueprint $table): void {
            $table->dropColumn('quantity');
        });

        Schema::table('resources', function (Blueprint $table): void {
            $table->dropIndex(['project_id', 'type']);
            $table->dropColumn(['unit_of_measure', 'cost_per_unit', 'supplier', 'is_external']);
        });
    }
};
