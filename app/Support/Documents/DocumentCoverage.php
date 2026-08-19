<?php

declare(strict_types=1);

namespace App\Support\Documents;

use App\Models\DocumentIssue;
use App\Models\Project;
use App\Models\Task;

/**
 * Qué documentos **deberían existir ya** según en qué fase va el proyecto.
 *
 * El tablero de documentos dice qué existe. Esa es media pregunta: los setenta
 * están listos para emitirse desde el primer día, así que un proyecto que apenas
 * arranca ve setenta renglones verdes y no aprende nada. Lo que hace falta saber
 * es **cuáles ya se le debieron haber emitido a este proyecto**, y ninguno de los
 * setenta lo contesta por sí solo.
 *
 * La fase se deduce del avance, no se captura. Un campo de fase es algo que
 * alguien tiene que acordarse de mover, y en cuanto se queda atrás el aviso
 * empieza a mentir en la dirección más peligrosa: diciendo que todo está en
 * orden.
 *
 * **Esto no bloquea nada.** Avisa. Un sistema que impide avanzar sin el
 * documento correcto se resuelve emitiendo documentos vacíos para pasar la
 * puerta, y entonces el expediente queda completo y no dice nada.
 */
final class DocumentCoverage
{
    /**
     * Qué se espera emitido en cada fase. Acumulativo: lo de ejecución incluye
     * lo de inicio y planeación, porque un proyecto en marcha sin acta no deja
     * de necesitarla por haber avanzado.
     *
     * @var array<string, list<string>>
     */
    private const EXPECTED = [
        'initiating' => ['project_charter', 'stakeholder_register'],
        'planning' => ['project_management_plan', 'risk_register'],
        'executing' => ['project_status_report'],
        'monitoring' => ['earned_value_report', 'variance_analysis'],
        'closing' => ['final_project_report', 'lessons_learned_report', 'acceptance_signoff'],
    ];

    /**
     * En qué fase va, y qué le falta.
     *
     * @return array{
     *     phase: string, progress: float, issued: int,
     *     expected: list<array{code: string, group: string, issued: bool}>,
     *     missing: int
     * }
     */
    public function for(Project $project): array
    {
        $phase = $this->phaseOf($project);

        $issued = DocumentIssue::query()
            ->where('project_id', $project->id)
            ->pluck('document_code')
            ->unique()
            ->all();

        $expected = [];
        $missing = 0;

        foreach (self::EXPECTED as $group => $codes) {
            foreach ($codes as $code) {
                $wasIssued = in_array($code, $issued, strict: true);

                $expected[] = ['code' => $code, 'group' => $group, 'issued' => $wasIssued];

                // Solo cuenta como faltante lo de las fases **por las que el
                // proyecto ya pasó**. Reclamar el informe de cierre a un
                // proyecto que arranca convierte el aviso en ruido, y un aviso
                // que siempre está encendido deja de leerse en una semana.
                if (! $wasIssued && $this->reached($phase, $group)) {
                    $missing++;
                }
            }
        }

        return [
            'phase' => $phase,
            'progress' => $this->progressOf($project),
            'issued' => count($issued),
            'expected' => $expected,
            'missing' => $missing,
        ];
    }

    /**
     * La fase, deducida del avance.
     *
     * Los cortes son gruesos a propósito: la pregunta que contestan es «¿este
     * proyecto ya debería tener acta?», no «¿va en el 43 %?».
     */
    public function phaseOf(Project $project): string
    {
        $progress = $this->progressOf($project);

        return match (true) {
            $progress <= 0 => 'initiating',
            $progress < 15 => 'planning',
            $progress < 85 => 'executing',
            $progress < 100 => 'monitoring',
            default => 'closing',
        };
    }

    /** ¿El proyecto ya llegó a esta fase? */
    private function reached(string $phase, string $group): bool
    {
        $order = array_keys(self::EXPECTED);

        return array_search($group, $order, strict: true) <= array_search($phase, $order, strict: true);
    }

    private function progressOf(Project $project): float
    {
        $tasks = Task::query()
            ->where('project_id', $project->id)
            ->where('is_summary', false)
            ->get(['duration_minutes', 'percent_complete']);

        if ($tasks->isEmpty()) {
            return 0.0;
        }

        // Ponderado por duración, igual que en todas partes: si aquí se
        // promediara a secas, la fase saltaría con cerrar tres tareas cortas.
        $total = (float) $tasks->sum('duration_minutes');

        if ($total <= 0) {
            return 0.0;
        }

        $earned = $tasks->sum(
            fn (Task $task): float => (float) $task->duration_minutes * (float) $task->percent_complete / 100,
        );

        return round($earned / $total * 100, 1);
    }
}
