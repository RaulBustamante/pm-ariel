<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BuzonTicket;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BuzonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    #[Test]
    public function authenticated_pages_include_the_floating_mailbox(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-buzon-open', false)
            ->assertSee('Reportar un error');

        $this->get(route('login'))->assertDontSee('data-buzon-open', false);
    }

    #[Test]
    public function a_user_can_report_an_error_with_context_and_an_image(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('buzon.store'), [
            'tipo' => 'error',
            'titulo' => 'No puedo guardar el avance',
            'descripcion' => 'Al presionar guardar la pantalla no responde.',
            'severidad' => 'bloqueante',
            'url' => 'https://tesseract.test/projects/1',
            'ruta_nombre' => 'projects.edit',
            'contexto' => json_encode([
                'userAgent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/126.0.0.0 Safari/537.36',
                'resolucion' => '1920x1080',
                'errores' => [['mensaje' => 'Failed', 'archivo' => 'app.js', 'linea' => 42]],
            ]),
            'imagen' => UploadedFile::fake()->image('pantalla.png'),
        ])->assertRedirect()->assertSessionHas('buzon_enviado');

        $ticket = BuzonTicket::query()->firstOrFail();
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame('Windows 10/11', $ticket->sistema_operativo);
        $this->assertStringStartsWith('BZN-', $ticket->folio);
        $this->assertCount(1, $ticket->adjuntos);
        Storage::disk('local')->assertExists($ticket->adjuntos->first()->ruta_archivo);
    }

    #[Test]
    public function only_an_admin_can_manage_the_board(): void
    {
        $member = User::factory()->create();
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));
        $ticket = BuzonTicket::query()->create([
            'user_id' => $member->id, 'folio' => 'BZN-2026-00001', 'tipo' => 'sugerencia',
            'titulo' => 'Agregar un filtro rápido', 'descripcion' => 'Ayudaría a encontrar proyectos.', 'estado' => 'nuevo',
        ]);

        $this->actingAs($member)->get(route('admin.buzon.index'))->assertForbidden();
        $this->actingAs($admin)->get(route('admin.buzon.index'))->assertOk()->assertSee($ticket->titulo);
        $this->actingAs($admin)->patch(route('admin.buzon.update', $ticket), [
            'estado' => 'resuelto', 'asignado_a' => $admin->id, 'notas_internas' => 'Corregido.',
        ])->assertRedirect();

        $this->assertSame('resuelto', $ticket->fresh()->estado);
        $this->assertNotNull($ticket->fresh()->resuelto_en);

        $this->actingAs($admin)->patchJson(route('admin.buzon.update', $ticket), [
            'estado' => 'en_revision',
        ])->assertOk();

        $this->assertSame('Corregido.', $ticket->fresh()->notas_internas);
        $this->assertSame($admin->id, $ticket->fresh()->asignado_a);
    }
}
