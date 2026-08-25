<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Support\Tasks\WaitingReason;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * La espera: por qué una tarea no avanza cuando la razón está afuera.
 *
 * Lo que se prueba aquí es el reloj, que es lo único que puede volverse mentira
 * en silencio. Una fecha de espera que se reinicia cuando no debe —o que no se
 * reinicia cuando sí— convierte el seguimiento en ruido, y nadie lo nota hasta
 * que confía en el número.
 *
 * También se prueba que la espera **convive** con el avance y no lo reemplaza:
 * es la decisión de diseño de la que depende todo lo demás.
 */
final class TaskWaitingTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'WAIT-1',
            'name' => 'Proyecto con esperas',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $this->project->members()->attach($this->manager->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $this->project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        $this->project->refresh();
    }

    private function task(string $name = 'Pruebas del módulo', float $percent = 0): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => 2 * self::DAY,
            'percent_complete' => $percent,
            'owner_id' => $this->manager->id,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
        ]);
        $task->save();

        return $task->refresh();
    }

    #[Test]
    public function empezar_una_espera_pone_la_fecha_sola(): void
    {
        $task = $this->task();

        $this->travelTo('2026-03-10 11:00');

        $task->update(['waiting_on' => WaitingReason::Approval->value]);

        $this->assertTrue($task->refresh()->isWaiting());
        $this->assertNotNull($task->waiting_since);
        $this->assertSame('2026-03-10', $task->waiting_since->format('Y-m-d'));
    }

    #[Test]
    public function la_espera_convive_con_el_avance_y_no_lo_reemplaza(): void
    {
        // El caso real: las pruebas ya se mandaron —la tarea va al 85 %— y falta
        // que alguien la dé de alta. Las dos cosas son ciertas a la vez, y es
        // toda la razón por la que la espera no es un cuarto estado.
        $task = $this->task(percent: 85);
        $task->update(['waiting_on' => WaitingReason::ThirdParty->value]);

        $task->refresh();

        $this->assertSame('doing', $task->state());
        $this->assertTrue($task->isWaiting());
        $this->assertSame(WaitingReason::ThirdParty, $task->waitingReason());
    }

    #[Test]
    public function cambiar_el_tipo_de_espera_reinicia_el_reloj(): void
    {
        $task = $this->task();

        $this->travelTo('2026-03-02 09:00');
        $task->update(['waiting_on' => WaitingReason::UserTesting->value]);

        // Tres semanas después el UAT termina y ahora se espera la firma. Es una
        // espera nueva: arrastrar la fecha vieja diría que llevas tres semanas
        // esperando una firma que pediste hoy.
        $this->travelTo('2026-03-23 09:00');
        $task->update(['waiting_on' => WaitingReason::Approval->value]);

        $this->assertSame('2026-03-23', $task->refresh()->waiting_since->format('Y-m-d'));
    }

    #[Test]
    public function corregir_solo_la_nota_no_reinicia_el_reloj(): void
    {
        $task = $this->task();

        $this->travelTo('2026-03-02 09:00');
        $task->update(['waiting_on' => WaitingReason::ThirdParty->value, 'waiting_note' => 'Sistemas']);

        // Sigues esperando lo mismo; solo aclaraste a quién.
        $this->travelTo('2026-03-23 09:00');
        $task->update(['waiting_note' => 'Sistemas — folio 4471']);

        $this->assertSame('2026-03-02', $task->refresh()->waiting_since->format('Y-m-d'));
    }

    #[Test]
    public function cerrar_la_tarea_limpia_la_espera(): void
    {
        $task = $this->task();
        $task->update(['waiting_on' => WaitingReason::Approval->value, 'waiting_note' => 'Ana Ruiz']);

        $task->update(['percent_complete' => 100]);

        $task->refresh();

        // Una tarea terminada no espera a nadie: dejar la espera puesta haría que
        // el Asesor siguiera reclamando seguimiento de algo ya entregado.
        $this->assertFalse($task->isWaiting());
        $this->assertNull($task->waiting_on);
        $this->assertNull($task->waiting_since);
        $this->assertNull($task->waiting_note);
    }

    #[Test]
    public function quitar_la_espera_borra_la_fecha(): void
    {
        $task = $this->task();
        $task->update(['waiting_on' => WaitingReason::ClientResponse->value]);

        $task->update(['waiting_on' => null]);

        $this->assertFalse($task->refresh()->isWaiting());
        $this->assertNull($task->waiting_since);
    }

    #[Test]
    public function se_captura_desde_el_detalle_de_la_tarea(): void
    {
        $task = $this->task();

        $response = $this->actingAs($this->manager)->put(
            route('projects.tasks.update', [$this->project, $task]),
            [
                'name' => $task->name,
                'duration' => '2d',
                'percent_complete' => 85,
                'waiting_on' => WaitingReason::ThirdParty->value,
                'waiting_note' => 'Sistemas, para dar de alta al usuario',
            ],
        );

        $response->assertRedirect();

        $task->refresh();
        $this->assertSame(WaitingReason::ThirdParty, $task->waitingReason());
        $this->assertSame('Sistemas, para dar de alta al usuario', $task->waiting_note);
        $this->assertNotNull($task->waiting_since);
    }

    #[Test]
    public function guardar_desde_la_lista_no_borra_la_espera(): void
    {
        // La Lista no manda el campo. Con `??` en vez de `has`, cualquier
        // guardado desde ahí la conservaría por accidente; con `has`, no mandarla
        // significa «no la toques» y mandarla vacía significa «ya no espero».
        $task = $this->task();
        $task->update(['waiting_on' => WaitingReason::Approval->value]);

        $this->actingAs($this->manager)->put(
            route('projects.tasks.update', [$this->project, $task]),
            ['name' => $task->name, 'duration' => '2d', 'percent_complete' => 40],
        );

        $this->assertTrue($task->refresh()->isWaiting());
    }

    #[Test]
    public function un_tipo_de_espera_inventado_se_rechaza(): void
    {
        $task = $this->task();

        $response = $this->actingAs($this->manager)->put(
            route('projects.tasks.update', [$this->project, $task]),
            ['name' => $task->name, 'duration' => '2d', 'waiting_on' => 'lo-que-sea'],
        );

        $response->assertSessionHasErrors('waiting_on');
        $this->assertFalse($task->refresh()->isWaiting());
    }

    #[Test]
    public function el_distintivo_aparece_en_el_detalle(): void
    {
        $task = $this->task(percent: 85);
        $task->update(['waiting_on' => WaitingReason::UserTesting->value]);

        $response = $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]));

        $response->assertOk();
        $response->assertSee(__('tasks.waiting_uat'));
        // El avance sigue ahí: el distintivo de espera se suma, no sustituye.
        $response->assertSee(__('tasks.state_doing'));
    }

    #[Test]
    public function el_filtro_deja_solo_las_que_esperan(): void
    {
        $waiting = $this->task('La que espera');
        $waiting->update(['waiting_on' => WaitingReason::Approval->value]);

        $this->task('La que avanza');

        $response = $this->actingAs($this->manager)->get(
            route('projects.tasks.index', [$this->project, 'waiting' => 1]),
        );

        $response->assertOk();
        $response->assertSee('La que espera');
        $response->assertDontSee('La que avanza');
    }

    #[Test]
    public function el_asesor_avisa_de_una_espera_de_mas_de_cinco_dias_laborales(): void
    {
        $task = $this->task();

        // Lunes 2 de marzo de 2026. Se pide la firma.
        $this->travelTo('2026-03-02 09:00');
        $task->update(['waiting_on' => WaitingReason::Approval->value]);

        // Lunes 9: seis días laborales después. Nadie volvió a preguntar.
        $this->travelTo('2026-03-09 09:00');

        $findings = app(ProjectAdvisor::class)->analyze($this->project->refresh());

        $this->assertTrue($findings->contains(fn ($finding): bool => $finding->rule === 'task.waiting_too_long'));
    }

    #[Test]
    public function el_asesor_no_avisa_de_una_espera_reciente(): void
    {
        $task = $this->task();

        // Viernes. Se pide la firma.
        $this->travelTo('2026-03-06 09:00');
        $task->update(['waiting_on' => WaitingReason::Approval->value]);

        // Lunes: solo un día laboral después. El fin de semana no es demora.
        $this->travelTo('2026-03-09 09:00');

        $findings = app(ProjectAdvisor::class)->analyze($this->project->refresh());

        $this->assertFalse($findings->contains(fn ($finding): bool => $finding->rule === 'task.waiting_too_long'));
    }

    #[Test]
    public function el_asesor_no_avisa_de_una_tarea_que_no_espera(): void
    {
        $this->task();

        $findings = app(ProjectAdvisor::class)->analyze($this->project->refresh());

        $this->assertFalse($findings->contains(fn ($finding): bool => $finding->rule === 'task.waiting_too_long'));
    }
}
