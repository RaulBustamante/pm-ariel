<?php

declare(strict_types=1);

namespace Tests\Feature\Tasks;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\Role;
use App\Models\Task;
use App\Models\User;
use App\Services\Scheduling\ProjectScheduler;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * El seguimiento del trabajo: avance, estado, notas y fechas reales.
 *
 * Lo que estas pruebas cuidan es que **las tres pantallas no puedan discrepar**.
 * El estado no es una columna: se deriva del avance, y vive en un solo método
 * del modelo. En cuanto la lista, el tablero y el Gantt clasifiquen por su
 * cuenta, uno dirá «terminada» sobre una tarea al 40 % y la gente dejará de
 * creerle a los tres.
 */
final class WorkTrackingTest extends TestCase
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
            'code' => 'SEG-1',
            'name' => 'Proyecto en curso',
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
    private function task(string $name, array $attributes = []): Task
    {
        $task = new Task;
        $task->fill([
            'project_id' => $this->project->id,
            'name' => $name,
            'duration_minutes' => 3 * self::DAY,
            'sort_order' => Task::query()->where('project_id', $this->project->id)->count(),
            ...$attributes,
        ]);
        $task->save();

        app(ProjectScheduler::class)->reschedule($this->project->refresh());

        return $task->refresh();
    }

    // --------------------------------------------------------------- El estado

    /**
     * La prueba que sostiene la decisión: **el estado sale del avance y de un
     * solo lugar**. Si alguien agrega una columna `status`, esto sigue pasando
     * pero el resto del sistema empieza a poder discrepar.
     */
    #[Test]
    public function the_state_is_derived_from_progress(): void
    {
        $todo = $this->task('Sin empezar');
        $doing = $this->task('A la mitad', ['percent_complete' => 40]);
        $done = $this->task('Terminada', ['percent_complete' => 100]);

        $this->assertSame('todo', $todo->state());
        $this->assertSame('doing', $doing->state());
        $this->assertSame('done', $done->state());
    }

    /** Los tres estados tienen texto en los dos idiomas. */
    #[Test]
    public function every_state_has_text_in_both_languages(): void
    {
        foreach (['todo', 'doing', 'done'] as $state) {
            foreach (['es', 'en'] as $locale) {
                $this->assertNotSame(
                    "tasks.state_{$state}",
                    __("tasks.state_{$state}", [], $locale),
                    "Falta el estado «{$state}» en {$locale}.",
                );
            }
        }
    }

    // ------------------------------------------------- El avance en la lista

    /**
     * **El avance se captura desde la lista.** Vivía solo en el detalle, y el
     * detalle no se alcanzaba desde la lista: quien quería decir «ya la terminé»
     * no tenía dónde.
     */
    #[Test]
    public function progress_can_be_captured_from_the_list(): void
    {
        $task = $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => $task->name,
                'duration' => '3d',
                'percent_complete' => 100,
            ])
            ->assertRedirect();

        $this->assertSame('done', $task->refresh()->state());
    }

    /** Y la lista lo enseña, con su estado y su enlace al detalle. */
    #[Test]
    public function the_list_shows_progress_and_links_to_the_detail(): void
    {
        $task = $this->task('Levantamiento', ['percent_complete' => 40]);

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.index', $this->project))
            ->assertOk()
            ->assertSee(__('tasks.progress'))
            ->assertSee(__('tasks.state_doing'))
            ->assertSee(route('projects.tasks.show', [$this->project, $task]), escape: false);
    }

    // ----------------------------------------------------------- Las notas

    /**
     * **Las notas se pueden capturar.** La columna existía desde la Etapa 3 y
     * ninguna pantalla la escribía: una columna que nadie llena es lo mismo que
     * una que no existe, y peor, porque parece que sí.
     */
    #[Test]
    public function notes_can_be_written_and_read_back(): void
    {
        $task = $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => $task->name,
                'duration' => '3d',
                'description' => "Hablar con almacén antes de empezar.\nEl turno de la noche no está enterado.",
            ])
            ->assertRedirect();

        $task->refresh();

        $this->assertStringContainsString('almacén', (string) $task->description);
        $this->assertTrue($task->hasNotes());

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee(__('tasks.notes'))
            ->assertSee('El turno de la noche no está enterado.');
    }

    /**
     * Guardar desde la lista **no borra las notas**. La lista no manda ese
     * campo, y un campo ausente no se distingue de uno vaciado si el controlador
     * no lo distingue a propósito.
     */
    #[Test]
    public function saving_from_the_list_does_not_wipe_the_notes(): void
    {
        $task = $this->task('Levantamiento', ['description' => 'No se toca.']);

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => $task->name,
                'duration' => '3d',
                'percent_complete' => 50,
            ]);

        $this->assertSame('No se toca.', $task->refresh()->description);
    }

    /** Pero vaciarlas a propósito sí las vacía. */
    #[Test]
    public function clearing_the_notes_from_the_detail_really_clears_them(): void
    {
        $task = $this->task('Levantamiento', ['description' => 'Se va.']);

        $this->actingAs($this->manager)
            ->put(route('projects.tasks.update', [$this->project, $task]), [
                'name' => $task->name,
                'duration' => '3d',
                'description' => '',
            ]);

        $this->assertNull($task->refresh()->description);
        $this->assertFalse($task->hasNotes());
    }

    // ------------------------------------------------------ Las fechas reales

    /**
     * Las fechas reales se anotan solas al capturar avance, y **ahora se ven**.
     * Un dato que el sistema guarda y nunca enseña es, para quien lo usa, un
     * dato que no existe.
     */
    #[Test]
    public function the_real_dates_are_shown_on_the_detail(): void
    {
        $task = $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee(__('tasks.not_started_yet'));

        $task->update(['percent_complete' => 100]);

        $this->actingAs($this->manager)
            ->get(route('projects.tasks.show', [$this->project, $task]))
            ->assertOk()
            ->assertSee(__('tasks.real_dates'))
            ->assertSee($task->refresh()->actual_finish?->format('d/m/Y') ?? '');
    }

    /**
     * La desviación del cierre se calcula contra lo planeado. Sin terminar es
     * `null` y no cero: un cero se leería como «cerró en la fecha», que es una
     * afirmación distinta de «todavía no cierra».
     */
    #[Test]
    public function the_finish_drift_is_null_until_it_actually_finishes(): void
    {
        $task = $this->task('Levantamiento', ['percent_complete' => 50]);

        $this->assertNull($task->finishDrift());

        $task->update(['percent_complete' => 100]);
        $task->refresh();

        $this->assertNotNull($task->finishDrift());
    }

    // --------------------------------------------------------- El tablero

    /** Arrastrar y oprimir el botón hacen lo mismo: capturar avance. */
    #[Test]
    public function dragging_a_card_captures_progress(): void
    {
        $task = $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->post(route('projects.kanban.move', [$this->project, $task]), ['column' => 'done'])
            ->assertRedirect();

        $task->refresh();

        $this->assertSame('done', $task->state());
        $this->assertSame(100.0, (float) $task->percent_complete);
        $this->assertNotNull($task->actual_finish);
    }

    /** La tarjeta dice el avance, quién responde y si tiene notas. */
    #[Test]
    public function the_card_carries_enough_to_read_it_without_opening_it(): void
    {
        $task = $this->task('Levantamiento', [
            'percent_complete' => 40,
            'owner_id' => $this->manager->id,
            'description' => 'Algo anotado.',
        ]);

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertSee($this->manager->name)
            ->assertSee('40 %')
            ->assertSee(__('tasks.has_notes'))
            ->assertSee(route('projects.tasks.show', [$this->project, $task]), escape: false);
    }

    /**
     * El tablero sigue completo sin JavaScript: el arrastre se agrega encima de
     * los botones, nunca en su lugar. Si alguien los quita, esto falla.
     */
    #[Test]
    public function the_board_still_works_without_javascript(): void
    {
        $this->task('Levantamiento');

        $this->actingAs($this->manager)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertSee(__('kanban.move_to_doing'))
            ->assertSee(__('kanban.move_to_done'));
    }

    /** Quien solo puede ver no arrastra: la zona de soltar ni se dibuja. */
    #[Test]
    public function a_reader_gets_no_drop_zone(): void
    {
        $this->task('Levantamiento');

        $auditor = User::factory()->create(['is_active' => true, 'must_change_password' => false]);
        $auditor->roles()->attach(Role::query()->where('name', Role::AUDITOR)->value('id'));

        $this->actingAs($auditor)
            ->get(route('projects.kanban', $this->project))
            ->assertOk()
            ->assertDontSee('data-kanban-column', escape: false)
            ->assertDontSee(__('kanban.move_to_done'));
    }

    // ------------------------------------------------------------- El Gantt

    /** El Gantt dice el estado con palabras, no solo con el relleno de la barra. */
    #[Test]
    public function the_gantt_says_the_state_in_words(): void
    {
        $this->task('Levantamiento', ['percent_complete' => 100]);

        $this->actingAs($this->manager)
            ->get(route('projects.gantt', $this->project))
            ->assertOk()
            ->assertSee(__('tasks.state_done'));
    }
}
