<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Archivos colgados de una tarea.
 *
 * **El nombre en disco lo genera el sistema; el del usuario solo se guarda para
 * mostrarlo.** Un archivo guardado con el nombre que trae permite dos ataques
 * viejos y baratos: escribir fuera de la carpeta con «../» en el nombre, y
 * servir contenido ejecutable porque la extensión decía otra cosa.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->cascadeOnDelete();

            // Lo que el usuario ve.
            $table->string('original_name');

            // Lo que existe en disco: generado, sin relación con el anterior.
            $table->string('stored_path', 500);

            $table->string('mime_type', 150);
            $table->string('extension', 20);
            $table->unsignedBigInteger('size_bytes');

            // Huella del contenido: permite detectar el mismo archivo subido dos
            // veces sin comparar bytes cada vez.
            $table->string('checksum', 64)->nullable();

            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
