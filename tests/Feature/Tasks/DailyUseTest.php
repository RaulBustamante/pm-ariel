<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use App\Services\Scheduling\TaskOutliner;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Lo que Raúl pidió después de usar el sistema (CL-021).
 *
 * Tres cosas distintas con un hilo común: **datos que el sistema ya tenía y
 * nadie podía ver o usar**. El avance de un paquete se calculaba y se tiraba;
 * ligar dos tareas exigía aprenderse `12FS+2d`; y lo que se fue diciendo de una
 * tarea no se podía guardar en ningún lado.
 */
final class DailyUseTest extends TestCase
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
            'code' => 'USO-1',
            'name' => 'Proyecto en uso',
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

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function task(string $name, int $days = 2, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => $days * self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        return $task;
    }

    private function reschedule(): void
    {
        app(ProjectScheduler::class)->reschedule($this->project->refresh());
    }

    // ------------------------------------- El avance de un paquete (9.1)

    /**
     * **La prueba del defecto que destapó Raúl.** El avance de un paquete se
     * calculaba desde la Etapa 3 y no se guardaba nunca: el número vivía un
     * instante en memoria y se tiraba en cada recálculo, así que la lista
     * pintaba lo que hubiera en la base — cero — y un paquete terminado se veía
     * igual que uno sin empezar.
     */
    #[Test]
    public function a_package_shows_the_progress_of_the_work_below_it(): void
    {
        $package = $this->task('Análisis', 0);
        $this->task('Corta', 1, ['parent_id' => $package->id, 'percent_complete' => 100]);
        $this->task('Larga', 9, ['parent_id' => $package->id]);

        $this->reschedule();

        // Ponderado por duración, no por número de tareas: una de un día
        // terminada y otra de nueve sin empezar es 10 %, no 50 %.
        $this->assertSame(10.0, (float) $package->refresh()->percent_complete);
    }

    /**
     * Y con tres niveles también. Contar solo las hijas directas daba peso cero
     * a un paquete cuyas hijas son todas paquetes, así que esa rama entera
     * desaparecía del promedio de arriba sin que nada fallara.
     */
    #[Test]
    public function the_weighting_survives_three_levels(): void
    {
        $top = $this->task('Proyecto', 0);
        $branch = $this->task('Rama pesada', 0, ['parent_id' => $top->id]);
        $this->task('Nueve días', 9, ['parent_id' => $branch->id]);
        $this->task('Un día terminado', 1, ['parent_id' => $top->id, 'percent_complete' => 100]);

        $this->reschedule();

        // La rama pesada tiene que pesar nueve; si pesara cero, el nivel de
        // arriba saldría al 100 %.
        $this->assertSame(10.0, (float) $top->refresh()->percent_complete);
    }

    /** El avance de una hoja lo sigue capturando quien la trabaja. */
    #[Test]
    public function a_leaf_keeps_the_progress_that_was_captured(): void
    {
        $task = $this->task('Sola', 3, ['percent_complete' => 35]);

        $this->reschedule();

        $this->assertSame(35.0, (float) $task->refresh()->percent_complete);
    }

    // ----------------------------------------- «Depende de» sin códigos (9.2)

    #[Test]
    public function two_tasks_can_be_linked_by_picking_from_a_list(): void
    {
        $first = $this->task('Levantamiento');
        $second = $this->task('Diseño');
        $this->reschedule();

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.dependencies.store', [$this->project, $second]), [
                'predecessor_id' => $first->id,
                'type' => 'FS',
                'lag_days' => 0,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('task_dependencies', [
            'predecessor_id' => $first->id,
            'successor_id' => $second->id,
            'type' => 'FS',
        ]);

        // Y el plan quedó recalculado: la segunda arranca después de la primera.
        $this->assertTrue($second->refresh()->early_start >= $first->refresh()->early_finish);
    }

    /** La espera se dice en días y se guarda con la jornada del proyecto. */
    #[Test]
    public function the_wait_is_captured_in_days(): void
    {
        $first = $this->task('Primera');
        $second = $this->task('Segunda');
        $this->reschedule();

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.dependencies.store', [$this->project, $second]), [
                'predecessor_id' => $first->id,
                'type' => 'FS',
                'lag_days' => 2,
            ]);

        $link = TaskDependency::query()->where('successor_id', $second->id)->firstOrFail();

        $this->assertSame(2 * self::DAY, (int) $link->lag_minutes);
    }

    /**
     * **Un círculo se rechaza y se deshace en el momento.** Sin esto, ligar dos
     * tareas al revés dejaría el proyecto entero sin poder calcularse, con un
     * mensaje que no dice qué hacer y sin forma de saber cuál dependencia fue.
     */
    #[Test]
    public function a_loop_is_refused_and_undone_on_the_spot(): void
    {
        $first = $this->task('Primera');
        $second = $this->task('Segunda');
        $this->reschedule();

        TaskDependency::query()->create([
            'project_id' => $this->project->id,
            'predecessor_id' => $first->id,
            'successor_id' => $second->id,
            'type' => 'FS',
            'lag_minutes' => 0,
        ]);
        $this->reschedule();

        // Y ahora al revés: la primera esperando a la segunda.
        $this->actingAs($this->manager)
            ->post(route('projects.tasks.dependencies.store', [$this->project, $first]), [
                'predecessor_id' => $second->id,
                'type' => 'FS',
                'lag_days' => 0,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        // La liga no quedó, y el plan sigue calculándose.
        $this->assertSame(1, TaskDependency::query()->count());
        $this->assertNotNull($first->refresh()->early_start);
    }

    /** Una tarea no puede depender de sí misma. */
    #[Test]
    public function a_task_cannot_depend_on_itself(): void
    {
        $task = $this->task('Sola');
        $this->reschedule();

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.dependencies.store', [$this->project, $task]), [
                'predecessor_id' => $task->id,
                'type' => 'FS',
            ])
            ->assertSessionHasErrors('predecessor_id');
    }

    /** Ligar dos que ya estaban ligadas cambia la relación, no crea una segunda. */
    #[Test]
    public function linking_the_same_pair_twice_replaces_the_relationship(): void
    {
        $first = $this->task('Primera');
        $second = $this->task('Segunda');
        $this->reschedule();

        foreach (['FS', 'SS'] as $type) {
            $this->actingAs($this->manager)
                ->post(route('projects.tasks.dependencies.store', [$this->project, $second]), [
                    'predecessor_id' => $first->id,
                    'type' => $type,
                    'lag_days' => 0,
                ]);
        }

        $this->assertSame(1, TaskDependency::query()->where('successor_id', $second->id)->count());
        $this->assertSame('SS', TaskDependency::query()->where('successor_id', $second->id)->value('type'));
    }

    #[Test]
    public function a_dependency_can_be_removed(): void
    {
        $first = $this->task('Primera');
        $second = $this->task('Segunda');
        $this->reschedule();

        $link = TaskDependency::query()->create([
            'project_id' => $this->project->id,
            'predecessor_id' => $first->id,
            'successor_id' => $second->id,
            'type' => 'FS',
            'lag_minutes' => 0,
        ]);
        $this->reschedule();

        $this->actingAs($this->manager)
            ->delete(route('projects.tasks.dependencies.destroy', [$this->project, $second, $link]))
            ->assertRedirect();

        $this->assertSame(0, TaskDependency::query()->count());
    }

    /** Las cuatro relaciones se leen en español, en los dos idiomas. */
    #[Test]
    public function every_relationship_reads_as_a_sentence_in_both_languages(): void
    {
        foreach (['FS', 'SS', 'FF', 'SF'] as $type) {
            foreach (['es', 'en'] as $locale) {
                foreach (['rel_', 'rel_'] as $prefix) {
                    $this->assertNotSame(
                        "tasks.{$prefix}{$type}",
                        __("tasks.{$prefix}{$type}", [], $locale),
                        "Falta la relación «{$type}» en {$locale}.",
                    );
                }

                $this->assertNotSame(
                    "tasks.rel_{$type}_short",
                    __("tasks.rel_{$type}_short", [], $locale),
                );
            }
        }
    }

    /**
     * En Modo Simple no se ofrece escoger la relación: casi todas las
     * dependencias reales son «esta empieza cuando aquella termina», y las
     * cuatro son justo la complejidad que hace que la gente odie estas
     * herramientas.
     */
    #[Test]
    public function simple_mode_does_not_offer_the_four_relationship_types(): void
    {
        $this->task('Primera');
        $second = $this->task('Segunda');
        $this->reschedule();

        $this->manager->update(['expert_mode' => false]);

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $second]))
            ->assertOk()
            ->assertSee(__('tasks.depends_on'))
            ->assertDontSee(__('tasks.relationship'));

        $this->manager->update(['expert_mode' => true]);

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $second]))
            ->assertOk()
            ->assertSee(__('tasks.relationship'));
    }

    // ---------------------------------------------- Comentarios (9.3)

    #[Test]
    public function a_comment_can_be_written_and_read_back(): void
    {
        $task = $this->task('Con conversación');
        $this->reschedule();

        $this->actingAs($this->manager)
            ->post(route('projects.tasks.comments.store', [$this->project, $task]), [
                'body' => 'El proveedor pidió dos días más.',
            ])
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee('El proveedor pidió dos días más.')
            ->assertSee($this->manager->name);
    }

    /**
     * El hilo mezcla lo que la gente dijo con lo que el sistema registró.
     * Separados obligan a leer las dos listas y cruzarlas por fecha, y ahí se
     * pierde justo lo que se busca.
     */
    #[Test]
    public function the_timeline_mixes_comments_and_changes(): void
    {
        $task = $this->task('Con historia');
        $this->reschedule();

        $this->actingAs($this->manager)->put(route('projects.tasks.update', [$this->project, $task]), [
            'name' => 'Con historia corregida',
            'duration' => '4d',
        ]);

        $this->actingAs($this->manager)->post(route('projects.tasks.comments.store', [$this->project, $task]), [
            'body' => 'Se alargó porque falta el material.',
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee('Se alargó porque falta el material.')
            // El campo que cambió, con su nombre en español y no con el de la
            // columna: quien lee un historial no tiene por qué saber cómo se
            // llaman las columnas por dentro.
            ->assertSee(__('tasks.duration'));
    }

    /** Solo su autor lo borra. */
    #[Test]
    public function only_the_author_can_delete_a_comment(): void
    {
        $task = $this->task('Con conversación');
        $this->reschedule();

        $other = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $other->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));
        $this->project->members()->attach($other->id, ['project_role' => Project::ROLE_MEMBER]);

        $this->actingAs($this->manager)->post(route('projects.tasks.comments.store', [$this->project, $task]), [
            'body' => 'Mío.',
        ]);

        $comment = TaskComment::query()->firstOrFail();

        $this->actingAs($other)
            ->delete(route('projects.tasks.comments.destroy', [$this->project, $task, $comment]))
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->delete(route('projects.tasks.comments.destroy', [$this->project, $task, $comment]))
            ->assertRedirect();

        $this->assertSoftDeleted($comment);
    }

    /** Comentar no recalcula el plan: es lo más barato que se puede hacer aquí. */
    #[Test]
    public function commenting_does_not_reschedule_the_project(): void
    {
        $task = $this->task('Sola');
        $this->reschedule();

        $runs = $this->project->scheduleRuns()->count();

        $this->actingAs($this->manager)->post(route('projects.tasks.comments.store', [$this->project, $task]), [
            'body' => 'Un comentario.',
        ]);

        $this->assertSame($runs, $this->project->scheduleRuns()->count());
    }

    #[Test]
    public function someone_without_write_access_cannot_comment(): void
    {
        $task = $this->task('Sola');
        $this->reschedule();

        $stranger = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $stranger->roles()->attach(Role::query()->where('name', Role::PROJECT_MANAGER)->value('id'));

        $this->actingAs($stranger)
            ->post(route('projects.tasks.comments.store', [$this->project, $task]), ['body' => 'Intruso.'])
            ->assertForbidden();
    }

    /** Una tarea de otro proyecto responde 404, no 403. */
    #[Test]
    public function a_task_from_another_project_is_not_found(): void
    {
        $other = Project::query()->create([
            'code' => 'USO-2',
            'name' => 'Otro',
            'owner_id' => $this->manager->id,
            'planned_start' => '2026-03-02 09:00',
        ]);

        $foreign = new Task;
        $foreign->fill(['project_id' => $other->id, 'name' => 'Ajena', 'duration_minutes' => 540, 'sort_order' => 0]);
        $foreign->save();

        $this->expectException(NotFoundHttpException::class);

        app(TaskOutliner::class)->assertBelongs($this->project, $foreign);
    }
}
