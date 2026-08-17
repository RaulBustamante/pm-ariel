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
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Initiation\InitiationStep;
use Illuminate\Database\Seeder;

/**
 * Un proyecto realista, cargado, para poder ver el producto funcionando sin
 * capturar nada.
 *
 * Trae a propósito **dos problemas plantados**: una persona sobreasignada y una
 * tarea crítica sin responsable. Un demo donde todo está bien no demuestra nada
 * — lo que hay que poder enseñar es que el sistema avisa.
 *
 *   artisan db:seed --class=DemoProjectSeeder
 *
 * Se puede correr varias veces: reemplaza el proyecto anterior con la misma
 * clave en vez de acumular copias.
 */
final class DemoProjectSeeder extends Seeder
{
    private const CODE = 'DEMO-01';

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
            'name' => 'Sistema de inventario de refacciones',
            'description' => 'Reemplazar las hojas de cálculo del control de refacciones críticas por un sistema único.',
            'owner_id' => $owner->id,
            'status' => 'active',
            // Arranque el lunes de la semana entrante: el Gantt se ve con «hoy»
            // dentro del cuadro, que es como se ve un proyecto real.
            'planned_start' => now()->startOfWeek()->setTime(9, 0),
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

        $this->charter($project, $owner);

        $tasks = $this->tasks($project);
        $this->dependencies($project, $tasks);
        $this->resources($project, $tasks);

        app(ProjectScheduler::class)->reschedule($project->refresh());
        app(ProjectAdvisor::class)->analyze($project->refresh());

