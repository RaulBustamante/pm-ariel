<?php

declare(strict_types=1);

namespace Tests\Feature\Onboarding;

use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\BaselineManager;
use App\Support\Scheduling\WorkingCalendar;
use Database\Seeders\ProjectTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OnboardingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProjectTemplatesSeeder::class);

        $this->admin = User::factory()->create([
            'email' => 'admin@localhost',
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->admin->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));
    }

    #[Test]
    public function the_tour_renders_its_five_steps_and_the_vocabulary(): void
    {
        $this->actingAs($this->admin)
            ->get(route('onboarding'))
            ->assertOk()
            ->assertSee(__('onboarding.step_start'))
            ->assertSee(__('onboarding.step_advisor'))
            ->assertSee(__('glossary.charter_label'))
            ->assertSee(__('shortcuts.title'));
    }

    #[Test]
    public function the_demo_project_loads_with_its_planted_problems(): void
    {
        $this->actingAs($this->admin)
            ->post(route('onboarding.demo.store'))
            ->assertRedirect();

        $demo = Project::query()->where('code', 'DEMO-01')->firstOrFail();

        // Un plan de tamaño real: con diez tareas todo se ve comodo y las
        // cosas que hay que poder enseñar —el Gantt paginado, los carriles del
        // tablero— no aparecen.
        $this->assertGreaterThanOrEqual(50, Task::query()->where('project_id', $demo->id)->count());
        $this->assertGreaterThan(0, $demo->findings()->count());
        // El aviso de sobreasignación es el que hace demostrable el producto.
        $this->assertContains('resource.overallocated', $demo->findings()->pluck('rule')->all());
    }

    /**
     * Una linea base capturada sobre el plan final da varianza cero en todos
     * los renglones, y la pantalla de comparacion se ve vacia. El ejemplo tiene
     * que traer desviacion de verdad para que se entienda para que sirve.
     */
    #[Test]
    public function the_demo_baseline_already_shows_a_slip(): void
    {
        $this->actingAs($this->admin)->post(route('onboarding.demo.store'));

        $demo = Project::query()->where('code', 'DEMO-01')->firstOrFail();
        $baseline = $demo->baselines()->firstOrFail();

        $comparison = app(BaselineManager::class)->compare(
            $demo,
            $baseline,
            WorkingCalendar::standard(),
        );

        $this->assertGreaterThan(0, $comparison['finish_variance_minutes'], 'El ejemplo debe traer atraso.');
    }

    /**
     * Un demo que no se puede quitar acaba conviviendo con los proyectos de
     * verdad hasta que alguien lo confunde en un reporte.
     */
    #[Test]
    public function the_demo_can_be_removed_in_one_click(): void
    {
        $this->actingAs($this->admin)->post(route('onboarding.demo.store'));

        $this->actingAs($this->admin)
            ->delete(route('onboarding.demo.destroy'))
            ->assertRedirect(route('onboarding'));

        // Definitivo: borrado en suave seguiría apareciendo en cualquier consulta
        // que use `withTrashed`.
        $this->assertSame(0, Project::withTrashed()->where('code', 'DEMO-01')->count());
    }

    #[Test]
    public function loading_the_demo_twice_does_not_duplicate_it(): void
    {
        $this->actingAs($this->admin)->post(route('onboarding.demo.store'));
        $this->actingAs($this->admin)->post(route('onboarding.demo.store'));

        $this->assertSame(1, Project::query()->where('code', 'DEMO-01')->count());
    }

    #[Test]
    public function someone_who_is_not_an_administrator_cannot_load_the_demo(): void
    {
        $manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $manager->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($manager)->post(route('onboarding.demo.store'))->assertForbidden();
    }

    #[Test]
    public function the_tour_is_reachable_from_the_menu(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee(route('onboarding'), escape: false);
    }
}
