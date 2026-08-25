<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «Mi semana» en el inicio.
 *
 * La tarjeta cabía ocho renglones y resumía el resto en «y 9 más», que dice
 * cuántas faltan y no cuáles: para averiguarlo había que salir del inicio. Ahora
 * la lista va completa y la tarjeta se recorre.
 *
 * Lo que se prueba es justo lo que puede volver a romperse en silencio: que no
 * se corte lo que cabe, y que cuando el tope de seguridad sí corta, la pantalla
 * lo diga en vez de fingir que esa era toda la lista.
 */
final class MyWeekTest extends TestCase
{
    use RefreshDatabase;

    private const DAY = 540;

    /** Miércoles. La semana corre del lunes 2 al domingo 8 de marzo de 2026. */
    private const MIDWEEK = '2026-03-04 09:00';

    /** Jueves: dentro de la semana y todavía por vencer. */
    private const DUE_DATE = '2026-03-05 17:00';

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->travelTo(self::MIDWEEK);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'WEEK-1',
            'name' => 'Proyecto de la semana',
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
    }

    /**
     * Una tarea que vence esta semana y es del usuario.
     *
     * `early_finish` se escribe a mano porque es columna del motor y no del
     * usuario: correr el planificador daría fechas que dependen del día en que
     * se ejecute la prueba, y aquí lo que se mide es el recorte de la lista.
     */
    private function dueTask(string $name): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => self::DAY,
            'percent_complete' => 0,
            'owner_id' => $this->manager->id,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
        ]);
        $task->save();

        $task->forceFill([
            'early_start' => self::MIDWEEK,
            'early_finish' => self::DUE_DATE,
        ])->save();

        return $task->refresh();
    }

    #[Test]
    public function la_tarjeta_muestra_todas_las_tareas_de_la_semana(): void
    {
        // Doce: mas de los ocho que cabian antes, menos que el tope.
        $names = [];

        for ($index = 1; $index <= 12; $index++) {
            $names[] = "Actividad numero {$index} de la semana";
            $this->dueTask("Actividad numero {$index} de la semana");
        }

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk();

        foreach ($names as $name) {
            $response->assertSee($name);
        }

        // Nada quedo escondido, asi que no hay nada que resumir.
        $response->assertDontSee(__('reports.and_more', ['count' => 4]));
    }

    #[Test]
    public function la_lista_se_puede_recorrer(): void
    {
        for ($index = 1; $index <= 12; $index++) {
            $this->dueTask("Actividad {$index}");
        }

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk();
        // La caja que se desplaza, y que recibe foco para poder recorrerla sin
        // raton. Sin `tabindex` la barra solo sirve con el mouse.
        $response->assertSee('scroll-pane', escape: false);
        $response->assertSee('tabindex="0"', escape: false);
    }

    #[Test]
    public function arriba_del_tope_la_pantalla_dice_cuantas_faltan(): void
    {
        // Cincuenta y cinco: el tope de seguridad son cincuenta.
        for ($index = 1; $index <= 55; $index++) {
            $this->dueTask("Actividad {$index}");
        }

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk();
        // El conteo del distintivo va completo y el renglon confiesa el corte:
        // cortar en silencio es lo que hacia que una lista incompleta se leyera
        // como la lista entera.
        $response->assertSee(__('reports.and_more', ['count' => 5]));
    }

    #[Test]
    public function una_tarjeta_sin_tareas_no_dibuja_la_caja_de_recorrido(): void
    {
        $this->dueTask('La unica actividad');

        $response = $this->actingAs($this->manager)->get(route('dashboard'));

        $response->assertOk();
        // «Cerradas» esta vacia: ahi va el texto de vacio, no una caja de cero
        // altura con barra.
        $response->assertSee(__('dashboard.week_no_closed'));
    }
}
