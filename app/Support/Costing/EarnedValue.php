<?php

declare(strict_types=1);

namespace App\Support\Costing;

use App\Models\Baseline;
use App\Models\BaselineTask;
use App\Models\Project;
use App\Models\Task;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * Valor ganado: las tres cifras de las que sale todo lo demás.
 *
 *   VP  valor planeado   cuánto del presupuesto **debería** estar ganado hoy
 *   VG  valor ganado     cuánto se ganó de verdad, según el avance capturado
 *   CR  costo real       cuánto se gastó de verdad, según lo capturado a mano
 *
 * De ahí salen el índice de costo (VG ÷ CR), el de cronograma (VG ÷ VP) y los
 * pronósticos. Es la única forma de contestar «¿vamos caros o vamos tarde?» con
 * un número en vez de con una impresión — y de distinguir las dos cosas, que en
 * un tablero de avance se ven idénticas.
 *
 * **Tres decisiones que hacen que esto no mienta:**
 *
 * 1. El presupuesto sale de la **línea base**, no del plan de hoy. Medir contra
 *    un plan que se ajusta cada semana da varianza cero siempre: es exactamente
 *    lo que la línea base existe para impedir.
 *
 * 2. El costo real es lo que alguien **capturó**, no una deducción del avance.
 *    Suponer que al 40 % de avance va el 40 % del gasto daría un índice de costo
 *    de 1.00 en todos los proyectos, para siempre.
 *
 * 3. Si el costo real está incompleto, **los índices que dependen de él no se
 *    calculan**: se devuelven en `null` y la pantalla dice qué falta. Un CPI de
 *    2.4 porque solo se capturó una tarea de treinta es peor que no tener CPI.
 */
final class EarnedValue
{
    /**
     * Los índices del proyecto a una fecha de corte.
     *
     * @return array{
     *     has_baseline: bool, baseline_name: ?string, status_date: CarbonInterface,
     *     bac: float, pv: float, ev: float, ac: ?float,
     *     cv: ?float, sv: float, cpi: ?float, spi: ?float,
     *     eac: ?float, etc: ?float, vac: ?float, tcpi: ?float,
     *     progress: float, planned_progress: float,
     *     costed_tasks: int, captured_tasks: int, started_tasks: int, missing_actuals: int,
     *     lines: list<array{
     *         name: string, wbs: string, budget: float, pv: float, ev: float,
     *         ac: ?float, cv: ?float, sv: float, percent: float
     *     }>
     * }
     */
    public function for(Project $project, ?CarbonInterface $statusDate = null): array
    {
        $statusDate ??= Carbon::now();

        $baseline = $this->activeBaseline($project);

        /** @var Collection<int, Task> $tasks */
        $tasks = $project->tasks()
            ->with(['assignments.resource'])
            ->where('is_summary', false)
            ->orderBy('sort_order')
            ->get();

        /** @var array<int, BaselineTask> $frozen */
        $frozen = $baseline === null
            ? []
            : $baseline->tasks()->get()->keyBy('task_id')->all();

        $bac = 0.0;
        $pv = 0.0;
        $ev = 0.0;
        $ac = 0.0;

        $captured = 0;
        $started = 0;
        $missing = 0;
        $lines = [];

        foreach ($tasks as $task) {
            $line = $frozen[$task->id] ?? null;

            // El presupuesto de la tarea: lo congelado si estaba en la línea
            // base, y su costo de hoy si nació después. Dejar fuera lo que se
            // agregó luego haría que el valor ganado ignorara justo el trabajo
            // que se metió al plan a media obra.
            $budget = $line !== null
                ? (float) $line->cost
                : TaskCost::of($task)['total'];

            $percent = min(100.0, max(0.0, (float) $task->percent_complete)) / 100;

            $taskPv = $budget * $this->plannedFraction($task, $line, $statusDate);
            $taskEv = $budget * $percent;

            $bac += $budget;
            $pv += $taskPv;
            $ev += $taskEv;

            $taskAc = $task->actual_cost === null ? null : (float) $task->actual_cost;

            if ($taskAc !== null) {
                $ac += $taskAc;
                $captured++;
            }

            // Solo se echa en falta el costo real de lo que **ya arrancó**. Pedir
            // el costo de una tarea que no ha empezado convertiría el aviso en
            // ruido permanente y dejaría de leerse.
            if ($percent > 0) {
                $started++;

                if ($taskAc === null) {
                    $missing++;
                }
            }

            $lines[] = [
                'name' => (string) $task->name,
                'wbs' => (string) ($task->wbs_code ?? ''),
                'budget' => round($budget, 2),
                'pv' => round($taskPv, 2),
                'ev' => round($taskEv, 2),
                'ac' => $taskAc === null ? null : round($taskAc, 2),
                'cv' => $taskAc === null ? null : round($taskEv - $taskAc, 2),
                'sv' => round($taskEv - $taskPv, 2),
                'percent' => round($percent * 100, 1),
            ];
        }

        $bac = round($bac, 2);
        $pv = round($pv, 2);
        $ev = round($ev, 2);

        // El costo real solo cuenta como conocido si **todo lo que ya arrancó**
        // lo tiene capturado. Con la mitad, el índice de costo saldría espléndido
        // por la sencilla razón de que falta la mitad del gasto.
        $knownAc = $started > 0 && $missing === 0 ? round($ac, 2) : null;

        return [
            'has_baseline' => $baseline !== null,
            'baseline_name' => $baseline?->name,
            'status_date' => $statusDate,
            'bac' => $bac,
            'pv' => $pv,
            'ev' => $ev,
            'ac' => $knownAc,
            'cv' => $knownAc === null ? null : round($ev - $knownAc, 2),
            'sv' => round($ev - $pv, 2),
            'cpi' => $this->ratio($ev, $knownAc),
            'spi' => $this->ratio($ev, $pv),
            'eac' => $this->eac($bac, $ev, $knownAc),
            'etc' => $this->etc($bac, $ev, $knownAc),
            'vac' => $this->vac($bac, $ev, $knownAc),
            'tcpi' => $this->tcpi($bac, $ev, $knownAc),
            'progress' => $bac > 0 ? round($ev / $bac * 100, 1) : 0.0,
            'planned_progress' => $bac > 0 ? round($pv / $bac * 100, 1) : 0.0,
            'costed_tasks' => count($lines),
            'captured_tasks' => $captured,
            'started_tasks' => $started,
            'missing_actuals' => $missing,
            'lines' => $lines,
        ];
    }

