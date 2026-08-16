<?php

declare(strict_types=1);

namespace App\Http\Controllers\Initiation;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Support\Initiation\InitiationHealth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * El paquete de inicio en una sola hoja, lista para imprimir o guardar como PDF.
 *
 * Es HTML con hoja de estilo de impresión, no un PDF generado en el servidor. La
 * decisión del motor de PDF es del bloque 5.1 y arrastra el Gantt paginado, que
 * es lo difícil; adelantarla aquí por un documento de texto sería elegir por una
 * razón que no es la que importa. Esto se ve bien impreso hoy y no compromete
 * esa decisión.
 */
final class InitiationPackageController extends Controller
{
    public function show(Project $project, InitiationHealth $health): View
    {
        $this->authorize('view', $project);

        $project->loadMissing([
            'charter.sponsor', 'charter.template', 'charter.approver',
            'stakeholders', 'risks.responses', 'risks.owner', 'owner', 'orgUnit',
        ]);

        return view('initiation.package', [
            'project' => $project,
            'charter' => $project->charter,
            'findings' => $health->findings($project),
            'light' => $health->light($project),
        ]);
    }

    /**
     * Aprobar es distinto de guardar: fija una fecha y un nombre. Se niega
     * mientras falte algo obligatorio, porque un acta aprobada a medias es peor
     * que ninguna — le da respaldo formal a un documento que no lo sostiene.
     */
    public function approve(Project $project, InitiationHealth $health): RedirectResponse
    {
        $charter = $project->charter;

        abort_if($charter === null, 404);

        $this->authorize('approve', $charter);

        if (! $health->isComplete($project)) {
            return back()->with('warning', __('initiation.cannot_approve_incomplete'));
        }

        $charter->forceFill([
            'approved_at' => now(),
            'approved_by' => Auth::id(),
        ])->save();

        return back()->with('status', __('initiation.approved'));
    }
}
