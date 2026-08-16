<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Contracts\Identity\ProvisionsUsers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\OrgUnit;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class UserController extends Controller
{
    public function index(VisibilityScope $visibility): View
    {
        $this->authorize('viewAny', User::class);

        /** @var User $viewer */
        $viewer = auth()->user();

        $query = User::query()->with(['orgUnit', 'position', 'roles']);

        // Un administrador ve a todos; el resto, solo su cadena hacia abajo.
        if (! $viewer->hasRole(Role::ADMIN) && ! $viewer->hasRole(Role::AUDITOR)) {
            $query->whereIn('id', $visibility->visibleUserIds($viewer));
        }

        return view('admin.users.index', [
            'users' => $query->orderBy('name')->paginate(25),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.users.create', $this->formOptions());
    }

    public function store(StoreUserRequest $request, ProvisionsUsers $provisioner): RedirectResponse
    {
        ['user' => $user, 'temporaryPassword' => $password] = $provisioner->provision($request->validated());

        if ($request->filled('roles')) {
            $user->roles()->sync($request->input('roles'));
        }

        // Se muestra una sola vez. No se guarda en ningún lado ni se envía por
        // correo mientras no exista la cuenta dedicada (supuesto S-02).
        return redirect()
            ->route('admin.users.index')
            ->with('status', __('users.created_with_password', [
                'email' => $user->email,
                'password' => $password,
            ]));
    }

    public function edit(User $user): View
    {
        $this->authorize('update', $user);

        return view('admin.users.edit', [...$this->formOptions(), 'user' => $user->load('roles')]);
    }

    public function update(UpdateUserRequest $request, User $user, VisibilityScope $visibility): RedirectResponse
    {
        $user->update($request->validated());

        if ($request->user()->can('assignRoles', User::class)) {
            $user->roles()->sync($request->input('roles', []));
        }

        // Un cambio de área o de rol puede alterar qué ve la gente: el caché de
        // visibilidad deja de ser válido para toda la rama, no solo para este.
        $visibility->flush();

        return redirect()->route('admin.users.index')->with('status', __('users.updated'));
    }

    /**
     * @return array{orgUnits: \Illuminate\Support\Collection<int, OrgUnit>, positions: \Illuminate\Support\Collection<int, Position>, roles: \Illuminate\Support\Collection<int, Role>}
     */
    private function formOptions(): array
    {
        return [
            'orgUnits' => OrgUnit::query()->orderBy('name')->get(),
            'positions' => Position::query()->orderBy('level')->get(),
            'roles' => Role::query()->orderBy('name')->get(),
        ];
    }
}
