<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Los requisitos del proyecto, uno por renglón.
 *
 * **Por qué no basta con el documento narrativo.** «Documentación de requisitos»
 * ya existe y se redacta como texto — sirve para explicar el alcance a una
 * persona. Lo que no se puede hacer con un párrafo es la **matriz de
 * trazabilidad**: contestar «¿qué tarea entrega este requisito?» y, al revés,
 * «¿este trabajo de quién salió?». Para eso hacen falta requisitos discretos con
 * clave propia.
 *
 * Es la diferencia entre decir que el alcance está documentado y poder
 * demostrar, renglón por renglón, que cada cosa que se pidió tiene quién la
 * entregue — y que nada de lo que se está construyendo salió de la nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_requirements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // El número que se cita: REQ-004. Igual que en los registros y las
            // actas, se guarda el número y el prefijo se pega al mostrarlo.
            $table->unsignedInteger('sequence');

            $table->string('description', 500);

            // De dónde salió: un interesado, una norma, el acta. Un requisito
            // sin origen no se puede negociar cuando hay que recortar alcance,
            // porque nadie sabe a quién habría que convencer.
            $table->string('origin')->nullable();

            $table->string('priority', 20)->default('should');
            $table->string('category', 40)->nullable();

            /*
            | La tarea que lo entrega.
            |
            | Nulo significa **requisito huérfano**: se pidió y nadie lo está
            | construyendo. Es el hallazgo que la matriz de trazabilidad existe
            | para producir, así que la columna tiene que poder estar vacía —
            | obligar a ligarlo forzaría a inventar una tarea para poder
            | guardarlo, y el hueco dejaría de verse.
            */
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();

            // Cómo se comprueba que quedó cumplido. Sin esto, «entregado» es la
            // opinión de quien entregó.
            $table->text('acceptance_criteria')->nullable();

            $table->string('status', 20)->default('proposed');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'sequence']);
            $table->index(['project_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_requirements');
    }
};
