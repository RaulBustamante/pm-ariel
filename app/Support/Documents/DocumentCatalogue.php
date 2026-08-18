<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\Project;

/**
 * El catálogo de documentos del PMI, y qué tanto existe cada uno hoy.
 *
 * Setenta documentos no se construyen con setenta plantillas: se clasifican por
 * **especie** —se genera solo, se redacta, es un registro que crece, es un
 * acta— y cada especie tiene una sola maquinaria detrás. La lista completa vive
 * en `config/pmi_documents.php`; aquí se resuelve qué se puede abrir.
 *
 * Existe sobre todo para que el hueco sea **visible**. Un sistema que enseña
 * cuatro documentos y calla los otros sesenta y seis se ve completo hasta que
 * alguien pide el que falta a media junta. Este tablero dice de una lo que hay,
 * lo que está registrado y lo que espera a otro dato.
 */
final class DocumentCatalogue
{
    public const STATE_READY = 'ready';

    public const STATE_PARTIAL = 'partial';

    public const STATE_PLANNED = 'planned';

    /** Los cinco grupos de procesos, en el orden del estándar. */
    public const GROUPS = ['initiating', 'planning', 'executing', 'monitoring', 'closing'];

    /**
     * Las rutas de lo que ya se puede abrir.
     *
     * Un documento «listo» sin destino sería un botón que no lleva a ningún
     * lado, así que se enlazan aquí de forma explícita y no por convención de
     * nombres: al agregar el siguiente, esta lista obliga a decir a dónde va.
     *
     * @var array<string, string>
     */
    private const ROUTES = [
        'project_charter' => 'projects.initiation.package',
        'stakeholder_register' => 'projects.initiation.stakeholders',
        'risk_register' => 'projects.initiation.risks',
        'wbs' => 'projects.tasks.index',
        'activity_list' => 'projects.tasks.index',
        'milestone_list' => 'projects.tasks.index',
        'project_schedule' => 'projects.gantt',
        'schedule_baseline' => 'projects.edit',
        'cost_estimates' => 'projects.analysis',
        'earned_value_report' => 'projects.earned-value',
        'cost_forecasts' => 'projects.earned-value',
        'resource_requirements' => 'projects.resources.index',
        'variance_analysis' => 'projects.analysis',
        'work_performance_data' => 'projects.analysis',
        'work_performance_information' => 'projects.analysis',
        'work_performance_reports' => 'projects.reports.weekly',
        'project_status_report' => 'projects.reports.weekly',
        'progress_report' => 'projects.reports.weekly',
    ];

    /**
     * El catálogo agrupado por proceso, listo para pintar.
     *
     * @return array<string, list<array{
     *     code: string, name: string, kind: string, state: string,
     *     source: ?string, url: ?string
     * }>>
     */
    public function forProject(Project $project): array
    {
        $grouped = array_fill_keys(self::GROUPS, []);

        /** @var array<string, array{group: string, kind: string, state: string, source: ?string}> $catalogue */
        $catalogue = config('pmi_documents.catalogue', []);

        foreach ($catalogue as $code => $entry) {
            $grouped[$entry['group']][] = [
                'code' => $code,
                'name' => __("documents.doc_{$code}"),
                'kind' => $entry['kind'],
                'state' => $entry['state'],
                'source' => $entry['source'],
                'url' => $this->url($code, $entry['state'], $project),
            ];
        }

        return $grouped;
    }

    /**
     * Cuántos se pueden emitir hoy, de cuántos.
     *
     * @return array{ready: int, partial: int, planned: int, total: int, percent: int}
     */
    public function coverage(): array
    {
        /** @var array<string, array{state: string}> $catalogue */
        $catalogue = config('pmi_documents.catalogue', []);

        $counts = array_count_values(array_column($catalogue, 'state'));
        $total = max(1, count($catalogue));
        $ready = $counts[self::STATE_READY] ?? 0;

        return [
            'ready' => $ready,
            'partial' => $counts[self::STATE_PARTIAL] ?? 0,
            'planned' => $counts[self::STATE_PLANNED] ?? 0,
            'total' => count($catalogue),
            'percent' => (int) round($ready / $total * 100),
        ];
    }

    private function url(string $code, string $state, Project $project): ?string
    {
        if ($state !== self::STATE_READY) {
            return null;
        }

        // Los que se redactan van todos a la misma pantalla, con su codigo. Por
        // eso no hace falta una entrada por documento en `ROUTES`: veinticinco
        // renglones repetidos que decir lo mismo.
        if ((string) config("pmi_documents.catalogue.{$code}.kind") === 'narrative') {
            return route('projects.documents.narrative', [$project, $code]);
        }

        // Lo mismo con los catorce registros que crecen durante el proyecto:
        // una sola pantalla, y el codigo dice cual.
        if ((string) config("pmi_documents.catalogue.{$code}.kind") === 'log') {
            return route('projects.documents.log', [$project, $code]);
        }

        $route = self::ROUTES[$code] ?? null;

        return $route === null ? null : route($route, $project);
    }
}
