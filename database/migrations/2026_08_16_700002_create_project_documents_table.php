<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El contenido de los documentos que se redactan.
 *
 * **Una sola tabla para los veinticinco**, no veinticinco tablas. Todos tienen
 * la misma forma —un proyecto, un código de documento y unas secciones de
 * texto—, y lo que cambia entre el plan de alcance y el de costos es de qué se
 * habla, no la estructura.
 *
 * El contenido va en JSON y no en columnas porque las secciones las define la
 * configuración: agregar una sección a un juego no puede exigir una migración,
 * o nadie la agregaría nunca.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_documents', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // El código del catálogo. Texto y no llave foránea: el catálogo vive
            // en configuración, no en la base.
            $table->string('document_code', 60);

            /*
            | Las secciones, por su clave: `{"approach": "...", "roles": "..."}`.
            |
            | Nunca se lee sin pasar por el juego de secciones que define la
            | configuración, así que una sección retirada del juego deja de
            | mostrarse sin que haya que limpiar la base — y si vuelve, su texto
            | sigue ahí.
            */
            $table->json('content')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'document_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_documents');
    }
};
