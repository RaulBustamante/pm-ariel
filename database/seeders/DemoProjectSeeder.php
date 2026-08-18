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
use App\Support\Documents\ProjectLog;
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

    /**
     * Avance capturado y **cuánto costó de verdad** lo avanzado.
     *
     * El segundo número es el factor contra el presupuesto: 1.0 salió como se
     * estimó, 1.4 salió un 40 % más caro. Dos tareas salen caras a propósito —
     * un ejemplo donde todo costó lo presupuestado da un índice de costo de
     * 1.00, que es justo el número que no demuestra que el indicador sirva.
     *
     * Sin costo real capturado el valor ganado **se niega a calcular** el índice
     * de costo en vez de deducirlo del avance, y el ejemplo enseñaría un informe
     * con la mitad de las casillas vacías.
     *
     * @var array<string, array{int, float}>
     */
    private const PROGRESS = [
        'entrevistas' => [100, 1.0],
        'inventario' => [100, 1.4],
        'procesos' => [100, 1.0],
        'requerimientos' => [80, 1.25],
        'revision_req' => [40, 1.0],
        'servidor' => [100, 1.0],
        'ambiente_dev' => [100, 0.9],
        'ambiente_qa' => [30, 1.0],
        'limpieza' => [25, 1.0],
    ];

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
            // Arranca tres semanas atrás, no hoy.
            //
            // Un ejemplo que empieza hoy no tiene nada que contar: el corte
            // semanal sale con las cuatro listas vacías y el atraso contra la
            // línea base en cero, que es justo lo que hay que poder enseñar.
            // Con historia, el demo muestra tareas cerradas, tareas que se
            // pasaron de su fecha y una entrega que ya se corrió.
            'planned_start' => now()->startOfWeek()->subWeeks(3)->setTime(9, 0),
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

        $this->baselineWithSlip($project, $tasks);
        // Después de la línea base: el costo real se siembra contra lo que se
        // congeló, que es contra lo que el valor ganado lo compara.
        $this->actualCosts($project, $tasks);
        $this->stampActualDates($project);
        $this->logs($project, $owner);

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

    /**
     * Congela el plan y **después** lo desvía.
     *
     * Una línea base capturada sobre el plan final da varianza cero en todos
     * los renglones, y entonces la pantalla de comparación se ve vacía y nadie
     * entiende para qué sirve. Aquí se captura primero y luego se alarga una
     * tarea de la ruta crítica, que es exactamente lo que pasa en un proyecto
     * real: el compromiso se firmó antes de que las cosas se complicaran.
     *
     * @param  array<string, Task>  $tasks
     */
    private function baselineWithSlip(Project $project, array $tasks): void
    {
        app(BaselineManager::class)->capture(
            $project,
            'Línea base aprobada por dirección',
            'Capturada al aprobarse el proyecto.',
        );

        // Se alargan tareas **de la ruta crítica**, elegidas del resultado del
        // motor y no a mano. Alargar una que tiene holgura no mueve la entrega,
        // la varianza sale en cero y la pantalla de comparación se ve vacía —
        // que es justo lo contrario de lo que el ejemplo debe enseñar.
        $critical = Task::query()
            ->where('project_id', $project->id)
            ->where('is_critical', true)
            ->where('is_summary', false)
            ->where('duration_minutes', '>', 0)
            ->orderByDesc('duration_minutes')
            ->limit(2)
            ->get();

        foreach ($critical as $task) {
            // Un 60 % más de lo estimado: la desviación típica de una tarea que
            // resultó más complicada de lo que parecía en el papel.
            $task->update(['duration_minutes' => (int) round($task->duration_minutes * 1.6)]);
        }

        app(ProjectScheduler::class)->reschedule($project->refresh());
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
     * El plan del proyecto de ejemplo.
     *
     * Cincuenta y tantas tareas y no diez a propósito: con diez todo se ve cómodo, y las
     * cosas que hay que poder enseñar —el Gantt paginado, el tope de renglones,
     * un tablero que necesita carriles— solo aparecen con un plan de tamaño
     * real.
     *
     * @return array<string, Task>
     */
    private function tasks(Project $project): array
    {
        $plan = [
            // clave           nombre                                    días  padre
            ['analisis',       'Análisis',                                 0,  null],
            ['entrevistas',    'Entrevistas con almacén',                  3,  'analisis'],
            ['inventario',     'Levantamiento del inventario actual',      4,  'analisis'],
            ['procesos',       'Mapeo del proceso de entradas y salidas',  3,  'analisis'],
            ['requerimientos', 'Documento de requerimientos',              4,  'analisis'],
            ['revision_req',   'Revisión con el área',                     2,  'analisis'],
            ['aprobacion',     'Aprobación de requerimientos',             0,  'analisis'],

            ['infra',          'Infraestructura',                          0,  null],
            ['servidor',       'Solicitud del servidor a TI',              5,  'infra'],
            ['ambiente_dev',   'Ambiente de desarrollo',                   2,  'infra'],
            ['ambiente_qa',    'Ambiente de pruebas',                      2,  'infra'],
            ['respaldos',      'Respaldos programados y probados',         3,  'infra'],

            ['construccion',   'Construcción',                             0,  null],
            ['modelo',         'Modelo de datos',                          3,  'construccion'],
            ['accesos',        'Accesos y perfiles de usuario',            4,  'construccion'],
            ['catalogo',       'Catálogo de refacciones',                  5,  'construccion'],
            ['ubicaciones',    'Ubicaciones y almacenes',                  3,  'construccion'],
            ['movimientos',    'Entradas y salidas',                       6,  'construccion'],
            ['ajustes',        'Ajustes de inventario',                    3,  'construccion'],
            ['alertas',        'Alertas de existencia mínima',             4,  'construccion'],
            ['reportes',       'Reportes de existencias y consumo',        5,  'construccion'],
            ['etiquetas',      'Impresión de etiquetas',                   3,  'construccion'],
            ['lector',         'Lectura de código de barras',              4,  'construccion'],
            ['cierre_dev',     'Congelamiento de desarrollo',              0,  'construccion'],

            ['calidad',        'Calidad',                                  0,  null],
            ['pruebas_unit',   'Pruebas del equipo de desarrollo',         4,  'calidad'],
            ['pruebas_int',    'Pruebas de integración',                   3,  'calidad'],
            ['carga',          'Prueba de carga con inventario completo',  2,  'calidad'],
            ['correcciones',   'Corrección de hallazgos',                  5,  'calidad'],
            ['revalidacion',   'Revalidación',                             2,  'calidad'],

            ['datos',          'Datos',                                    0,  null],
            ['limpieza',       'Limpieza del catálogo histórico',          6,  'datos'],
            ['mapeo',          'Mapeo de claves viejas a nuevas',          4,  'datos'],
            ['migracion',      'Migración de datos históricos',            4,  'datos'],
            ['conciliacion',   'Conciliación contra el conteo físico',     3,  'datos'],
            ['visto_bueno',    'Visto bueno de almacén',                   0,  'datos'],

            ['gente',          'Gente',                                    0,  null],
            ['manual',         'Manual de usuario',                        4,  'gente'],
            ['material',       'Material de capacitación',                 3,  'gente'],
            ['capacitacion',   'Capacitación del personal',                2,  'gente'],
            ['capacitacion_2', 'Capacitación del segundo turno',           2,  'gente'],
            ['acompanamiento', 'Acompañamiento la primera semana',         5,  'gente'],

            ['despliegue',     'Puesta en marcha',                         0,  null],
            ['plan_arranque',  'Plan de arranque y marcha atrás',          2,  'despliegue'],
            ['ventana',        'Ventana de mantenimiento acordada',        1,  'despliegue'],
            ['pase',           'Pase a producción',                        1,  'despliegue'],
            ['verificacion',   'Verificación posterior al pase',           1,  'despliegue'],
            ['arranque',       'Arranque en producción',                   0,  'despliegue'],

            ['cierre',         'Cierre',                                   0,  null],
            ['estabilizacion', 'Estabilización',                          10,  'cierre'],
            ['medicion',       'Medición contra el criterio de éxito',     3,  'cierre'],
            ['leccion',        'Lecciones aprendidas',                     2,  'cierre'],
            ['entrega_ti',     'Entrega formal a TI',                      2,  'cierre'],
            ['cierre_formal',  'Cierre formal del proyecto',               0,  'cierre'],
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

        // Un poco de avance real, para que las barras no se vean todas vacías y
        // para que el corte semanal tenga algo que contar. Las fechas reales de
        // cierre se ponen después de programar, en `stampActualDates()`: antes
        // de eso las tareas todavía no tienen fechas calculadas.
        foreach (self::PROGRESS as $key => [$percent, $factor]) {
            $tasks[$key]->update(['percent_complete' => $percent]);
        }

        return $tasks;
    }

    /**
     * Cuándo se cerró de verdad cada tarea terminada.
     *
     * El modelo las anota solas al capturar avance, pero el sembrado marca todo
     * en el mismo instante: quedarían las nueve cerradas hoy, y el corte semanal
     * las mostraría todas en la misma semana. Un ejemplo así no enseña lo que el
     * documento hace, que es distinguir esta semana de las anteriores.
     *
     * Se cierran en su fecha planeada, salvo una que se pasa dos días: sin al
     * menos una desviación, la comparación contra la línea base no se entiende.
     */
    private function stampActualDates(Project $project): void
    {
        $done = $project->tasks()
            ->where('percent_complete', '>=', 100)
            ->whereNotNull('early_finish')
            ->get();

        foreach ($done as $index => $task) {
            $task->forceFill([
                'actual_start' => $task->early_start,
                // La última cae dentro de la semana en curso: sin al menos un
                // cierre reciente, el bloque «cerrado esta semana» del corte
                // sale vacío y no se entiende para qué está.
                'actual_finish' => $index === $done->count() - 1
                    // Miércoles de la semana en curso: cae dentro de la ventana
                    // sin importar qué día se siembre el ejemplo. Con `subDay()`
                    // se salía por un día cuando se sembraba en lunes.
                    ? now()->startOfWeek()->addDays(2)->setTime(17, 0)
                    : ($index === 0
                        ? $task->early_finish?->copy()->addDays(2)
                        : $task->early_finish),
            ])->save();
        }
    }

    /**
     * Lo que de verdad costó cada tarea avanzada.
     *
     * Se calcula contra el **presupuesto congelado en la línea base**, que es
     * contra lo que el valor ganado compara. Calcularlo contra el costo de hoy
     * daría un índice torcido en las dos tareas que la línea base alargó
     * después, y el ejemplo enseñaría un sobrecosto que en realidad es un
     * cambio de plan.
     *
     * @param  array<string, Task>  $tasks
     */
    private function actualCosts(Project $project, array $tasks): void
    {
        $baseline = $project->baselines()->latest('captured_at')->first();

        if ($baseline === null) {
            return;
        }

        $frozen = $baseline->tasks()->get()->keyBy('task_id');

        foreach (self::PROGRESS as $key => [$percent, $factor]) {
            $line = $frozen[$tasks[$key]->id] ?? null;
            $budget = (float) ($line === null ? $tasks[$key]->cost : $line->cost);

            $tasks[$key]->update([
                'actual_cost' => round($budget * $percent / 100 * $factor, 2),
            ]);
        }
    }

    /**
     * Renglones en cuatro de los catorce registros del PMI.
     *
     * Un registro vacío no demuestra el registro: demuestra la pantalla. Lo que
     * hay que poder enseñar es un pendiente vencido en rojo, una incidencia
     * cerrada con su desenlace y una decisión con su porqué, que es lo único que
     * explica para qué sirve anotar.
     *
     * Las fechas van relativas al arranque del ejemplo —tres semanas atrás— para
     * que el vencido siga vencido sin importar qué día se siembre.
     */
    private function logs(Project $project, User $owner): void
    {
        $log = app(ProjectLog::class);
        $start = now()->startOfWeek()->subWeeks(3);

        $entries = [
            ['issue_log', [
                'occurred_on' => $start->copy()->addDays(4)->toDateString(),
                'title' => 'El servidor de pruebas no llegó en la fecha comprometida',
                'detail' => "TI lo comprometió para el jueves.\nSe retrasa el ambiente de QA y con él las pruebas de integración.",
                'status' => 'resolved',
                'owner_id' => $owner->id,
                'due_on' => $start->copy()->addDays(9)->toDateString(),
                'priority' => 'high',
                'outcome' => 'Se habilitó una máquina prestada del área de sistemas mientras llega la definitiva.',
            ]],
            ['issue_log', [
                'occurred_on' => $start->copy()->addDays(11)->toDateString(),
                'title' => 'El catálogo histórico trae claves duplicadas',
                'detail' => 'Aparecen 340 refacciones con dos claves distintas. Hay que decidir cuál se conserva antes de migrar.',
                'status' => 'in_progress',
                'owner_id' => $owner->id,
                'due_on' => $start->copy()->addDays(15)->toDateString(),
                'priority' => 'critical',
            ]],
            ['decision_log', [
                'occurred_on' => $start->copy()->addDays(6)->toDateString(),
                'title' => 'Se arranca con lectura de código de barras y no con RFID',
                'detail' => 'El RFID cuesta cuatro veces más y el almacén ya tiene lectores de código de barras.',
                'status' => 'decided',
                'owner_id' => $owner->id,
                'outcome' => 'Se revisa el punto al cierre del proyecto, con el consumo real medido.',
            ]],
            ['action_item_log', [
                'occurred_on' => $start->copy()->addDays(8)->toDateString(),
                'title' => 'Conseguir el conteo físico del almacén para conciliar',
                'detail' => 'Sin el conteo no se puede cerrar la conciliación de datos.',
                'status' => 'open',
                'owner_id' => $owner->id,
                // Vencido a propósito: un registro de pendientes donde nada se
                // vence no enseña lo único que de verdad hace falta ver.
                'due_on' => $start->copy()->addDays(12)->toDateString(),
                'priority' => 'high',
            ]],
            ['lessons_learned_register', [
                'occurred_on' => $start->copy()->addDays(10)->toDateString(),
                'title' => 'Pedir la infraestructura a TI antes de arrancar, no durante',
                'detail' => 'Los cinco días de la solicitud del servidor se descubrieron cuando ya estorbaban.',
                'status' => 'captured',
                'owner_id' => $owner->id,
                'outcome' => 'En el siguiente proyecto la solicitud entra como primera tarea del plan.',
            ]],
        ];

        foreach ($entries as [$code, $data]) {
            $log->record($project, $code, $data);
        }
    }

    /**
     * @param  array<string, Task>  $tasks
     */
    private function dependencies(Project $project, array $tasks): void
    {
        $links = [
            // Análisis
            ['entrevistas', 'inventario', 'FS', 0],
            ['entrevistas', 'procesos', 'SS', 0],
            ['inventario', 'requerimientos', 'FS', 0],
            ['procesos', 'requerimientos', 'FS', 0],
            ['requerimientos', 'revision_req', 'FS', 0],
            ['revision_req', 'aprobacion', 'FS', 0],

            // Infraestructura: arranca en paralelo, no espera al análisis.
            ['servidor', 'ambiente_dev', 'FS', 0],
            ['ambiente_dev', 'ambiente_qa', 'FS', 0],
            ['ambiente_qa', 'respaldos', 'FS', 0],

            // Construcción
            ['aprobacion', 'modelo', 'FS', 0],
            ['ambiente_dev', 'modelo', 'FS', 0],
            ['modelo', 'accesos', 'FS', 0],
            ['modelo', 'catalogo', 'FS', 0],
            ['catalogo', 'ubicaciones', 'FS', 0],
            ['catalogo', 'movimientos', 'FS', 0],
            ['movimientos', 'ajustes', 'FS', 0],
            // Las alertas pueden empezar cuando los movimientos van a la mitad.
            ['movimientos', 'alertas', 'SS', 2 * self::DAY],
            ['movimientos', 'reportes', 'FS', 0],
            ['ubicaciones', 'etiquetas', 'FS', 0],
            ['etiquetas', 'lector', 'FS', 0],
            ['reportes', 'cierre_dev', 'FS', 0],
            ['alertas', 'cierre_dev', 'FS', 0],
            ['lector', 'cierre_dev', 'FS', 0],
            ['ajustes', 'cierre_dev', 'FS', 0],
            ['accesos', 'cierre_dev', 'FS', 0],

            // Calidad
            ['cierre_dev', 'pruebas_unit', 'FS', 0],
            ['ambiente_qa', 'pruebas_unit', 'FS', 0],
            ['pruebas_unit', 'pruebas_int', 'FS', 0],
            ['pruebas_int', 'carga', 'FS', 0],
            ['carga', 'correcciones', 'FS', 0],
            ['correcciones', 'revalidacion', 'FS', 0],

            // Datos: la limpieza puede empezar antes de que exista el sistema.
            ['aprobacion', 'limpieza', 'FS', 0],
            ['limpieza', 'mapeo', 'FS', 0],
            ['mapeo', 'migracion', 'FS', 0],
            ['cierre_dev', 'migracion', 'FS', 0],
            ['migracion', 'conciliacion', 'FS', 0],
            ['conciliacion', 'visto_bueno', 'FS', 0],

            // Gente
            ['cierre_dev', 'manual', 'FS', 0],
            ['manual', 'material', 'FS', 0],
            ['revalidacion', 'capacitacion', 'FS', 0],
            ['material', 'capacitacion', 'FS', 0],
            ['capacitacion', 'capacitacion_2', 'FS', 0],

            // Puesta en marcha
            ['revalidacion', 'plan_arranque', 'FS', 0],
            ['plan_arranque', 'ventana', 'FS', 0],
            ['visto_bueno', 'pase', 'FS', 0],
            ['ventana', 'pase', 'FS', 0],
            ['capacitacion_2', 'pase', 'FS', 0],
            ['respaldos', 'pase', 'FS', 0],
            ['pase', 'verificacion', 'FS', 0],
            ['verificacion', 'arranque', 'FS', 0],
            ['arranque', 'acompanamiento', 'FS', 0],

            // Cierre
            ['acompanamiento', 'estabilizacion', 'SS', 0],
            ['estabilizacion', 'medicion', 'FS', 0],
            ['medicion', 'leccion', 'FS', 0],
            ['leccion', 'entrega_ti', 'FS', 0],
            ['entrega_ti', 'cierre_formal', 'FS', 0],
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
            ['ana', 'inventario', 100],
            ['ana', 'requerimientos', 100],
            ['ana', 'manual', 100],
            ['luis', 'modelo', 100],
            ['luis', 'catalogo', 100],
            ['luis', 'movimientos', 100],
            // Problema plantado: Luis también lleva las alertas, que se traslapan
            // con los movimientos por la liga SS. Va a salir al 200 %.
            ['luis', 'alertas', 100],
            ['luis', 'reportes', 100],
            ['carmen', 'procesos', 50],
            ['carmen', 'conciliacion', 50],
            ['carmen', 'capacitacion', 50],
            ['carmen', 'capacitacion_2', 50],
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

        /*
        | Tarifas y un material, para que el reporte de costos tenga que enseñar.
        |
        | Sin tarifas el ejemplo muestra un costo de cero, que es exactamente el
        | caso que no permite juzgar si la pantalla sirve. Y sin material, la
        | mitad del motor de costo —cantidad × costo unitario— queda sin
        | demostrar.
        */
        foreach ([
            'Luis Ortega' => 480.0,
            'Ana Barrera' => 620.0,
            'Miguel Fuentes' => 390.0,
        ] as $who => $rate) {
            Resource::query()
                ->where('project_id', $project->id)
                ->where('name', $who)
                ->update(['cost_per_hour' => $rate]);
        }

        $licencias = Resource::query()->create([
            'project_id' => $project->id,
            'name' => 'Licencias de servidor',
            'type' => Resource::TYPE_MATERIAL,
            'unit_of_measure' => 'licencia',
            'cost_per_unit' => 8400.00,
            'supplier' => 'Distribuidor autorizado',
            'is_external' => true,
        ]);

        if (isset($tasks['servidor'])) {
            TaskAssignment::query()->create([
                'task_id' => $tasks['servidor']->id,
                'resource_id' => $licencias->id,
                'units_percent' => 0,
                'quantity' => 4,
            ]);
        }
    }
}
