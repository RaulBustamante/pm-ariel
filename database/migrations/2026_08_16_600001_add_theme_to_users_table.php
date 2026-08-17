<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El tema es del usuario, no del navegador.
 *
 * Guardarlo en la base y no en `localStorage` tiene dos razones. La primera es
 * el parpadeo: si el tema lo decide un script al cargar, la página aparece un
 * instante con el tema equivocado antes de corregirse, y ese parpadeo blanco es
 * exactamente lo que delata a un tema oscuro mal hecho. Con la preferencia en la
 * base, el servidor escribe el atributo en el `<html>` y la página llega ya
 * vestida.
 *
 * La segunda es que sigue a la persona: quien entra desde la computadora de la
 * sala de juntas ve lo mismo que en la suya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // `system` por omisión: mientras nadie elija, manda el sistema
            // operativo. Es lo que la gente ya configuró una vez y no debería
            // tener que volver a decir aquí.
            $table->string('theme', 10)->default(User::THEME_SYSTEM)->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('theme');
        });
    }
};
