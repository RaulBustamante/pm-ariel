<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Borrar un proyecto: quién puede, y qué protege del descuido.
 */
final class ProjectDeletionTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->manager = $this->makeUser('gerente@ariel.test', Role::PROJECT_MANAGER);
        $this->project = $this->makeProject('DEL-1', $this->manager);
    }

    #[Test]
    public function the_manager_can_delete_typing_the_code(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('projects.destroy', $this->project), ['confirmation' => 'DEL-1'])
            ->assertRedirect(route('projects.index'));

        $this->assertSoftDeleted('projects', ['id' => $this->project->id]);
    }

    /**
     * Se pide escribir la clave y no un «¿estás seguro?»: a un cuadro de
     * confirmación se le da aceptar sin leerlo, y escribir la clave obliga a
     * mirar **cuál** se está borrando.
     */
    #[Test]
    public function a_wrong_confirmation_deletes_nothing(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('projects.destroy', $this->project), ['confirmation' => 'DEL-2'])
            ->assertRedirect();

        $this->assertNotSoftDeleted('projects', ['id' => $this->project->id]);
    }

    #[Test]
    public function an_empty_confirmation_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('projects.destroy', $this->project), [])
            ->assertSessionHasErrors('confirmation');

        $this->assertNotSoftDeleted('projects', ['id' => $this->project->id]);
    }

    /**
     * El borrado es suave a propósito: el proyecto sale de todas las pantallas
     * pero la fila sigue ahí, y con ella queda registrado quién lo borró. Un
     * borrado definitivo se llevaría justo el rastro que hace falta cuando
     * alguien pregunta a dónde se fue un proyecto.
     */
    #[Test]
    public function the_row_survives_so_the_audit_trail_survives(): void
    {
        $this->actingAs($this->manager)
            ->delete(route('projects.destroy', $this->project), ['confirmation' => 'DEL-1']);

        $this->assertSame(0, Project::query()->where('code', 'DEL-1')->count());
        $this->assertSame(1, Project::withTrashed()->where('code', 'DEL-1')->count());
    }

    #[Test]
    public function someone_who_is_not_on_the_project_cannot_delete_it(): void
    {
        $stranger = $this->makeUser('ajeno@ariel.test', Role::PROJECT_MANAGER);

        $this->actingAs($stranger)
            ->delete(route('projects.destroy', $this->project), ['confirmation' => 'DEL-1'])
            ->assertForbidden();

        $this->assertNotSoftDeleted('projects', ['id' => $this->project->id]);
    }

    #[Test]
    public function a_read_only_role_cannot_delete(): void
    {
        $auditor = $this->makeUser('auditor@ariel.test', Role::AUDITOR);
        $this->project->members()->attach($auditor->id, ['project_role' => Project::ROLE_MEMBER]);

        $this->actingAs($auditor)
            ->delete(route('projects.destroy', $this->project), ['confirmation' => 'DEL-1'])
            ->assertForbidden();
    }

    #[Test]
    public function the_delete_form_only_shows_to_whoever_can_use_it(): void
    {
        $this->actingAs($this->manager)
            ->get(route('projects.edit', $this->project))
            ->assertOk()
            ->assertSee(__('projects.danger_zone'));
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::factory()->create([
            'email' => $email,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $user->roles()->attach(Role::query()->where('name', $role)->value('id'));

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
}
