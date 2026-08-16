<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_hierarchy', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manager_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subordinate_id')->constrained('users')->cascadeOnDelete();

            // Con vigencia: un cambio de jefe no borra el histórico, que es lo que
            // permite explicar por qué alguien veía cierto proyecto el mes pasado.
            $table->date('effective_from');
            $table->date('effective_to')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['manager_id', 'subordinate_id', 'effective_from'], 'user_hierarchy_unique_relation');
            $table->index(['manager_id', 'effective_to']);
            $table->index(['subordinate_id', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_hierarchy');
    }
};
