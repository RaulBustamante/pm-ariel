<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Costing\ProjectCosts;
use Illuminate\View\View;

/**
 * Los tres cortes que contestan «¿cómo vamos de recursos y de dinero?».
 *
 * Van juntos en una pantalla y no en tres pestañas por dos razones. La primera
 * es que salen de la misma consulta: partirlos costaría recorrer el proyecto
 * tres veces para presentar los mismos números de tres formas. La segunda es que
 * **se leen en cadena**: la carga enseña quién está por encima de su capacidad,
 * la distribución de horas enseña en qué se le va el tiempo, y el costo enseña
 * cuánto cuesta eso. Separarlos obliga a llevarse un dato de una pestaña a otra
 * en la cabeza.
 *
 * La barra de pestañas ya tiene nueve entradas. Agregar tres más por tres cortes
 * del mismo dato empeoraría la navegación para no ganar nada.
 */
final class AnalysisController extends Controller
{
    public function show(Project $project, ProjectCosts $costs): View
    {
        $this->authorize('view', $project);

        $baseline = $project->baselines()->oldest('captured_at')->first();

        return view('analysis.show', [
            'project' => $project,
            'costs' => $costs->for($project),
            'workload' => $costs->workload($project),
            // Se compara contra la **primera** línea base, que es el compromiso
            // original. Comparar contra la más reciente esconde justamente la
            // desviación acumulada, que es lo que se quiere ver.
            'baseline' => $baseline,
        ]);
    }
}
