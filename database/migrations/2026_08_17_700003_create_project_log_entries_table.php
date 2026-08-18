<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los registros que crecen durante el proyecto.
 *
 * **Una sola tabla para los catorce**, no catorce tablas. Incidencias,
 * decisiones, cambios, lecciones, minutas, acciones, supuestos, mediciones:
 * todos son lo mismo —una fecha, un número que se cita, qué pasó, quién
 * responde y en qué estado está—, y lo que cambia entre una incidencia y una
 * lección es de qué se habla, no la estructura.
 *
 * Catorce tablas habrían significado catorce migraciones, catorce modelos,
 * catorce pantallas y catorce lugares donde arreglar el mismo defecto. El tipo
 * va en una columna porque es un dato, no un esquema.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_log_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // El código del catálogo (`config/pmi_documents.php`). Texto y no
            // llave foránea: el catálogo vive en configuración, no en la base.
            $table->string('document_code', 60);

            /*
            | El número que la gente cita: INC-004, DEC-011.
            |
            | Se guarda el número y no el texto. El prefijo vive en
            | `config/pmi_logs.php` y se pega al mostrarlo, así que corregirlo
            | no obliga a reescribir la base — y el orden por número sigue
            | siendo numérico, que es lo que un texto no garantiza.
            */
            $table->unsignedInteger('sequence');

            // Cuándo pasó, no cuándo se capturó. Alguien registra el martes la
            // incidencia del viernes, y el registro tiene que decir viernes.
            $table->date('occurred_on');

            $table->string('title', 200);
            $table->text('detail')->nullable();

            // Del juego de estados de este tipo (`config/pmi_logs.php`). Se
            // valida contra el juego al guardar: un estado que no pertenece
            // dejaría un renglón sin distintivo y sin que nada falle.
            $table->string('status', 30);

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->string('priority', 20)->nullable();

            // El desenlace: cómo se resolvió, qué se decidió, qué se corrigió.
            // Separado del detalle a propósito — leer un registro cerrado es
            // buscar el final, y tenerlo mezclado con el planteamiento obliga a
            // leer el párrafo entero para encontrarlo.
            $table->text('outcome')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Un solo número por tipo y proyecto. Es lo que hace que INC-004
            // signifique una sola cosa dentro de un proyecto.
            $table->unique(['project_id', 'document_code', 'sequence']);

            // La consulta de la pantalla: los renglones de un tipo, del más
            // reciente al más viejo.
            $table->index(['project_id', 'document_code', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_log_entries');
    }
};
