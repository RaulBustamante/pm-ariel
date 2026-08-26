<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('buzon_tickets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('folio', 24)->nullable()->unique();
            $table->string('tipo', 20);
            $table->string('titulo', 180);
            $table->text('descripcion');
            $table->string('severidad', 20)->nullable();
            $table->string('url', 500)->nullable();
            $table->string('ruta_nombre', 150)->nullable();
            $table->string('navegador', 120)->nullable();
            $table->string('sistema_operativo', 120)->nullable();
            $table->string('resolucion', 40)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('errores_consola')->nullable();
            $table->string('estado', 20)->default('nuevo');
            $table->foreignId('asignado_a')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas_internas')->nullable();
            $table->timestamp('resuelto_en')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['estado', 'created_at']);
            $table->index(['user_id', 'estado']);
        });

        Schema::create('buzon_adjuntos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('buzon_ticket_id')->constrained('buzon_tickets')->cascadeOnDelete();
            $table->string('ruta_archivo', 500);
            $table->string('nombre_original');
            $table->unsignedBigInteger('tamano')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('buzon_adjuntos');
        Schema::dropIfExists('buzon_tickets');
    }
};
