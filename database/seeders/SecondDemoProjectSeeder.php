<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\ProjectTemplate;
use App\Models\Resource;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskDependency;
use App\Models\User;
use App\Services\Advisor\ProjectAdvisor;
use App\Services\Scheduling\BaselineManager;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Scheduling\DependencyType;
use Illuminate\Database\Seeder;

/**
 * Un segundo proyecto de ejemplo, de otro tipo.
 *
 * Existe para que se pueda comprobar lo que **solo se ve con más de uno**: el
 * resumen de la semana en el inicio, que cruza todos los proyectos. Con un solo
 * ejemplo, esa pantalla se ve idéntica al corte de ese proyecto y no demuestra
 * nada.
 *
 * Es de mudanza de almacén y no de sistemas a propósito: mezclado con el de
 * inventario, la lista de pendientes de la semana enseña tareas de dos mundos
 * distintos, que es como se ve la semana de una persona de verdad.
 *
 *   artisan db:seed --class=SecondDemoProjectSeeder
 *
 * Se borra desde la pantalla de ajustes del proyecto, como cualquier otro.
 */
final class SecondDemoProjectSeeder extends Seeder
{
    private const CODE = 'DEMO-02';

    private const DAY = 540;

    public function run(): void
    {
        $owner = User::query()->where('email', 'admin@localhost')->first()
            ?? User::query()->first();

        if ($owner === null) {
            if (isset($this->command)) {
                $this->command->error('No hay usuarios. Corre primero: artisan db:seed --class=DevAdminSeeder');
            }

            return;
        }

        Project::withTrashed()->where('code', self::CODE)->forceDelete();

        $project = Project::query()->create([
            'code' => self::CODE,
            'name' => 'Mudanza del almacén de refacciones',
            'description' => 'Trasladar el almacén de refacciones a la nave nueva sin parar la operación.',
            'owner_id' => $owner->id,
            'status' => 'active',
            // Arranca dos semanas atrás: se traslapa con el otro ejemplo, que es
            // lo que hace demostrable el resumen del inicio.
            'planned_start' => now()->startOfWeek()->subWeeks(2)->setTime(9, 0),
        ]);

        $project->members()->attach($owner->id, ['project_role' => Project::ROLE_MANAGER]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => 'Calendario del proyecto',
            'key' => Calendar::DEFAULT_KEY,
            'timezone' => 'America/Mexico_City',
            'week' => Calendar::standardWeek(),
            'is_default' => true,
        ]);

        ProjectCharter::query()->create([
            'project_id' => $project->id,
            'template_id' => ProjectTemplate::query()->where('key', 'operations')->value('id')
                ?? ProjectTemplate::query()->value('id'),
            'problem_statement' => 'La nave actual quedó chica y está a veinte minutos de producción. '
                .'Cada urgencia de refacción cuesta media hora de camioneta.',
            'expected_benefit' => 'Almacén a doscientos metros de producción y espacio para el doble de existencias.',
            'objectives' => "Mover todo sin parar la línea.\nNo perder trazabilidad de ninguna pieza.\nDejar la nave vieja entregada al arrendador.",
            'deliverables' => "- Nave nueva acondicionada\n- Inventario trasladado y conciliado\n- Nave vieja entregada",
            'success_criteria' => 'Cero paros de producción por falta de refacción durante la mudanza.',
            'out_of_scope' => 'No incluye la mudanza de oficinas ni el archivo muerto.',
            'sponsor_id' => $owner->id,
        ]);

        $tasks = $this->tasks($project, $owner);
        $this->dependencies($project, $tasks);
        $this->resources($project, $tasks);

        app(ProjectScheduler::class)->reschedule($project->refresh());

        app(BaselineManager::class)->capture($project, 'Línea base al aprobarse', 'Capturada al autorizarse la mudanza.');

        $this->stampActualDates($project);

        app(ProjectAdvisor::class)->analyze($project->refresh());

