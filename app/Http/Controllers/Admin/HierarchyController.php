<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AssignManagerRequest;
use App\Models\User;
use App\Support\Hierarchy\HierarchyManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * Quién reporta a quién. Es la pantalla que hace visible la regla de
 * visibilidad: un jefe ve todo lo de su cadena hacia abajo.
 */
final class HierarchyController extends Controller
{
    public function index(HierarchyManager $hierarchy): View
    {
        $this->authorize('viewAny', User::class);

        $users = User::query()->with('orgUnit')->orderBy('name')->get();

        // Una consulta, no una por usuario: el listado completo cabe de sobra en
        // memoria y así la pantalla no crece en consultas con la plantilla.
        $managers = $this->currentManagers();

        return view('admin.hierarchy.index', [
            'users' => $users,
            'managers' => $managers,
            'roots' => $users->reject(fn (User $user): bool => $managers->has($user->id)),
        ]);
    }

    public function edit(User $user, HierarchyManager $hierarchy): View
    {
        $this->authorize('update', $user);

        return view('admin.hierarchy.edit', [
            'user' => $user,
            'manager' => $hierarchy->managerOf($user),
            // Ofrecer a alguien que cerraría un ciclo solo sirve para que el
            // formulario lo rechace después. Se filtran antes.
            'candidates' => User::query()
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get()
                ->reject(fn (User $candidate): bool => $hierarchy->wouldCreateCycle($user, $candidate)),
        ]);
    }

    public function update(AssignManagerRequest $request, User $user, HierarchyManager $hierarchy): RedirectResponse
    {
        $managerId = $request->validated('manager_id');

        $manager = $managerId === null ? null : User::query()->find($managerId);

        if (! $hierarchy->assign($user, $manager)) {
            return back()
                ->withInput()
                ->withErrors(['manager_id' => __('hierarchy.would_create_cycle')]);
        }

        return redirect()
            ->route('admin.hierarchy.index')
            ->with('status', __('hierarchy.updated', ['name' => $user->name]));
    }

    /**
     * Jefe vigente de cada usuario, indexado por subordinado.
     *
     * @return Collection<int, User>
     */
    private function currentManagers(): Collection
    {
        /** @var Collection<int, User> $managers */
        $managers = User::query()
            ->join('user_hierarchy', 'users.id', '=', 'user_hierarchy.manager_id')
            ->whereNull('user_hierarchy.effective_to')
            ->whereNull('user_hierarchy.deleted_at')
            ->select(['users.*', 'user_hierarchy.subordinate_id'])
            ->get()
            ->keyBy('subordinate_id');

        return $managers;
    }
}
