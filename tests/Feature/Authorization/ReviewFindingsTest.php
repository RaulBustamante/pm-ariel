<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Resource;
use App\Models\Risk;
use App\Models\Role;
use App\Models\Stakeholder;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Los hallazgos de la revisión del bloque 5.17, cada uno con la prueba que
 * vuelve a fallar si alguien deshace la corrección.
 *
 * Se agrupan aquí, y no repartidos entre los archivos de cada módulo, porque lo
 * que tienen en común no es el módulo sino el motivo: los seis pasaron
 * inadvertidos durante cinco etapas y ninguna prueba existente los tocaba.
 */
final class ReviewFindingsTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = $this->makeManager('titular@ariel.test');
        $this->project = $this->makeProject('REV-1', $this->manager);
    }

    // --- Hallazgo 2: la cuenta desactivada seguía dentro -------------------

    /**
     * `is_active` se comprobaba solo al momento de entrar. Quien ya estaba
     * dentro seguía trabajando después de que lo dieran de baja, que es
     * justamente el día en que más importa que no pueda.
     */
    #[Test]
    public function a_deactivated_account_is_thrown_out_on_the_next_request(): void
    {
        $this->actingAs($this->manager)->get(route('dashboard'))->assertOk();

        $this->manager->forceFill(['is_active' => false])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    /**
     * No basta con negar el paso: hay que cerrar la sesión. Si sobreviviera,
     * reactivar la cuenta reanudaría una sesión vieja sin pedir contraseña.
     */
    #[Test]
    public function reactivating_the_account_does_not_resume_the_old_session(): void
    {
        $this->actingAs($this->manager)->get(route('dashboard'));

        $this->manager->forceFill(['is_active' => false])->save();
        $this->get(route('dashboard'));

        $this->manager->forceFill(['is_active' => true])->save();

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    #[Test]
    public function an_active_account_is_not_disturbed(): void
    {
        $this->actingAs($this->manager)->get(route('projects.index'))->assertOk();
    }

    // --- Hallazgo 3: N+1 en la lista de proyectos --------------------------

    /**
     * El semáforo de inicio de la última columna recorre interesados y riesgos.
     * Sin traerlos con la consulta principal, cada renglón disparaba tres
     * consultas más, y el guardia de carga perezosa no lo veía porque
     * `loadMissing` es una carga explícita.
     *
     * Se fija un techo en vez de un número exacto: la cifra depende de detalles
     * que van a cambiar, pero que no crezca con los renglones no debe cambiar.
     */
    #[Test]
    public function the_project_list_does_not_query_once_per_row(): void
    {
        for ($i = 2; $i <= 9; $i++) {
            $project = $this->makeProject("REV-{$i}", $this->manager);
            Stakeholder::query()->create([
                'project_id' => $project->id,
                'name' => "Interesado {$i}",
                'power' => 3,
                'interest' => 3,
            ]);
        }

        $admin = $this->makeAdmin();

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('projects.index'))->assertOk();
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertLessThan(
            30,
            $queries,
            "La lista de nueve proyectos hizo {$queries} consultas. Si crece con el número de "
            .'renglones, alguien quitó una relación de la carga anticipada en ProjectController::index.',
        );
    }

    // --- Hallazgo 6: pertenencia del recurso al desasignar -----------------

    #[Test]
    public function a_resource_from_another_project_cannot_be_used_to_unassign(): void
    {
        $task = $this->makeTask($this->project, 'Tarea propia');

        $otherProject = $this->makeProject('REV-OTRO', $this->makeManager('otro@ariel.test'));
        $foreign = Resource::query()->create([
            'project_id' => $otherProject->id,
            'name' => 'Recurso ajeno',
            'type' => Resource::TYPE_PERSON,
            'capacity_percent' => 100,
        ]);

        $this->actingAs($this->manager)
            ->delete(route('projects.assignments.destroy', [$this->project, $task, $foreign]))
            ->assertNotFound();
    }

    // --- Hallazgo 5: pertenencia en todas las rutas anidadas ---------------

    /**
     * Laravel no acota los enlaces de modelo anidados por sí solo: en
     * `projects/{project}/risks/{risk}` el riesgo se busca en toda la tabla, no
     * dentro del proyecto. Cada controlador lo comprueba a mano, y esa es la
     * clase de comprobación que la ruta número trece olvida.
     *
     * Esta prueba recorre un hijo de cada tipo. Cuando se agregue otro modelo
     * anidado, agregar aquí su renglón cuesta un minuto; descubrir el hueco en
     * producción cuesta bastante más.
     */
    #[Test]
    public function no_nested_route_reaches_a_child_of_another_project(): void
    {
        $otherManager = $this->makeManager('ajeno@ariel.test');
        $other = $this->makeProject('REV-AJENO', $otherManager);

        $foreignRisk = Risk::query()->create([
            'project_id' => $other->id,
            'code' => 'R-01',
            'description' => 'Riesgo ajeno',
            'probability' => 3,
            'impact' => 3,
        ]);

        $foreignStakeholder = Stakeholder::query()->create([
            'project_id' => $other->id,
            'name' => 'Interesado ajeno',
            'power' => 3,
            'interest' => 3,
        ]);

        $foreignTask = $this->makeTask($other, 'Tarea ajena');

        $foreignCalendar = Calendar::query()->where('project_id', $other->id)->firstOrFail();

        $foreignResource = Resource::query()->create([
            'project_id' => $other->id,
            'name' => 'Recurso ajeno',
            'type' => Resource::TYPE_PERSON,
            'capacity_percent' => 100,
        ]);

        // El titular del proyecto propio: tiene permiso sobre `REV-1` y ninguno
        // sobre los hijos de `REV-AJENO`. Es el caso peligroso —no un extraño,
        // sino alguien que sí puede entrar por la puerta de enfrente.
        $this->actingAs($this->manager);

        $attempts = [
            'riesgo' => ['delete', route('projects.risks.destroy', [$this->project, $foreignRisk])],
            'interesado' => ['delete', route('projects.stakeholders.destroy', [$this->project, $foreignStakeholder])],
            'tarea' => ['get', route('projects.tasks.show', [$this->project, $foreignTask])],
            'calendario' => ['post', route('projects.calendars.default', [$this->project, $foreignCalendar])],
            'recurso' => ['delete', route('projects.resources.destroy', [$this->project, $foreignResource])],
        ];

        foreach ($attempts as $what => [$verb, $url]) {
            $status = $this->{$verb}($url)->getStatusCode();

            $this->assertSame(
                404,
                $status,
                "Se alcanzó un {$what} de otro proyecto por una ruta anidada: contestó {$status}. "
                .'Un 403 tampoco sirve: distinguirlo de un 404 revela qué identificadores existen.',
            );
        }

        // Y nada se borró de paso.
        $this->assertDatabaseHas('risks', ['id' => $foreignRisk->id, 'deleted_at' => null]);
        $this->assertDatabaseHas('stakeholders', ['id' => $foreignStakeholder->id, 'deleted_at' => null]);
    }

    // --- Armado -----------------------------------------------------------

    private function makeManager(string $email): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        return $user;
    }

    private function makeAdmin(): User
    {
        $user = User::factory()->create([
            'email' => 'admin@ariel.test',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        return $user;
    }

    private function makeProject(string $code, User $owner): Project
    {
        $project = Project::query()->create([
            'code' => $code,
            'name' => "Proyecto {$code}",
            'owner_id' => $owner->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $project->members()->attach($owner->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Calendario',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        return $project;
    }

    private function makeTask(Project $project, string $name): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $project->id,
            'name' => $name,
            'duration_minutes' => 540,
            'sort_order' => 0,
        ]);
        $task->save();

        return $task;
    }
}
