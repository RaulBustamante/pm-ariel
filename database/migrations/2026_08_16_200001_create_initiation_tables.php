<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El paquete de inicio de un proyecto: por qué existe, quién le importa y qué
 * puede salir mal.
 *
 * Los textos son `text` y no columnas cortas a propósito. Quien escribe una
 * justificación no debe toparse con un límite que lo obligue a resumir mal.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_templates', function (Blueprint $table): void {
            $table->id();

            // Clave estable: los riesgos e interesados típicos se referencian por
            // ella, no por el nombre, que sí se puede traducir o corregir.
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();

            // Las del sistema no se borran desde la interfaz. Un usuario puede
            // agregar las suyas sin tocar código.
            $table->boolean('is_system')->default(false);

            // Riesgos, interesados y entregables típicos del tipo de proyecto.
            $table->json('payload')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('project_charters', function (Blueprint $table): void {
            $table->id();

            // 1:1 con el proyecto. Un proyecto tiene un acta, no varias versiones
            // sueltas; el histórico de cambios vive en la bitácora de auditoría.
            $table->foreignId('project_id')->unique()->constrained('projects')->cascadeOnDelete();

            $table->foreignId('template_id')->nullable()->constrained('project_templates')->nullOnDelete();

            // Paso 1 — por qué existe el proyecto.
            $table->text('problem_statement')->nullable();
            $table->text('opportunity')->nullable();
            $table->text('expected_benefit')->nullable();
            $table->text('alignment')->nullable();

            // Paso 3 — el acta propiamente dicha.
            $table->text('objectives')->nullable();
            $table->text('deliverables')->nullable();
            $table->text('success_criteria')->nullable();
            $table->text('assumptions')->nullable();
            $table->text('constraints')->nullable();
            $table->text('out_of_scope')->nullable();
            $table->text('high_level_milestones')->nullable();

            $table->foreignId('sponsor_id')->nullable()->constrained('users')->nullOnDelete();

            // Dónde se quedó el recorrido. Se puede abandonar y retomar; sin esto
            // el usuario tendría que recordar en qué paso iba.
            $table->string('current_step', 30)->default('justification');
            $table->json('completed_steps')->nullable();

            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('current_step');
        });

        Schema::create('stakeholders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // Un interesado puede ser de fuera: un cliente, un proveedor, una
            // autoridad. Por eso el usuario del sistema es opcional y el nombre no.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('organization')->nullable();
            $table->string('role_title')->nullable();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();

            // Escala de 1 a 5. La matriz clásica es de 2×2, pero obligar a elegir
            // entre "alto" y "bajo" empuja a poner todo en alto.
            $table->unsignedTinyInteger('power')->default(3);
            $table->unsignedTinyInteger('interest')->default(3);

            $table->text('expectations')->nullable();
            $table->text('engagement_strategy')->nullable();
            $table->text('notes')->nullable();

            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'power', 'interest']);
        });

        Schema::create('risks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();

            // Correlativo por proyecto (R-01, R-02). Se cita en juntas y en actas,
            // así que tiene que ser corto y estable.
            $table->string('code', 12);

            $table->string('category', 50)->nullable();
            $table->text('description');
            $table->text('cause')->nullable();
            $table->text('effect')->nullable();

            $table->unsignedTinyInteger('probability')->default(3);
            $table->unsignedTinyInteger('impact')->default(3);

            // Amenaza u oportunidad. Un riesgo positivo también se gestiona, y
            // dejarlo fuera es la razón por la que casi nadie los registra.
            $table->string('kind', 20)->default('threat');
            $table->string('status', 20)->default('identified');

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();

            // De dónde salió: del catálogo de la plantilla o escrito a mano. Sirve
            // para saber si el catálogo está sirviendo de algo.
            $table->string('source', 20)->default('manual');
            $table->string('catalog_key', 80)->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'code']);
            $table->index(['project_id', 'status']);
            $table->index(['probability', 'impact']);
        });

        Schema::create('risk_responses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('risk_id')->constrained('risks')->cascadeOnDelete();

            // evitar / mitigar / transferir / aceptar / escalar
            $table->string('strategy', 20);
            $table->text('description');

            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('pending');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['risk_id', 'status']);
        });
    }

    public function down(): void
    {
        // En orden inverso: cada tabla se va antes que aquella de la que depende.
        Schema::dropIfExists('risk_responses');
        Schema::dropIfExists('risks');
        Schema::dropIfExists('stakeholders');
        Schema::dropIfExists('project_charters');
        Schema::dropIfExists('project_templates');
    }
};