        if (isset($this->command)) {
            $this->command->info('Segundo proyecto de ejemplo cargado: '.$project->name);
            $this->command->comment('Sirve para ver el resumen de la semana en el inicio, que cruza proyectos.');
        }
    }

    /**
     * @return array<string, Task>
     */
    private function tasks(Project $project, User $owner): array
    {
        /** @var list<array{string, string, int, ?string, bool}> $plan */
        $plan = [
            ['acondicionamiento', 'Acondicionamiento de la nave', 0, null, true],
            ['piso', 'Nivelación y pintura de piso', 4, 'acondicionamiento', false],
            ['racks', 'Montaje de racks', 6, 'acondicionamiento', false],
            ['electrico', 'Instalación eléctrica y luminarias', 5, 'acondicionamiento', false],
            ['red', 'Red y punto de lectura de código', 3, 'acondicionamiento', false],

            ['preparacion', 'Preparación del inventario', 0, null, true],
            ['conteo', 'Conteo físico previo', 5, 'preparacion', false],
            ['etiquetado', 'Etiquetado de tarimas', 4, 'preparacion', false],
            ['depuracion', 'Depuración de obsoletos', 6, 'preparacion', false],
            ['embalaje', 'Embalaje de piezas críticas', 3, 'preparacion', false],

            ['traslado', 'Traslado', 0, null, true],
            ['ventana', 'Acuerdo de ventana con producción', 1, 'traslado', false],
            ['viaje_uno', 'Primer viaje: refacciones críticas', 2, 'traslado', false],
            ['viaje_dos', 'Segundo viaje: resto del inventario', 3, 'traslado', false],
            ['acomodo', 'Acomodo en racks nuevos', 4, 'traslado', false],
            ['conciliacion', 'Conciliación contra el conteo previo', 3, 'traslado', false],

            ['cierre_mudanza', 'Cierre', 0, null, true],
            ['limpieza_nave', 'Limpieza de la nave vieja', 2, 'cierre_mudanza', false],
            ['entrega_nave', 'Entrega al arrendador', 1, 'cierre_mudanza', false],
            ['listo', 'Almacén nuevo operando', 0, 'cierre_mudanza', false],
        ];

        $tasks = [];
        $order = 0;

        foreach ($plan as [$key, $name, $days, $parent, $isSummary]) {
            $task = new Task;
            $task->fill([
                'project_id' => $project->id,
                // `?->` y no acceso directo: si alguien reordena el plan y una
                // hija queda antes que su padre, esto la deja de primer nivel
                // en vez de reventar con un indice inexistente.
                'parent_id' => $parent === null ? null : ($tasks[$parent] ?? null)?->id,
                'name' => $name,
                'is_summary' => $isSummary,
                'duration_minutes' => $days * self::DAY,
                'sort_order' => $order++,
                'cost' => $days * 3800,
                // El dueño se queda con varias: el resumen del inicio filtra por
                // responsable, y sin responsables no tendría nada que enseñar.
                'owner_id' => $isSummary || $days === 0 ? null : $owner->id,
            ]);
            $task->save();

            $tasks[$key] = $task;
        }

        foreach (['conteo' => 100, 'etiquetado' => 100, 'piso' => 100, 'depuracion' => 60, 'racks' => 45] as $key => $percent) {
            ($tasks[$key] ?? null)?->update(['percent_complete' => $percent]);
        }

        return $tasks;
    }

    /**
     * @param  array<string, Task>  $tasks
     */
    private function dependencies(Project $project, array $tasks): void
    {
        $links = [
            ['piso', 'racks'], ['racks', 'electrico'], ['electrico', 'red'],
            ['conteo', 'etiquetado'], ['etiquetado', 'depuracion'], ['depuracion', 'embalaje'],
            ['red', 'ventana'], ['embalaje', 'ventana'],
            ['ventana', 'viaje_uno'], ['viaje_uno', 'viaje_dos'], ['viaje_dos', 'acomodo'],
            ['acomodo', 'conciliacion'], ['conciliacion', 'limpieza_nave'],
            ['limpieza_nave', 'entrega_nave'], ['entrega_nave', 'listo'],
        ];

        foreach ($links as [$from, $to]) {
            TaskDependency::query()->create([
                'project_id' => $project->id,
                'predecessor_id' => $tasks[$from]->id,
                'successor_id' => $tasks[$to]->id,
                'type' => DependencyType::FinishToStart->value,
                'lag_minutes' => 0,
            ]);
        }
    }

    /**
     * @param  array<string, Task>  $tasks
     */
    private function resources(Project $project, array $tasks): void
    {
        $people = [];

        foreach (['Marta Reyes', 'Jorge Salinas', 'Cuadrilla de mudanza'] as $name) {
            $people[$name] = Resource::query()->create([
                'project_id' => $project->id,
                'name' => $name,
                'type' => Resource::TYPE_PERSON,
                'capacity_percent' => 100,
            ]);
        }

        $assignments = [
            'conteo' => 'Marta Reyes', 'etiquetado' => 'Marta Reyes', 'depuracion' => 'Marta Reyes',
            'piso' => 'Jorge Salinas', 'racks' => 'Jorge Salinas', 'electrico' => 'Jorge Salinas',
            'viaje_uno' => 'Cuadrilla de mudanza', 'viaje_dos' => 'Cuadrilla de mudanza',
            'acomodo' => 'Cuadrilla de mudanza',
        ];

        foreach ($assignments as $key => $who) {
            TaskAssignment::query()->create([
                'task_id' => $tasks[$key]->id,
                'resource_id' => $people[$who]->id,
                'units_percent' => 100,
            ]);
        }
    }

    /** Igual que el primer ejemplo: se cierran en su fecha, una dentro de esta semana. */
    private function stampActualDates(Project $project): void
    {
        $done = $project->tasks()
            ->where('percent_complete', '>=', 100)
            ->whereNotNull('early_finish')
            ->get();

        foreach ($done as $index => $task) {
            $task->forceFill([
                'actual_start' => $task->early_start,
                'actual_finish' => $index === $done->count() - 1
                    ? now()->startOfWeek()->addDay()->setTime(16, 0)
                    : $task->early_finish,
            ])->save();
        }
    }
}
