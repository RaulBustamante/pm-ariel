<?php

declare(strict_types=1);

namespace App\Support\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Models\Project;
use App\Models\ProjectCharter;
use App\Models\ProjectTemplate;
use App\Models\Risk;
use App\Models\Stakeholder;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Arranca el recorrido de inicio: crea el proyecto, su acta vacía y —si se eligió
 * una plantilla— precarga los riesgos e interesados típicos de ese tipo de
 * proyecto.
 *
 * Lo precargado nace **editable y borrable**. Una plantilla es un punto de
 * partida, no una afirmación sobre este proyecto en particular; si el usuario no
 * pudiera quitar lo que no aplica, el registro de riesgos diría cosas falsas
 * desde el primer día.
 */
final class InitiationStarter
{
    public function __construct(
        private readonly SuggestsContent $suggestions,
    ) {}

    /**
     * @param  array{code: string, name: string, description?: ?string, org_unit_id?: ?int, currency?: ?string, planned_start?: ?string, planned_finish?: ?string}  $attributes
     */
    public function start(array $attributes, User $owner, ?ProjectTemplate $template = null): Project
    {
        return DB::transaction(function () use ($attributes, $owner, $template): Project {
            $project = new Project;
            $project->fill($attributes);
            $project->forceFill([
                'owner_id' => $owner->id,
                'status' => 'draft',
            ])->save();

            // Quien arranca el proyecto es su gerente. Sin esto no podría editar
            // lo que acaba de crear: la regla 2 exige membresía, no jerarquía.
            $project->members()->attach($owner->id, ['project_role' => Project::ROLE_MANAGER]);

            ProjectCharter::query()->create([
                'project_id' => $project->id,
                'template_id' => $template?->id,
                'current_step' => InitiationStep::Justification->value,
                'completed_steps' => [],
            ]);

            if ($template !== null) {
                $project->refresh();
                $this->preload($project);
            }

            return $project->refresh();
        });
    }

    /**
     * Convierte los entregables del asistente en tareas de primer nivel.
     *
     * Es lo que hace que el asistente termine en un plan y no en una pantalla
     * vacía. Duración cero: son hitos hasta que alguien les ponga trabajo, y
     * ponerles una duración inventada sería peor — el plan diría fechas que
     * nadie decidió.
     *
     * @param  list<string>  $deliverables
     */
    public function seedDeliverables(Project $project, array $deliverables): int
    {
        if ($deliverables === []) {
            return 0;
        }

        return DB::transaction(function () use ($project, $deliverables): int {
            $base = (int) Task::query()->where('project_id', $project->id)->max('sort_order');

            foreach ($deliverables as $index => $name) {
                $task = new Task;
                $task->fill([
                    'project_id' => $project->id,
                    'name' => $name,
                    'duration_minutes' => 0,
                    'sort_order' => $base + $index + 1,
                ]);
                $task->save();
            }

            return count($deliverables);
        });
    }

    /**
     * Precarga el catálogo de la plantilla. Nada de esto se marca como completo:
     * el usuario todavía tiene que revisarlo, y decir que ya está sería mentira.
     */
    public function preload(Project $project): void
    {
        foreach ($this->suggestions->suggestStakeholders($project) as $index => $suggested) {
            Stakeholder::query()->create([
                'project_id' => $project->id,
                'name' => (string) ($suggested['name'] ?? __('initiation.unnamed_stakeholder')),
                'role_title' => $suggested['role_title'] ?? null,
                'organization' => $suggested['organization'] ?? null,
                'power' => (int) ($suggested['power'] ?? 3),
                'interest' => (int) ($suggested['interest'] ?? 3),
                'sort_order' => $index,
            ]);
        }

        foreach ($this->suggestions->suggestRisks($project) as $suggested) {
            Risk::query()->create([
                'project_id' => $project->id,
                'code' => Risk::nextCodeFor($project),
                'category' => $suggested['category'] ?? null,
                'description' => (string) ($suggested['description'] ?? ''),
                'cause' => $suggested['cause'] ?? null,
                'effect' => $suggested['effect'] ?? null,
                'probability' => (int) ($suggested['probability'] ?? 3),
                'impact' => (int) ($suggested['impact'] ?? 3),
                'kind' => $suggested['kind'] ?? Risk::KIND_THREAT,
                'source' => 'catalog',
                'catalog_key' => $suggested['key'] ?? null,
            ]);
        }

        $deliverables = $this->suggestions->suggestDeliverables($project);

        if ($deliverables !== [] && blank($project->charter?->deliverables)) {
            $project->charter?->update([
                'deliverables' => implode("\n", array_map(
                    fn (string $item): string => "- {$item}",
                    $deliverables,
                )),
            ]);
        }
    }
}
