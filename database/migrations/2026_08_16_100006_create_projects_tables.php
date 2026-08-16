<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Solo el esquema mínimo que necesitan las reglas de visibilidad (CL-008).
 * El CRUD, las pantallas y la lógica de proyectos son de la Etapa 3.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');

            $table->foreignId('owner_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->nullOnDelete();

            // Moneda operativa configurable desde el arranque: Ariel opera en
            // frontera y no se asume MXN. Sin conversión automática hasta Fase 2.
            $table->char('currency', 3)->default('MXN');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'owner_id']);
            $table->index('org_unit_id');
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();

            // Ser jefe da lectura; ser miembro con este rol da escritura.
            $table->string('project_role', 20)->default('member');

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'project_role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('projects');
    }
};
