<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

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
 * «Mi semana» mezcla tareas de todos los proyectos, y hay que poder saber de
 * cuál es cada una.
 *
 * El renglón ya traía el dato, pero decía solo el código —«GP-06»—, que es
 * correlativo: identifica el proyecto sin nombrarlo, y obliga a recordar de
 * memoria una tabla de equivalencias para leer la propia lista de pendientes.
 *
 * Lo que se prueba aquí es que el nombre esté **escrito en la página**, y no
 * escondido detrás de un `title` o de un color. Un dato que solo aparece al
 * pasar el ratón no existe en un celular ni para quien navega con teclado, y
 * ese es justo el error que era fácil cometer al arreglar esto.
 */
final class MyWeekProjectTagTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->user = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->user->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));
    }

    private function projectWithLateTask(string $code, string $name, string $taskName): Project
    {
        $project = Project::query()->create([
            'code' => $code,
            'name' => $name,
            'owner_id' => $this->user->id,
            'planned_start' => '2020-01-06 09:00',
        ]);

        $project->members()->attach($this->user->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        // Una tarea vencida y sin terminar: cae en «atrasadas», que es la lista
        // que de verdad se llena y donde el problema se sentía.
        $this->lateTask($project, $taskName, $this->user);

        return $project;
    }

    /**
     * Las fechas calculadas se escriben a la fuerza y no con `create`: son del
     * motor de programación, no del formulario, y por eso no son `fillable`.
     * Correr el programador aquí sería pedirle que calcule un plan real para
     * probar el color de un punto.
     */
    private function lateTask(Project $project, string $name, User $owner): Task
    {
        $task = new Task;
        $task->forceFill([
            'project_id' => $project->id,
            'name' => $name,
            'duration_minutes' => 540,
            'owner_id' => $owner->id,
            'percent_complete' => 0,
            'sort_order' => 1,
            'is_summary' => false,
            'early_start' => '2020-01-06 09:00',
            'early_finish' => '2020-01-06 18:00',
        ]);
        $task->save();

        return $task;
    }

    #[Test]
    public function each_task_says_the_name_of_its_project_not_only_the_code(): void
    {
        $this->projectWithLateTask('GP-06', 'Generacion de Reportes', 'Queries needed please');
        $this->projectWithLateTask('GP-07', 'Replace .76 with Dashboard', 'dashboard 100% funcional');

        $response = $this->actingAs($this->user)->get(route('dashboard'))->assertOk();

        // El nombre, escrito en la página. Esta es la afirmación que importa.
        $response->assertSee('Generacion de Reportes')
            ->assertSee('Replace .76 with Dashboard');
    }

    #[Test]
    public function two_projects_that_look_the_same_once_truncated_get_different_colors(): void
    {
        // El caso real que hizo falta el color: cortados a lo que cabe en una
        // tarjeta angosta, estos dos nombres quedan idénticos.
        $iso = $this->projectWithLateTask('GP-01', 'Implementacion de ISO', 'revisar con Karen');
        $ia = $this->projectWithLateTask('GP-05', 'Implementacion de IA', 'Solicitar usuarios');

        $this->assertNotSame(
            $iso->swatch(),
            $ia->swatch(),
            'Dos proyectos que se cortan iguales necesitan tonos distintos: el color es lo unico que los separa de un vistazo.',
        );

        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('background-color: '.$iso->swatch(), false)
            ->assertSee('background-color: '.$ia->swatch(), false);
    }

    #[Test]
    public function the_color_does_not_change_when_the_project_is_renamed(): void
    {
        $project = $this->projectWithLateTask('GP-06', 'Generacion de Reportes', 'Queries needed please');
        $before = $project->swatch();

        $project->update(['name' => 'Reportes de operacion', 'code' => 'REPORTES']);

        // El color es lo que la gente aprende a reconocer de un vistazo. Si
        // saliera de un hash del nombre, corregir una falta de ortografía le
        // cambiaría el color al proyecto y habría que reaprenderlo.
        $this->assertSame($before, $project->fresh()->swatch());
    }

    #[Test]
    public function the_full_name_is_kept_within_reach_for_when_it_gets_cut_off(): void
    {
        $this->projectWithLateTask('GP-03', 'Proyectos Inapp (ARIEL 3, CREDITAPP)', 'User Creation');

        // El `title` es el complemento, nunca el sustituto: el nombre ya se
        // probó visible arriba, y esto solo cubre el recorte.
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('title="Proyectos Inapp (ARIEL 3, CREDITAPP) · GP-03"', false);
    }

    #[Test]
    public function the_team_table_names_the_project_in_its_project_column(): void
    {
        $project = $this->projectWithLateTask('GP-04', 'Desarrollo con ARIEL QMS', 'Ajustar riesgos');

        $subordinate = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $subordinate->roles()->attach(Role::query()->where('name', Role::TEAM_MEMBER)->value('id'));
        $this->user->directReports()->attach($subordinate->id, ['effective_from' => now()->subMonth()]);
        $project->members()->attach($subordinate->id, ['project_role' => Project::ROLE_MEMBER]);

        $this->lateTask($project, 'Lo de mi gente', $subordinate);

        // La columna se llama «Proyecto»: decía un código correlativo, y aquí
        // hay ancho de sobra para el nombre.
        $this->actingAs($this->user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Lo de mi gente')
            ->assertSee('Desarrollo con ARIEL QMS');
    }

    #[Test]
    public function a_task_whose_project_vanished_does_not_break_the_home_page(): void
    {
        $project = $this->projectWithLateTask('GP-09', 'Se va a borrar', 'Tarea huerfana');

        // `project` viene de una relación, así que puede llegar nula. El
        // componente tiene que aguantarlo: el inicio es la primera pantalla que
        // se abre y no puede ser la que reviente.
        $project->delete();

        $this->actingAs($this->user)->get(route('dashboard'))->assertOk();
    }
}
