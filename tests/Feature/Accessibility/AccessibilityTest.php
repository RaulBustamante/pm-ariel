<?php

declare(strict_types=1);

namespace Tests\Feature\Accessibility;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Initiation\InitiationStep;
use Database\Seeders\ProjectTemplatesSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Accesibilidad verificada, no declarada.
 *
 * Estas pruebas no sustituyen a probar con un lector de pantalla real, y no
 * pretenden hacerlo. Cubren lo que sí se puede comprobar sin abrir un navegador
 * — y que es justo lo que se rompe en silencio cuando alguien agrega una
 * pantalla con prisa.
 */
final class AccessibilityTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(ProjectTemplatesSeeder::class);

        $this->manager = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $this->manager->roles()->attach(Role::query()->where('name', Role::ADMIN)->value('id'));

        $this->project = Project::query()->create([
            'code' => 'A11Y-1',
            'name' => 'Proyecto accesible',
            'owner_id' => $this->manager->id,
            'planned_start' => now()->startOfWeek()->setTime(9, 0),
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

        ProjectCharter::query()->create([
            'project_id' => $this->project->id,
            'current_step' => InitiationStep::Justification->value,
            'completed_steps' => [],
        ]);

        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => 'Levantamiento',
            'duration_minutes' => 1080,
            'sort_order' => 0,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());
        app(ProjectAdvisor::class)->analyze($this->project->refresh());
    }

    /**
     * @return list<string>
     */
    private function screens(): array
    {
        return [
            route('dashboard'),
            route('team-activities.index'),
            route('onboarding'),
            route('preferences.edit'),
            route('projects.index'),
            route('projects.create'),
            route('projects.dashboard', $this->project),
            route('projects.tasks.index', $this->project),
            route('projects.gantt', $this->project),
            route('projects.kanban', $this->project),
            route('projects.calendar', $this->project),
            route('projects.advisor', $this->project),
            route('projects.edit', $this->project),
            route('projects.calendars.index', $this->project),
            route('projects.initiation.overview', $this->project),
            route('admin.users.index'),
            route('admin.hierarchy.index'),
            route('admin.org-units.index'),
        ];
    }

    /** El primer elemento enfocable de cada pantalla lleva al contenido. */
    #[Test]
    public function every_screen_offers_a_skip_link(): void
    {
        foreach ($this->screens() as $url) {
            $content = $this->actingAs($this->manager)->get($url)->assertOk()->getContent() ?: '';

            $this->assertStringContainsString('#main-content', $content, "Falta el salto al contenido en {$url}");
            $this->assertStringContainsString('id="main-content"', $content, "Falta el destino del salto en {$url}");
        }
    }

    #[Test]
    public function every_screen_declares_its_language(): void
    {
        foreach ($this->screens() as $url) {
            $content = $this->actingAs($this->manager)->get($url)->getContent() ?: '';

            $this->assertMatchesRegularExpression('/<html lang="(es|en)"/', $content, "Falta el idioma en {$url}");
        }
    }

    /**
     * Una clave de traducción sin traducir llega a la pantalla como
     * «tasks.algo». Es el error más fácil de cometer y el más vergonzoso de
     * enseñar.
     */
    #[Test]
    public function no_screen_shows_an_untranslated_key(): void
    {
        $prefixes = [
            'tasks.', 'gantt.', 'kanban.', 'calendar.', 'calendars.', 'advisor.',
            'projects.', 'initiation.', 'dashboard.', 'reports.', 'filters.',
            'onboarding.', 'shortcuts.', 'attachments.', 'import.', 'wizard.',
            'org_units.', 'hierarchy.', 'glossary.', 'errors.', 'constraints.', 'team.',
        ];

        foreach ($this->screens() as $url) {
            $content = $this->actingAs($this->manager)->get($url)->getContent() ?: '';

            // Solo el texto visible: los atributos `data-` y las rutas contienen
            // puntos legítimamente.
            $visible = strip_tags($content);

            foreach ($prefixes as $prefix) {
                $this->assertDoesNotMatchRegularExpression(
                    '/(?<![A-Za-z0-9_])'.preg_quote($prefix, '/').'[A-Za-z0-9_.-]+/',
                    $visible,
                    "Se ve una clave sin traducir que empieza con «{$prefix}» en {$url}",
                );
            }
        }
    }

    /** Una tabla sin `caption` ni encabezados es una rejilla muda. */
    #[Test]
    public function every_table_has_a_caption_and_column_headers(): void
    {
        foreach ($this->screens() as $url) {
            $content = $this->actingAs($this->manager)->get($url)->getContent() ?: '';

            if (! str_contains($content, '<table')) {
                continue;
            }

            $this->assertStringContainsString('<caption', $content, "Tabla sin título en {$url}");
            $this->assertStringContainsString('scope="col"', $content, "Tabla sin encabezados en {$url}");
        }
    }

    /** Un dibujo sin rótulo es una imagen vacía para quien no lo ve. */
    #[Test]
    public function every_chart_carries_a_label_and_a_long_description(): void
    {
        foreach ([
            route('projects.gantt', $this->project),
            route('projects.dashboard', $this->project),
        ] as $url) {
            $content = $this->actingAs($this->manager)->get($url)->getContent() ?: '';

            $this->assertStringContainsString('role="img"', $content, "Dibujo sin rol en {$url}");
            $this->assertStringContainsString('aria-label', $content, "Dibujo sin rótulo en {$url}");
            $this->assertStringContainsString('<desc>', $content, "Dibujo sin descripción larga en {$url}");
        }
    }

    /**
     * El color no puede ser el único indicador. Cada estado lleva además texto
     * o un símbolo — es la deficiencia visual más común y la más fácil de
     * ignorar al diseñar.
     */
    #[Test]
    public function state_is_never_communicated_by_colour_alone(): void
    {
        $content = $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->getContent() ?: '';

        // La ruta crítica se marca con la palabra, no solo con el rojo.
        $this->assertStringContainsString(__('tasks.critical'), $content);
    }

    /** Sin `viewport` no hay diseño adaptable que valga. */
    #[Test]
    public function every_screen_is_responsive_ready(): void
    {
        foreach ($this->screens() as $url) {
            $content = $this->actingAs($this->manager)->get($url)->getContent() ?: '';

            $this->assertStringContainsString('width=device-width', $content, "Falta el viewport en {$url}");
        }
    }

    /**
     * En pantalla chica el menú lateral se oculta. El acceso no puede
     * desaparecer con él.
     */
    #[Test]
    public function navigation_survives_on_a_small_screen(): void
    {
        $content = $this->actingAs($this->manager)->get(route('dashboard'))->getContent() ?: '';

        $this->assertStringContainsString('lg:hidden', $content, 'Falta la navegación de pantalla chica.');
    }

    /** Las tablas anchas hacen scroll en su caja, no en el cuerpo de la página. */
    #[Test]
    public function wide_tables_scroll_inside_their_own_box(): void
    {
        $content = $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->getContent() ?: '';

        $this->assertStringContainsString('overflow-x-auto', $content);
    }

    #[Test]
    public function the_error_pages_explain_what_to_do(): void
    {
        foreach (['403', '404', '419', '429', '500', '503'] as $code) {
            $html = view("errors.{$code}")->render();

            $this->assertStringContainsString(__("errors.{$code}_title"), $html);
            $this->assertStringContainsString(__("errors.{$code}_action"), $html);
            // Nunca un callejón sin salida.
            $this->assertStringContainsString(__('errors.go_home'), $html);
        }
    }
}
