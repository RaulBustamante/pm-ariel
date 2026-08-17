<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El plan: tareas, ligas, calendarios, líneas base y la bitácora de cálculos.
 *
 * **Las fechas calculadas se guardan, no se recalculan al vuelo.** Un Gantt que
 * dispara el motor en cada carga es un Gantt que se siente lento; y peor, dos
 * pantallas abiertas podrían mostrar fechas distintas si algo cambió entre una
 * y otra. El cálculo se dispara al editar y su resultado queda escrito, con el
 * `schedule_run` que lo produjo — así siempre se puede responder "¿de cuándo son
 * estas fechas y qué las generó?".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            // Desde cuándo se puede empezar a trabajar. Sin esto el cálculo
            // tendría que suponer "hoy", y el mismo plan daría fechas distintas
            // cada día que alguien lo abriera.
            $table->dateTime('planned_start')->nullable()->after('status');
            $table->dateTime('planned_finish')->nullable()->after('planned_start');
        });

        Schema::create('calendars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->nullable()->constrained('projects')->cascadeOnDelete();

            $table->string('name');
            $table->string('key', 50);
            $table->string('timezone', 64)->default('America/Mexico_City');

            // Turnos por día de la semana y excepciones, como los entiende
            // WorkingCalendar. En JSON porque son la configuración de un objeto
            // de dominio, no entidades que alguien vaya a consultar por separado.
            $table->json('week');
            $table->json('exceptions')->nullable();

            // El del proyecto cuando una tarea no trae calendario propio.
            $table->boolean('is_default')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'key']);
        });

        Schema::create('tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->cascadeOnDelete();

            // Derivado: lo recalcula WbsNumberer. Se guarda porque se ordena y se
            // busca por él, no porque sea fuente de verdad.
            $table->string('wbs_code', 50)->nullable();
            $table->unsignedInteger('sort_order')->default(0);

            $table->string('name');
            $table->text('description')->nullable();

            // Minutos de trabajo. Cero es un hito.
            $table->unsignedInteger('duration_minutes')->default(0);

            $table->string('constraint_type', 10)->default('ASAP');
            $table->dateTime('constraint_date')->nullable();

            $table->foreignId('calendar_id')->nullable()->constrained('calendars')->nullOnDelete();

            // --- Resultado del último cálculo -----------------------------
            $table->dateTime('early_start')->nullable();
            $table->dateTime('early_finish')->nullable();
            $table->dateTime('late_start')->nullable();
            $table->dateTime('late_finish')->nullable();
            $table->integer('total_float_minutes')->nullable();
            $table->integer('free_float_minutes')->nullable();
            $table->boolean('is_critical')->default(false);
            $table->boolean('is_summary')->default(false);

            // --- Captura del usuario --------------------------------------
            $table->decimal('cost', 14, 2)->default(0);
            $table->decimal('percent_complete', 5, 2)->default(0);
            $table->dateTime('actual_start')->nullable();
            $table->dateTime('actual_finish')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'sort_order']);
            $table->index(['project_id', 'wbs_code']);
            $table->index(['project_id', 'is_critical']);
            $table->index('parent_id');
        });

        Schema::create('task_dependencies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('predecessor_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('successor_id')->constrained('tasks')->cascadeOnDelete();

            $table->string('type', 2)->default('FS');

            // Minutos de trabajo. Negativo es adelanto.
            $table->integer('lag_minutes')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            // Dos ligas iguales entre las mismas tareas no significan nada
            // distinto de una sola, y en cambio duplican el cálculo.
            $table->unique(['predecessor_id', 'successor_id', 'type'], 'task_dependencies_unique_link');
            $table->index(['project_id', 'successor_id']);
        });

        Schema::create('schedule_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->dateTime('project_start');
            $table->dateTime('project_finish')->nullable();

            $table->unsignedInteger('task_count')->default(0);
            $table->unsignedInteger('critical_task_count')->default(0);
            $table->decimal('elapsed_ms', 10, 3)->default(0);

            $table->string('status', 20)->default('ok');

            // Cuando falla por un ciclo, aquí queda cuál era. Sin esto, el
            // usuario ve "no se pudo calcular" y nadie puede reconstruir por qué.
            $table->text('failure_reason')->nullable();
            $table->json('failure_cycle')->nullable();

            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
        });

        Schema::create('baselines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            $table->string('name');
            $table->text('notes')->nullable();
            $table->dateTime('captured_at');
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();

            $table->dateTime('project_start')->nullable();
            $table->dateTime('project_finish')->nullable();
            $table->decimal('total_cost', 16, 2)->default(0);

            // Varias por proyecto: la original y las que se aprueben después.
            // Solo una es la vigente para comparar.
            $table->boolean('is_active')->default(false);

            $table->timestamps();

            $table->index(['project_id', 'is_active']);
        });

        Schema::create('baseline_tasks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('baseline_id')->constrained('baselines')->cascadeOnDelete();

            // Sin llave foránea a `tasks` a propósito: si la tarea se borra, la
            // línea base debe seguir diciendo qué se había comprometido. Una
            // línea base que se modifica cuando cambia el plan no es una línea
            // base, es una copia del plan.
            $table->unsignedBigInteger('task_id');
            $table->string('wbs_code', 50)->nullable();
            $table->string('name');

            $table->dateTime('start')->nullable();
            $table->dateTime('finish')->nullable();
            $table->unsignedInteger('duration_minutes')->default(0);
            $table->decimal('cost', 14, 2)->default(0);

            $table->timestamps();

            $table->unique(['baseline_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('baseline_tasks');
        Schema::dropIfExists('baselines');
        Schema::dropIfExists('schedule_runs');
        Schema::dropIfExists('task_dependencies');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('calendars');

        // Se comprueba antes de soltar: si la migración se aplicó en una versión
        // anterior que no traía estas columnas, un `down()` ciego se atora y deja
        // la base a medio revertir — con las tablas ya borradas y la migración
        // todavía marcada como aplicada.
        Schema::table('projects', function (Blueprint $table): void {
            foreach (['planned_start', 'planned_finish'] as $column) {
                if (Schema::hasColumn('projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
