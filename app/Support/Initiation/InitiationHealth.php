<?php

declare(strict_types=1);

namespace App\Support\Initiation;

use App\Models\Project;
use App\Models\Risk;
use App\Models\Stakeholder;

/**
 * Qué le falta al inicio de este proyecto para estar sano, y por qué importa.
 *
 * Es el único lugar donde vive esa opinión. Las pantallas la muestran, el PDF la
 * imprime y el semáforo la resume, pero ninguno la vuelve a calcular por su
 * cuenta: si estuviera repetida, tres partes de la aplicación empezarían a
 * discrepar sobre si el proyecto está listo.
 *
 * Las reglas son deliberadamente pocas y explicables. Ninguna adivina.
 */
final class InitiationHealth
{
    /** Debajo de esto, nadie miró de verdad los riesgos. */
    private const MINIMUM_RISKS = 3;

    /**
     * @return list<Finding>
     */
    public function findings(Project $project): array
    {
        $project->loadMissing(['charter', 'stakeholders', 'risks.responses']);

        return [
            ...$this->justificationFindings($project),
            ...$this->stakeholderFindings($project),
            ...$this->charterFindings($project),
            ...$this->riskFindings($project),
        ];
    }

    /**
     * @return list<Finding>
     */
    public function findingsFor(Project $project, InitiationStep $step): array
    {
        return array_values(array_filter(
            $this->findings($project),
            fn (Finding $finding): bool => $finding->step === $step,
        ));
    }

    /**
     * verde: nada pendiente · ámbar: solo advertencias · rojo: falta algo que el
     * documento necesita para sostenerse.
     */
    public function light(Project $project): string
    {
        $findings = $this->findings($project);

        if ($findings === []) {
            return 'green';
        }

        foreach ($findings as $finding) {
            if ($finding->isBlocking()) {
                return 'red';
            }
        }

        return 'amber';
    }

    /**
     * Porcentaje de avance del recorrido. Cuenta pasos sin nada bloqueante, no
     * campos llenos: un acta con las diez casillas a medias no está al 100 %.
     */
    public function completion(Project $project): int
    {
        $steps = InitiationStep::ordered();
        $blocked = [];

        foreach ($this->findings($project) as $finding) {
            if ($finding->isBlocking()) {
                $blocked[$finding->step->value] = true;
            }
        }

        $done = count($steps) - count($blocked);

        return (int) round($done / count($steps) * 100);
    }

    public function isComplete(Project $project): bool
    {
        foreach ($this->findings($project) as $finding) {
            if ($finding->isBlocking()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<Finding>
     */
    private function justificationFindings(Project $project): array
    {
        $step = InitiationStep::Justification;
        $charter = $project->charter;
        $findings = [];

        if ($charter === null || blank($charter->problem_statement)) {
            $findings[] = Finding::blocking(
                $step,
                __('initiation.finding_no_problem'),
                __('initiation.finding_no_problem_why'),
            );
        }

        if ($charter === null || blank($charter->expected_benefit)) {
            $findings[] = Finding::blocking(
                $step,
                __('initiation.finding_no_benefit'),
                __('initiation.finding_no_benefit_why'),
            );
        }

        if ($charter !== null && blank($charter->alignment)) {
            $findings[] = Finding::warning(
                $step,
                __('initiation.finding_no_alignment'),
                __('initiation.finding_no_alignment_why'),
            );
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function stakeholderFindings(Project $project): array
    {
        $step = InitiationStep::Stakeholders;
        $stakeholders = $project->stakeholders;
        $findings = [];

        if ($stakeholders->isEmpty()) {
            $findings[] = Finding::blocking(
                $step,
                __('initiation.finding_no_stakeholders'),
                __('initiation.finding_no_stakeholders_why'),
            );

            return $findings;
        }

        $critical = $stakeholders->filter(
            fn (Stakeholder $s): bool => $s->quadrant() === Stakeholder::QUADRANT_MANAGE_CLOSELY,
        );

        if ($critical->isEmpty()) {
            $findings[] = Finding::warning(
                $step,
                __('initiation.finding_no_key_stakeholder'),
                __('initiation.finding_no_key_stakeholder_why'),
            );
        }

        $withoutStrategy = $critical->filter(
            fn (Stakeholder $s): bool => blank($s->engagement_strategy),
        );

        if ($withoutStrategy->isNotEmpty()) {
            $findings[] = Finding::warning(
                $step,
                __('initiation.finding_no_strategy', [
                    'names' => $withoutStrategy->pluck('name')->implode(', '),
                ]),
                __('initiation.finding_no_strategy_why'),
            );
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function charterFindings(Project $project): array
    {
        $step = InitiationStep::Charter;
        $charter = $project->charter;
        $findings = [];

        foreach (['objectives', 'deliverables', 'success_criteria'] as $field) {
            if ($charter === null || blank($charter->{$field})) {
                $findings[] = Finding::blocking(
                    $step,
                    __("initiation.finding_no_{$field}"),
                    __("initiation.finding_no_{$field}_why"),
                );
            }
        }

        if ($charter !== null && $charter->sponsor_id === null) {
            $findings[] = Finding::warning(
                $step,
                __('initiation.finding_no_sponsor'),
                __('initiation.finding_no_sponsor_why'),
            );
        }

        return $findings;
    }

    /**
     * @return list<Finding>
     */
    private function riskFindings(Project $project): array
    {
        $step = InitiationStep::Risks;
        $risks = $project->risks;
        $findings = [];

        if ($risks->count() < self::MINIMUM_RISKS) {
            $findings[] = Finding::blocking(
                $step,
                __('initiation.finding_few_risks', ['count' => $risks->count(), 'minimum' => self::MINIMUM_RISKS]),
                __('initiation.finding_few_risks_why'),
            );
        }

        $unanswered = $risks->filter(fn (Risk $risk): bool => $risk->needsResponse());

        if ($unanswered->isNotEmpty()) {
            $findings[] = Finding::blocking(
                $step,
                __('initiation.finding_risk_without_response', [
                    'codes' => $unanswered->pluck('code')->implode(', '),
                ]),
                __('initiation.finding_risk_without_response_why'),
            );
        }

        $ownerless = $risks->filter(
            fn (Risk $risk): bool => $risk->owner_id === null && $risk->level() !== Risk::LEVEL_LOW,
        );

        if ($ownerless->isNotEmpty()) {
            $findings[] = Finding::warning(
                $step,
                __('initiation.finding_risk_without_owner', [
                    'codes' => $ownerless->pluck('code')->implode(', '),
                ]),
                __('initiation.finding_risk_without_owner_why'),
            );
        }

        return $findings;
    }
}
