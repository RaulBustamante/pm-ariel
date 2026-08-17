<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las versiones emitidas de cada documento.
 *
 * Sin esto, el corte semanal que alguien mandó el viernes **no existe en ningún
 * lado** la semana siguiente: el sistema lo vuelve a generar con los datos de
 * hoy, que ya son otros. Y la pregunta que se hace en una junta no es «cómo
 * vamos» sino «qué dijimos que íbamos hace tres semanas».
 *
 * **Emitir es un acto deliberado**, no un efecto de descargar. Bajar el PDF para
 * revisarlo es un borrador; emitirlo es comprometerse con lo que dice. Archivar
 * cada descarga llenaría el disco de borradores y volvería inútil la lista justo
 * cuando hiciera falta encontrar el bueno. Es además como funciona el control de
 * documentos del PMI: una versión se emite, no se acumula.
 *
 * El renglón es **inmutable**, igual que una línea base. Toda su utilidad viene
 * de eso: un archivo que se puede editar después no prueba nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // El código del catálogo (`config/pmi_documents.php`). Texto y no
            // llave foránea: el catálogo vive en configuración, no en la base, y
            // un documento que se retire del catálogo no debe borrar el
            // histórico de lo que ya se emitió con su nombre.
            $table->string('document_code', 60);

            // El número de versión dentro del proyecto y del documento: 1, 2, 3.
            // Es lo que la gente cita en una junta, mucho antes que una fecha.
            $table->unsignedInteger('version');

            $table->string('title');
            $table->dateTime('issued_at');
            $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();

            $table->string('stored_path');
            $table->unsignedBigInteger('byte_size')->default(0);
            $table->string('checksum', 64)->nullable();

            /*
            | Las cifras de portada, congeladas.
            |
            | Permiten leer la lista —«al 62 %, con 14 días de atraso»— sin abrir
            | siete PDF para encontrar el que se busca. Y sobreviven al archivo:
            | si algún día se depuran los binarios viejos, el registro sigue
            | diciendo qué decía cada versión.
            */
            $table->json('summary')->nullable();

            $table->text('notes')->nullable();
            $table->timestamps();

            // Una sola versión con el mismo número por documento y proyecto.
            $table->unique(['project_id', 'document_code', 'version']);
            $table->index(['project_id', 'issued_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_issues');
    }
};
