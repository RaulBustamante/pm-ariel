<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();

            // Formato "recurso.acción": projects.view, costs.manage, ...
            $table->string('name', 100)->unique();
            $table->string('group', 50);
            $table->timestamps();

            $table->index('group');
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 50)->unique();

            // Los roles semilla no se pueden borrar ni renombrar desde la interfaz:
            // hay Policies y pruebas que dependen de sus nombres.
            $table->boolean('is_system')->default(false);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('role_permission', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('permission_id')->constrained('permissions')->cascadeOnDelete();

            $table->unique(['role_id', 'permission_id']);
        });

        Schema::create('user_role', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_role');
        Schema::dropIfExists('role_permission');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
