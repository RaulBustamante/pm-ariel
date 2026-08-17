<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Recursos, asignaciones y los hallazgos del motor de avisos.
 *
 * Un recurso no es un usuario: puede ser una persona sin cuenta, una máquina o
 * un proveedor. Por eso `user_id` es opcional y el nombre no — igual que con los
 * interesados. Atar los recursos a las cuentas del sistema obligaría a dar de
 * alta usuarios que nunca van a entrar, solo para poder asignarles trabajo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();

            $table->string('name');
            $table->string('type', 20)->default('person');
            $table->string('role_title')->nullable();
            $table->string('email')->nullable();

            // Cuánto puede dar al día, en porcentaje. 100 es de tiempo completo;
            // 50 es medio tiempo. Es lo que se compara contra lo asignado.
            $table->unsignedSmallInteger('capacity_percent')->default(100);

            $table->decimal('cost_per_hour', 12, 2)->nullable();
            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'is_active']);
        });

        Schema::create('task_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();

            // Qué parte de su jornada dedica a esta tarea. Dos tareas al 60 %
            // cada una son 120 %: eso es una sobreasignación.
            $table->unsignedSmallInteger('units_percent')->default(100);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['task_id', 'resource_id']);
            $table->index('resource_id');
        });

        Schema::create('project_findings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // La regla que lo produjo. Estable, para poder silenciar una regla
            // concreta después sin adivinar cuál era.
            $table->string('rule', 60);
            $table->string('severity', 20)->default('warning');

            $table->text('message');
            $table->text('why');

            // A qué apunta: tarea, recurso, o nada si es del proyecto entero.
            $table->foreignId('task_id')->nullable()->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->cascadeOnDelete();

            // Espacio para la accion sugerida, **hoy vacio a proposito** (D-017).
            // Detectar es aritmetica verificable; recomendar es un juicio que sin
            // historial real de Ariel no tiene con que respaldarse.
            $table->text('suggested_action')->nullable();

            $table->timestamp('detected_at');

            $table->timestamps();

            $table->index(['project_id', 'severity']);
            $table->index(['project_id', 'rule']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_findings');
        Schema::dropIfExists('task_assignments');
        Schema::dropIfExists('resources');
    }
};
