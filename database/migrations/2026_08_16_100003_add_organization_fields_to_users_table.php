<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Nullable a propósito: con SSO la contraseña no vive en esta base.
            // Ver ARCHITECTURE.md, "Preparación para SSO".
            $table->string('password')->nullable()->change();

            $table->string('auth_provider', 30)->default('local')->after('password');
            $table->string('external_id', 191)->nullable()->after('auth_provider');

            $table->string('locale', 5)->default('es')->after('external_id');
            $table->string('timezone', 64)->default('America/Tijuana')->after('locale');

            // Modo Simple por defecto: el usuario nuevo no debería toparse con
            // holguras ni restricciones el primer día.
            $table->boolean('expert_mode')->default(false)->after('timezone');

            $table->foreignId('position_id')->nullable()->after('expert_mode')
                ->constrained('positions')->nullOnDelete();
            $table->foreignId('org_unit_id')->nullable()->after('position_id')
                ->constrained('org_units')->nullOnDelete();

            $table->boolean('is_active')->default(true)->after('org_unit_id');

            // La contraseña que asigna un administrador nace temporal: mientras
            // siga vigente, dos personas conocen la cuenta.
            $table->boolean('must_change_password')->default(false)->after('is_active');

            $table->foreignId('created_by')->nullable()->after('must_change_password')
                ->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')
                ->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->unique(['auth_provider', 'external_id']);
            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropUnique(['auth_provider', 'external_id']);
            $table->dropIndex(['is_active']);

            $table->dropConstrainedForeignId('position_id');
            $table->dropConstrainedForeignId('org_unit_id');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');

            $table->dropColumn([
                'auth_provider', 'external_id', 'locale', 'timezone', 'expert_mode',
                'is_active', 'must_change_password',
            ]);
            $table->dropSoftDeletes();

            $table->string('password')->nullable(false)->change();
        });
    }
};
