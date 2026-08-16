<?php

declare(strict_types=1);

namespace App\Services\Initiation;

use App\Contracts\Initiation\SuggestsContent;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Stakeholder;

/**
 * Sugerencias hechas de plantillas y reglas. Sin IA (D-016).
 *
 * Un catálogo escrito por alguien que conoce el negocio de Ariel vale más que
 * un riesgo genérico redactado por un modelo que no lo conoce. Y las estrategias
 * de interesados no son opinión: salen del cuadrante, que es aritmética.
 */
final class TemplateSuggestionProvider implements SuggestsContent
{
    /**
     * @return list<array<string, mixed>>
     */
    public function suggestRisks(Project $project): array
    {
        return $this->templateFor($project)?->catalogRisks() ?? [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function suggestStakeholders(Project $project): array
    {
        return $this->templateFor($project)?->catalogStakeholders() ?? [];
    }

    /**
     * @return list<string>
     */
    public function suggestDeliverables(Project $project): array
    {
        return $this->templateFor($project)?->catalogDeliverables() ?? [];
    }

    /**
     * La matriz de Mendelow en una línea. No es una recomendación de la máquina:
     * es lo que la disciplina dice desde hace cuarenta años para cada cuadrante.
     */
    public function suggestEngagementStrategy(Stakeholder $stakeholder): string
    {
        return __("initiation.strategy_{$stakeholder->quadrant()}");
    }

    /**
     * De la plantilla sale un ejemplo del tipo de proyecto, no un borrador de
     * este. Es menos, y es honesto: la plantilla no sabe nada de este proyecto.
     */
    public function suggestNarrative(Project $project, string $field): ?string
    {
        $examples = $this->templateFor($project)?->payload['narratives'] ?? [];

        if (! is_array($examples)) {
            return null;
        }

        $example = $examples[$field] ?? null;

        return is_string($example) && $example !== '' ? $example : null;
    }

    public function isAvailable(Project $project): bool
    {
        $template = $this->templateFor($project);

        if ($template === null) {
            return false;
        }

        return $template->catalogRisks() !== []
            || $template->catalogStakeholders() !== []
            || $template->catalogDeliverables() !== [];
    }

    private function templateFor(Project $project): ?ProjectTemplate
    {
        $project->loadMissing('charter.template');

        return $project->charter?->template;
    }
}
