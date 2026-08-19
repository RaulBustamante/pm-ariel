<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Baseline;
use App\Models\BaselineTask;
use App\Models\Project;
use App\Models\ProjectLogEntry;
use App\Models\Resource;
use App\Models\Risk;
use App\Models\Stakeholder;
use App\Models\Task;
use App\Support\Costing\EarnedValue;
use App\Support\Costing\TaskCost;
use App\Support\Scheduling\ProjectDurations;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * De dónde salen los renglones de cada documento derivado.
 *
 * Un método por fuente, y **ninguno consulta nada que no exista ya**: el
 * diccionario de la EDT sale de las tareas, el informe de riesgos del registro
 * de riesgos, el de lecciones del registro de lecciones. Esa es toda la promesa
 * de la especie `derived` — se generan solos porque el dato ya está.
 *
 * Los valores salen **formateados para leerse**, no crudos. Un informe que
 * imprime `threat` y `1` en vez de «Amenaza» y «Muy baja» obliga a traducir
 * mentalmente en cada renglón, y es la diferencia entre un documento que se
 * manda a un cliente y uno que hay que reescribir antes.
 */
final class DerivedSources
{
    public function __construct(
        private readonly EarnedValue $earnedValue,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function rowsFor(string $source, Project $project): array
    {
        return match ($source) {
            'wbsDictionary' => $this->wbsDictionary($project),
            'activityAttributes' => $this->activityAttributes($project),
            'resourceBreakdown' => $this->resourceBreakdown($project),
            'costBaseline' => $this->costBaseline($project),
            'riskReport' => $this->riskReport($project),
            'stakeholderEngagement' => $this->stakeholderEngagement($project),
            'scheduleForecast' => $this->scheduleForecast($project),
            'lessonsLearned' => $this->lessonsLearned($project),
            'finalReport' => $this->finalReport($project),
            default => [],
        };
    }

    /**
     * El diccionario de la EDT: cada paquete y cada tarea con qué es, quién
     * responde, cuánto dura y cuánto cuesta.
     *
     * Incluye los resúmenes a propósito. El diccionario del PMI documenta **los
     * paquetes de trabajo**, no solo las hojas: dejar fuera los encabezados
     * daría una lista de tareas, que ya existe y se llama de otra forma.
     *
     * @return list<array<string, mixed>>
     */
    private function wbsDictionary(Project $project): array
    {
        $durations = ProjectDurations::for($project);
        $rows = [];

        foreach ($this->tasks($project) as $task) {
            $rows[] = [
                'wbs' => (string) ($task->wbs_code ?? ''),
                'name' => (string) $task->name,
                'detail' => $task->description,
                'owner' => $task->owner?->name,
                'duration' => $durations->toHuman((int) $task->duration_minutes),
                'start' => $task->early_start?->format('d/m/Y'),
                'finish' => $task->early_finish?->format('d/m/Y'),
                'cost' => number_format(TaskCost::of($task)['total'], 2),
                'is_summary' => (bool) $task->is_summary,
            ];
        }

        return $rows;
    }

    /**
     * Los atributos de cada actividad: lo que el estándar pide documentar de una
     * tarea más allá de su nombre y su duración.
     *
     * @return list<array<string, mixed>>
     */
    private function activityAttributes(Project $project): array
    {
        $durations = ProjectDurations::for($project);

        $names = Task::query()
            ->where('project_id', $project->id)
            ->pluck('name', 'id');

        $rows = [];

        foreach ($this->tasks($project) as $task) {
            if ($task->is_summary) {
                continue;
            }

            $predecessors = $task->predecessorLinks
                ->map(fn ($link): string => (string) ($names[$link->predecessor_id] ?? ''))
                ->filter()
                ->implode(', ');

            $rows[] = [
                'wbs' => (string) ($task->wbs_code ?? ''),
                'name' => (string) $task->name,
                'duration' => $durations->toHuman((int) $task->duration_minutes),
                'predecessors' => $predecessors === '' ? null : $predecessors,
                'owner' => $task->owner?->name,
                'constraint' => __('constraints.'.($task->constraint_type ?? 'ASAP')),
                'float' => $task->total_float_minutes === null
                    ? null
                    : $durations->toHuman((int) $task->total_float_minutes),
                'critical' => $task->is_critical ? __('tasks.critical') : '—',
            ];
        }

        return $rows;
    }

    /**
     * La RBS: los recursos agrupados por especie.
     *
     * @return list<array<string, mixed>>
     */
    private function resourceBreakdown(Project $project): array
    {
        $resources = Resource::query()
            ->where('project_id', $project->id)
            ->orderBy('type')
            ->orderBy('name')
            ->get();

        $rows = [];

        foreach ($resources as $resource) {
            $isMaterial = $resource->isMaterial();

            $rows[] = [
                'kind' => __("resources.type_{$resource->type}"),
                'name' => (string) $resource->name,
                'role' => $resource->role_title,
                // La tarifa se dice en la unidad de cada especie. Ponerle «por
                // hora» a un material sería un dato falso.
                'rate' => $isMaterial
                    ? ($resource->cost_per_unit === null ? null : number_format((float) $resource->cost_per_unit, 2).' / '.($resource->unit_of_measure ?: '?'))
                    : ($resource->cost_per_hour === null ? null : number_format((float) $resource->cost_per_hour, 2).' / h'),
                'capacity' => $isMaterial ? null : $resource->capacity_percent.' %',
                'supplier' => $resource->supplier,
                'origin' => $resource->is_external ? __('resources.external') : __('resources.internal_origin'),
            ];
        }

        return $rows;
    }

    /**
     * La línea base de costos: lo comprometido contra lo de hoy, renglón por
     * renglón.
     *
     * Sin línea base no hay documento, y se dice: inventar una comparación
     * contra el plan de hoy daría varianza cero en todo y haría creer que el
     * costo no se ha movido.
     *
     * @return list<array<string, mixed>>
     */
    private function costBaseline(Project $project): array
    {
        /** @var Baseline|null $baseline */
        $baseline = $project->baselines()->where('is_active', true)->latest('captured_at')->first()
            ?? $project->baselines()->latest('captured_at')->first();

        if ($baseline === null) {
            return [];
        }

        /** @var array<int, BaselineTask> $frozen */
        $frozen = $baseline->tasks()->get()->keyBy('task_id')->all();

        $rows = [];

        foreach ($this->tasks($project) as $task) {
            if ($task->is_summary) {
                continue;
            }

            $line = $frozen[$task->id] ?? null;
            $current = TaskCost::of($task)['total'];
            $committed = $line === null ? 0.0 : (float) $line->cost;

            $rows[] = [
                'wbs' => (string) ($task->wbs_code ?? ''),
                'name' => (string) $task->name,
                // Lo que nació después de la línea base no tenía compromiso: se
                // dice, en vez de enseñar un cero que se lee como «era gratis».
                'baseline_cost' => $line === null ? '—' : number_format($committed, 2),
                'current_cost' => number_format($current, 2),
                'variance' => $line === null ? '—' : number_format($current - $committed, 2),
                'is_over' => $line !== null && $current > $committed,
            ];
        }

        return $rows;
    }

    /**
     * El informe de riesgos, sobre el registro que ya existe.
     *
     * @return list<array<string, mixed>>
     */
    private function riskReport(Project $project): array
    {
        $risks = Risk::query()
            ->where('project_id', $project->id)
            ->with('owner')
            ->orderBy('code')
            ->get();

        // De mayor a menor exposición: un informe de riesgos ordenado por clave
        // obliga a leerlo entero para encontrar el que importa.
        $risks = $risks->sortByDesc(fn (Risk $risk): int => $risk->score())->values();

        $rows = [];

        foreach ($risks as $risk) {
            $rows[] = [
                'code' => (string) $risk->code,
                'description' => (string) $risk->description,
                'category' => $risk->category,
                'kind' => __("initiation.risk_kind_{$risk->kind}"),
                'probability' => (string) $risk->probability,
                'impact' => (string) $risk->impact,
                'level' => __("initiation.level_{$risk->level()}"),
                'status' => __("initiation.status_{$risk->status}"),
                'owner' => $risk->owner?->name,
                'is_high' => in_array($risk->level(), [Risk::LEVEL_HIGH, Risk::LEVEL_CRITICAL], strict: true),
            ];
        }

        return $rows;
    }

    /**
     * El plan de involucramiento: qué hacer con cada interesado, según dónde
     * cae en la matriz de poder e interés.
     *
     * @return list<array<string, mixed>>
     */
    private function stakeholderEngagement(Project $project): array
    {
        $stakeholders = Stakeholder::query()
            ->where('project_id', $project->id)
            ->orderByDesc('power')
            ->orderByDesc('interest')
            ->get();

        $rows = [];

        foreach ($stakeholders as $stakeholder) {
            $power = (int) $stakeholder->power;
            $interest = (int) $stakeholder->interest;

            // El cuadrante **es** la estrategia que recomienda el estándar, y es
            // lo único de este documento que el sistema puede deducir solo.
            $quadrant = match (true) {
                $power >= 4 && $interest >= 4 => 'manage',
                $power >= 4 => 'satisfy',
                $interest >= 4 => 'inform',
                default => 'monitor',
            };

            $rows[] = [
                'name' => (string) $stakeholder->name,
                'organization' => $stakeholder->organization,
                'role' => $stakeholder->role_title,
                'power' => (string) $power,
                'interest' => (string) $interest,
                'quadrant' => __("derived.quadrant_{$quadrant}"),
                'strategy' => $stakeholder->engagement_strategy,
                'expectations' => $stakeholder->expectations,
            ];
        }

        return $rows;
    }

    /**
     * El pronóstico del cronograma: el índice de avance llevado al calendario.
     *
     * **Es lo que el valor ganado no contesta.** El SPI dice «vas al 68 % del
     * ritmo debido» y nadie sabe qué hacer con eso; llevado a fechas dice «al
     * ritmo de hoy, esto acaba el 14 de noviembre en vez del 30 de septiembre»,
     * que es la frase que cambia una junta.
     *
     * @return list<array<string, mixed>>
     */
    private function scheduleForecast(Project $project): array
    {
        $evm = $this->earnedValue->for($project);
        $spi = $evm['spi'];

        $start = $project->planned_start;

        // `max()` devuelve texto y no una fecha: se traduce aquí, una vez, en
        // vez de dejar que cada uso lo descubra.
        $latest = Task::query()
            ->where('project_id', $project->id)
            ->where('is_summary', false)
            ->max('early_finish');

        $finish = is_string($latest) ? Carbon::parse($latest) : null;

        $rows = [
            $this->measure('planned_finish', $finish?->format('d/m/Y') ?? '—', null),
            $this->measure('spi', $spi === null ? '—' : number_format($spi, 3),
                $spi === null ? __('derived.no_spi') : null),
        ];

        // El pronóstico solo se calcula si hay con qué. Un SPI de cero o nulo
        // daría una fecha absurda o una división por cero, y una fecha absurda
        // en un informe se cree.
        if ($spi !== null && $spi > 0 && $start !== null && $finish !== null) {
            $planned = $start->diffInDays($finish);
            $forecast = $start->copy()->addDays((int) round($planned / $spi));
            $slip = (int) round($planned / $spi) - $planned;

            $rows[] = $this->measure(
                'forecast_finish',
                $forecast->format('d/m/Y'),
                $slip > 0
                    ? __('derived.forecast_late', ['days' => $slip])
                    : __('derived.forecast_early', ['days' => abs($slip)]),
            );
        } else {
            $rows[] = $this->measure('forecast_finish', '—', __('derived.forecast_blocked'));
        }

        return $rows;
    }

    /**
     * El informe de lecciones, sobre el registro que ya crece durante el
     * proyecto. No se vuelve a capturar nada: se ordena y se imprime.
     *
     * @return list<array<string, mixed>>
     */
    private function lessonsLearned(Project $project): array
    {
        $entries = ProjectLogEntry::query()
            ->where('project_id', $project->id)
            ->where('document_code', 'lessons_learned_register')
            ->orderBy('occurred_on')
            ->get();

        $rows = [];

        foreach ($entries as $entry) {
            $rows[] = [
                'reference' => $entry->reference(),
                'occurred_on' => $entry->occurred_on?->format('d/m/Y'),
                'title' => (string) $entry->title,
                'detail' => $entry->detail,
                'outcome' => $entry->outcome,
                'status' => __("logs.status_{$entry->status}"),
            ];
        }

        return $rows;
    }

    /**
     * El informe final: las cifras con las que cierra el proyecto.
     *
     * @return list<array<string, mixed>>
     */
    private function finalReport(Project $project): array
    {
        $evm = $this->earnedValue->for($project);

        $tasks = Task::query()->where('project_id', $project->id)->where('is_summary', false);
        $total = (clone $tasks)->count();
        $done = (clone $tasks)->where('percent_complete', '>=', 100)->count();

        $rows = [
            $this->measure('tasks_done', "{$done} / {$total}", null),
            $this->measure('progress', $evm['progress'].' %', null),
            $this->measure('budget', number_format($evm['bac'], 2), null),
            $this->measure(
                'actual_cost',
                $evm['ac'] === null ? '—' : number_format($evm['ac'], 2),
                $evm['ac'] === null ? __('derived.no_actuals') : null,
            ),
            $this->measure(
                'cost_index',
                $evm['cpi'] === null ? '—' : number_format($evm['cpi'], 3),
                $evm['cpi'] === null ? null : ($evm['cpi'] < 1 ? __('evm.cost_over') : __('evm.cost_ok')),
            ),
            $this->measure(
                'schedule_index',
                $evm['spi'] === null ? '—' : number_format($evm['spi'], 3),
                $evm['spi'] === null ? null : ($evm['spi'] < 1 ? __('evm.schedule_late') : __('evm.schedule_ok')),
            ),
        ];

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function measure(string $key, string $value, ?string $reading): array
    {
        return [
            'measure' => __("derived.measure_{$key}"),
            'value' => $value,
            'reading' => $reading,
        ];
    }

    /**
     * Las tareas en el orden del plan, con lo que hace falta para costearlas.
     *
     * @return Collection<int, Task>
     */
    private function tasks(Project $project): Collection
    {
        return Task::query()
            ->where('project_id', $project->id)
            ->with(['owner', 'assignments.resource', 'predecessorLinks'])
            ->orderBy('sort_order')
            ->get();
    }
}
