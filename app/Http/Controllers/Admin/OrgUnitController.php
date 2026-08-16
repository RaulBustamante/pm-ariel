<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreOrgUnitRequest;
use App\Http\Requests\Admin\UpdateOrgUnitRequest;
use App\Models\OrgUnit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

final class OrgUnitController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', OrgUnit::class);

        return view('admin.org-units.index', [
            'tree' => $this->tree(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', OrgUnit::class);

        return view('admin.org-units.create', [
            'parents' => $this->tree(),
            'unit' => null,
        ]);
    }

    public function store(StoreOrgUnitRequest $request): RedirectResponse
    {
        OrgUnit::query()->create($request->validated());

        return redirect()
            ->route('admin.org-units.index')
            ->with('status', __('org_units.created'));
    }

    public function edit(OrgUnit $orgUnit): View
    {
        $this->authorize('update', $orgUnit);

        return view('admin.org-units.edit', [
            // Un área no puede colgar de sí misma ni de su descendencia, así que
            // esas opciones ni siquiera se ofrecen. La validación las rechaza de
            // todos modos: la lista es cortesía, no la defensa.
            'parents' => $this->tree()->reject(
                fn (OrgUnit $candidate): bool => $candidate->is($orgUnit) || $orgUnit->isAncestorOf($candidate),
            ),
            'unit' => $orgUnit,
        ]);
    }

    public function update(UpdateOrgUnitRequest $request, OrgUnit $orgUnit): RedirectResponse
    {
        $orgUnit->update($request->validated());

        return redirect()
            ->route('admin.org-units.index')
            ->with('status', __('org_units.updated'));
    }

    /**
     * Borrar un área con gente dentro deja a esa gente sin área y sin rastro de
     * cuál tenía. Borrar una con áreas colgando desconecta el subárbol. En los
     * dos casos el sistema no puede adivinar la intención, así que se niega y
     * dice qué falta hacer antes.
     */
    public function destroy(OrgUnit $orgUnit): RedirectResponse
    {
        $this->authorize('delete', $orgUnit);

        if ($orgUnit->users()->exists()) {
            return back()->with('error', __('org_units.has_users'));
        }

        if ($orgUnit->children()->exists()) {
            return back()->with('error', __('org_units.has_children'));
        }

        $orgUnit->delete();

        return redirect()
            ->route('admin.org-units.index')
            ->with('status', __('org_units.deleted'));
    }

    /**
     * El árbol aplanado en el orden en que se lee, con la profundidad a mano
     * para sangrar sin recursión en la vista.
     *
     * @return Collection<int, OrgUnit>
     */
    private function tree(): Collection
    {
        $units = OrgUnit::query()
            ->withCount('users')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $byParent = $units->groupBy(fn (OrgUnit $unit): int => (int) $unit->parent_id);

        $ordered = new Collection;

        $walk = function (int $parentId) use (&$walk, $byParent, $ordered): void {
            foreach ($byParent->get($parentId, new Collection) as $unit) {
                $ordered->push($unit);
                $walk($unit->id);
            }
        };

        $walk(0);

        // Si un ciclo en los datos dejó áreas fuera del recorrido, se muestran
        // al final en vez de desaparecer del listado sin decir nada.
        return $ordered->concat($units->diff($ordered))->values();
    }
}
