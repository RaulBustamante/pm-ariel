<?php

declare(strict_types=1);

use App\Support\DetailLevel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cuánto enseña cada proyecto en sus pantallas.
 *
 * Nace en Estándar para todos, incluidos los que ya existen: enseñar de menos
 * se arregla con un clic desde la pantalla del proyecto, y enseñar de más es
 * justo lo que la gente pidió que dejáramos de hacer.
 *
 * `string` y no un `enum` de la base: MySQL obliga a reescribir la tabla para
 * agregar un caso al `enum`, y aquí es probable que aparezca un tercer nivel.
 * La validación de los valores vive en `DetailLevel`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->string('detail_level', 20)
                ->default(DetailLevel::Standard->value)
                ->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn('detail_level');
        });
    }
};
