<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las actas de aceptación.
 *
 * La cuarta y última especie del catálogo. Son dos documentos —la aceptación de
 * un entregable y la del proyecto entero— y tienen la misma forma: alguien de
 * fuera del equipo dice **si lo recibe**, con nombre, fecha y reservas si las
 * hay.
 *
 * Lo que las separa de un registro no es la estructura sino lo que pasa al
 * final: **un acta se firma y se congela**. Un registro crece durante el
 * proyecto; un acta cierra algo. Y toda la utilidad de un acta viene de que no
 * se pueda editar después — un documento de aceptación modificable no prueba
 * nada, igual que una línea base o una versión emitida.
 *
 * Por eso `signed_at` no es un adorno: es el interruptor que vuelve el renglón
 * inmutable, y el modelo lo hace cumplir.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_records', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // El código del catálogo (`config/pmi_documents.php`). Texto y no
            // llave foránea: el catálogo vive en configuración, no en la base.
            $table->string('document_code', 60);

            // El número que se cita: ACE-003, ACT-001. Igual que en los
            // registros, se guarda el número y el prefijo se pega al mostrarlo.
            $table->unsignedInteger('sequence');

            $table->string('subject', 200);
            $table->text('detail')->nullable();

            /*
            | El entregable que se acepta.
            |
            | Sin llave foránea dura contra el plan sería imposible rastrear
            | «se acepta el módulo de inventario» hasta la tarea que lo produjo.
            | Se anula si la tarea se borra, y **el acta sobrevive**: lo que se
            | aceptó se aceptó, aunque después alguien reorganice el plan.
            */
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();

            // Aceptado, aceptado con reservas o rechazado. La opción de en medio
            // existe a propósito: sin ella, quien recibe algo casi bueno tiene
            // que aceptarlo entero o rechazarlo entero, y ninguna de las dos es
            // lo que pasó en la junta.
            $table->string('decision', 30);
            $table->text('reservations')->nullable();

            /*
            | Quién acepta. Va como texto y **no** como llave a `users`.
            |
            | Quien recibe un entregable es casi siempre alguien de fuera del
            | equipo del proyecto —un cliente, otra área, un auditor— y
            | frecuentemente no tiene cuenta en el sistema. Exigir un usuario
            | obligaría a crear cuentas falsas para poder cerrar un acta, que es
            | peor que guardar un nombre.
            */
            $table->string('accepted_by_name');
            $table->string('accepted_by_role')->nullable();
            $table->string('accepted_by_org')->nullable();
            $table->date('accepted_on');

            /*
            | El congelado.
            |
            | `signed_at` nulo es un borrador que se puede corregir; con fecha,
            | el renglón es inmutable y el modelo rechaza cualquier cambio.
            |
            | `signed_by` es **quien lo asentó en el sistema**, no quien aceptó:
            | son dos personas distintas casi siempre, y confundirlas haría que
            | el acta afirmara algo que nadie dijo.
            */
            $table->dateTime('signed_at')->nullable();
            $table->foreignId('signed_by')->nullable()->constrained('users')->nullOnDelete();

            // La huella del contenido al firmar. Sirve para demostrar que el
            // renglón es el mismo que se archivó, no para evitar duplicados.
            $table->string('checksum', 64)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'document_code', 'sequence']);
            $table->index(['project_id', 'document_code', 'accepted_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_records');
    }
};