        if (isset($this->command)) {
            $this->command->newLine();
            $this->command->info('Proyecto de ejemplo cargado.');
            $this->command->table(
                ['Proyecto', 'Tareas', 'Recursos', 'Avisos'],
                [[
                    $project->name,
                    $project->tasks()->count(),
                    $project->resources()->count(),
                    $project->findings()->count(),
                ]],
            );
            $this->command->comment('Trae dos problemas plantados a proposito: una persona sobreasignada');
            $this->command->comment('y una tarea critica sin responsable. Miralos en la pestaña Avisos.');
        }
    }

    private function charter(Project $project, User $owner): void
    {
        ProjectCharter::query()->create([
            'project_id' => $project->id,
            'template_id' => ProjectTemplate::query()->where('key', 'systems')->value('id'),
            'problem_statement' => 'El control de refacciones se lleva en cinco hojas de cálculo que cada quien guarda en su equipo. '
                .'Nadie sabe cuál es la versión buena, y consolidar toma dos días al mes.',
            'expected_benefit' => 'Una sola fuente de información consultable en el momento, y dos días al mes liberados '
                .'del trabajo de consolidar.',
            'alignment' => 'Reducción de paros por falta de refacción, que es el objetivo de operación del año.',
            'objectives' => "Tener un solo inventario confiable.\nSaber en el momento qué hay y dónde está.\nAvisar antes de que se agote una refacción crítica.",
            'deliverables' => "- Documento de requerimientos aprobado\n- Ambiente de pruebas funcionando\n- Sistema en producción\n- Usuarios capacitados y manual entregado",
            'success_criteria' => 'El cierre del mes siguiente a la puesta en marcha se hace en un día, con cero diferencias.',
            'out_of_scope' => 'No incluye compras ni órdenes de trabajo. Tampoco el inventario de consumibles de oficina.',
            'assumptions' => 'TI entrega el servidor en las dos primeras semanas.',
            'constraints' => 'Tiene que estar operando antes de la auditoría de mayo.',
            'sponsor_id' => $owner->id,
            'current_step' => InitiationStep::Risks->value,
            'completed_steps' => array_map(
                fn (InitiationStep $step): string => $step->value,
                InitiationStep::ordered(),
            ),
        ]);
    }

    /**
     * @return array<string, Task>
     */
    private function tasks(Project $project): array
    {
        $plan = [
            // clave           nombre                                    días  padre
            ['analisis',       'Análisis',                                 0,  null],
            ['entrevistas',    'Entrevistas con almacén',                  3,  'analisis'],
            ['requerimientos', 'Documento de requerimientos',              4,  'analisis'],
            ['aprobacion',     'Aprobación de requerimientos',             0,  'analisis'],

            ['construccion',   'Construcción',                             0,  null],
            ['modelo',         'Modelo de datos',                          3,  'construccion'],
            ['catalogo',       'Catálogo de refacciones',                  5,  'construccion'],
            ['movimientos',    'Entradas y salidas',                       6,  'construccion'],
            ['alertas',        'Alertas de existencia mínima',             4,  'construccion'],

            ['despliegue',     'Puesta en marcha',                         0,  null],
            ['migracion',      'Migración de datos históricos',            4,  'despliegue'],
            ['pruebas',        'Pruebas con usuarios',                     3,  'despliegue'],
            ['capacitacion',   'Capacitación del personal',                2,  'despliegue'],
            ['arranque',       'Arranque en producción',                   0,  'despliegue'],
        ];

        $tasks = [];
        $order = 0;

        foreach ($plan as [$key, $name, $days, $parentKey]) {
            $task = new Task;
            $task->fill([
                'project_id' => $project->id,
                'parent_id' => $parentKey === null ? null : $tasks[$parentKey]->id,
                'name' => $name,
                'duration_minutes' => $days * self::DAY,
                'sort_order' => $order++,
                'cost' => $days * 4500,
            ]);
            $task->save();

            $tasks[$key] = $task;
        }

        // Un poco de avance real, para que las barras no se vean todas vacías.
        $tasks['entrevistas']->update(['percent_complete' => 100]);
        $tasks['requerimientos']->update(['percent_complete' => 60]);

        return $tasks;
    }

    /**
     * @param  array<string, Task>  $tasks
     */
    private function dependencies(Project $project, array $tasks): void
    {
        $links = [
            ['entrevistas', 'requerimientos', 'FS', 0],
            ['requerimientos', 'aprobacion', 'FS', 0],
            ['aprobacion', 'modelo', 'FS', 0],
            ['modelo', 'catalogo', 'FS', 0],
            ['catalogo', 'movimientos', 'FS', 0],
            // Las alertas pueden empezar cuando los movimientos van a la mitad.
            ['movimientos', 'alertas', 'SS', 2 * self::DAY],
            ['movimientos', 'migracion', 'FS', 0],
            ['migracion', 'pruebas', 'FS', 0],
            ['pruebas', 'capacitacion', 'FS', 0],
            ['capacitacion', 'arranque', 'FS', 0],
            ['alertas', 'arranque', 'FS', 0],
        ];

        foreach ($links as [$from, $to, $type, $lag]) {
            TaskDependency::query()->create([
                'project_id' => $project->id,
                'predecessor_id' => $tasks[$from]->id,
                'successor_id' => $tasks[$to]->id,
                'type' => $type,
                'lag_minutes' => $lag,
            ]);
        }
    }

    /**
     * @param  array<string, Task>  $tasks
     */
    private function resources(Project $project, array $tasks): void
    {
        $people = [
            'ana' => ['Ana Rivera', 'Analista de sistemas', 100],
            'luis' => ['Luis Ortega', 'Desarrollador', 100],
            'carmen' => ['Carmen Díaz', 'Jefa de almacén', 50],
        ];

        $resources = [];

        foreach ($people as $key => [$name, $role, $capacity]) {
            $resource = new Resource;
            $resource->fill([
                'project_id' => $project->id,
                'name' => $name,
                'role_title' => $role,
                'capacity_percent' => $capacity,
            ]);
            $resource->save();

            $resources[$key] = $resource;
        }

        $assignments = [
            ['ana', 'entrevistas', 100],
            ['ana', 'requerimientos', 100],
            ['luis', 'modelo', 100],
            ['luis', 'catalogo', 100],
            ['luis', 'movimientos', 100],
            // Problema plantado: Luis también lleva las alertas, que se traslapan
            // con los movimientos por la liga SS. Va a salir al 200 %.
            ['luis', 'alertas', 100],
            ['carmen', 'pruebas', 50],
            ['carmen', 'capacitacion', 50],
        ];

        foreach ($assignments as [$person, $taskKey, $units]) {
            TaskAssignment::query()->create([
                'task_id' => $tasks[$taskKey]->id,
                'resource_id' => $resources[$person]->id,
                'units_percent' => $units,
            ]);
        }

        // Segundo problema plantado: la migración es crítica y nadie la lleva.
        $tasks['entrevistas']->update(['owner_id' => $project->owner_id]);
        $tasks['requerimientos']->update(['owner_id' => $project->owner_id]);
    }
}
