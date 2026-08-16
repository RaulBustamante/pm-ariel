<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');

            // Nivel jerárquico nominal del puesto. No define visibilidad: eso lo
            // resuelve user_hierarchy, que es la relación real jefe-subordinado.
            $table->unsignedSmallInteger('level')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('level');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
