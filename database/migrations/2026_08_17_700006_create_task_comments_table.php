<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lo que se fue diciendo de una tarea mientras se trabajaba.
 *
 * **No es lo mismo que las notas.** Las notas (`tasks.description`) son el
 * estado actual de lo que hay que saber para trabajar la tarea: se reescriben.
 * Esto es la conversación, y crece — «el proveedor pidió dos días más», «se
 * revisó con almacén y falta el segundo turno». La diferencia importa porque la
 * pregunta que se hace tres meses después no es «qué dice la tarea» sino «qué
 * pasó aquí».
 *
 * Se guarda quién y cuándo, y **no se edita**: un comentario que se puede
 * reescribir deja de ser un historial y pasa a ser otra nota. Su autor lo puede
 * borrar mientras sea reciente — un dedo o un comentario en la tarea
 * equivocada—, y eso queda en la bitácora de auditoría como todo lo demás.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();

            // El proyecto va repetido a propósito: la pantalla de una tarea
            // comprueba pertenencia contra el proyecto, y sin esta columna cada
            // comprobación costaría una unión con `tasks`.
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->text('body');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // La consulta de la pantalla: la conversación de una tarea, del
            // comentario más reciente al más viejo.
            $table->index(['task_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_comments');
    }
};