    /**
     * Qué fracción de la tarea **debería** estar hecha a la fecha de corte.
     *
     * Se reparte el trabajo de forma pareja entre su inicio y su fin, que es lo
     * que hace el método estándar cuando no hay curva de gasto capturada. No es
     * exacto para una tarea que gasta todo el primer día, pero el error se
     * promedia entre decenas de tareas y la alternativa —pedirle a alguien la
     * curva de cada tarea— garantiza que nadie use el indicador.
     *
     * Las fechas salen de la **línea base**. Usar las de hoy movería el valor
     * planeado cada vez que se reprograma, y entonces ir tarde no se notaría
     * nunca: el plan siempre coincidiría consigo mismo.
     */
    private function plannedFraction(Task $task, ?BaselineTask $line, CarbonInterface $statusDate): float
    {
        $start = $line === null ? $task->early_start : $line->start;
        $finish = $line === null ? $task->early_finish : $line->finish;

        if ($start === null || $finish === null) {
            return 0.0;
        }

        if ($statusDate->lessThanOrEqualTo($start)) {
            return 0.0;
        }

        if ($statusDate->greaterThanOrEqualTo($finish)) {
            return 1.0;
        }

        $total = $finish->getTimestamp() - $start->getTimestamp();

        if ($total <= 0) {
            return 1.0;
        }

        return ($statusDate->getTimestamp() - $start->getTimestamp()) / $total;
    }

    /**
     * Pronóstico del costo final, suponiendo que **lo que ha pasado sigue
     * pasando**: `BAC ÷ CPI`.
     *
     * Es la variante que el estándar llama típica, y la que casi siempre acierta
     * más. La otra —«el resto saldrá según lo planeado»— asume que el equipo
     * corregirá el rumbo por su cuenta, que es justo lo que no suele ocurrir.
     */
    private function eac(float $bac, float $ev, ?float $ac): ?float
    {
        if ($ac === null || $ac <= 0 || $ev <= 0) {
            return null;
        }

        // `BAC × CR ÷ VG` es lo mismo que `BAC ÷ CPI`, pero sin pasar por el
        // CPI ya redondeado a tres decimales: con un índice de 0.667 en vez de
        // 0.6666…, un pronóstico de 3,000 sale en 2,998.50 y quien revise la
        // cuenta a mano concluye que el sistema calcula mal.
        return round($bac * $ac / $ev, 2);
    }

    /** Lo que falta por gastar, según el pronóstico. */
    private function etc(float $bac, float $ev, ?float $ac): ?float
    {
        $eac = $this->eac($bac, $ev, $ac);

        return $eac === null || $ac === null ? null : round($eac - $ac, 2);
    }

    /** Cuánto se va a pasar del presupuesto. Negativo es sobrecosto. */
    private function vac(float $bac, float $ev, ?float $ac): ?float
    {
        $eac = $this->eac($bac, $ev, $ac);

        return $eac === null ? null : round($bac - $eac, 2);
    }

    /**
     * A qué eficiencia habría que trabajar de aquí en adelante para todavía
     * terminar dentro del presupuesto.
     *
     * Es el número incómodo: cuando sale en 1.3 quiere decir que hay que hacer
     * el trabajo restante un 30 % más barato de lo estimado, y ahí es donde
     * conviene renegociar en vez de prometer.
     */
    private function tcpi(float $bac, float $ev, ?float $ac): ?float
    {
        if ($ac === null) {
            return null;
        }

        $remainingBudget = $bac - $ac;

        // Si ya se gastó el presupuesto entero, no hay eficiencia que alcance y
        // la división daría un número sin sentido o con el signo cambiado.
        if ($remainingBudget <= 0) {
            return null;
        }

        return round(($bac - $ev) / $remainingBudget, 3);
    }

    private function ratio(float $numerator, ?float $denominator): ?float
    {
        if ($denominator === null || $denominator <= 0) {
            return null;
        }

        return round($numerator / $denominator, 3);
    }

    private function activeBaseline(Project $project): ?Baseline
    {
        return $project->baselines()
            ->where('is_active', true)
            ->latest('captured_at')
            ->first()
            ?? $project->baselines()->latest('captured_at')->first();
    }
}
