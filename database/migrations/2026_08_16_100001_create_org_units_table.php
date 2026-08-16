<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('org_units', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('org_units')->nullOnDelete();
            $table->string('name');
            $table->string('code', 50)->nullable();

            // Ruta materializada ("/1/4/9/") para consultar un subárbol completo
            // sin recursión. Se recalcula al mover una unidad.
            $table->string('path', 500)->nullable();
            $table->unsignedSmallInteger('depth')->default(0);
            $table->unsignedInteger('sort_order')->default(0);

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['parent_id', 'sort_order']);
            $table->index('path');
            $table->unique('code');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('org_units');
    }
};
