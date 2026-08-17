<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePositionRequest;
use App\Models\Position;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Los puestos de la organización.
 *
 * El modelo existía desde la Etapa 1 y el alta de usuarios ya ofrecía el campo,
 * pero **nunca hubo pantalla para crear uno ni semilla que los cargara**: el
 * desplegable salía vacío y no había forma de llenarlo desde la aplicación. No
 * fallaba nada — un desplegable vacío se ve igual que uno cuyas opciones no
 * aplican —, y por eso pasó cinco etapas sin que nadie lo notara.
 *
 * El `level` ordena de mayor a menor jerarquía y sirve para dos cosas: presentar
 * la lista en un orden que la gente reconozca, y más adelante sugerir a quién
 * escalar algo. No otorga permisos: eso lo hacen los roles, y confundirlos sería
 * dar acceso por título en vez de por responsabilidad.
 */
final class PositionController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', User::class);

        return view('admin.positions.index', [
            'positions' => Position::query()
                ->withCount('users')
                ->orderBy('level')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', User::class);

        return view('admin.positions.create', ['position' => null]);
    }

    public function store(StorePositionRequest $request): RedirectResponse
    {
        Position::query()->create($request->validated());

        return redirect()
            ->route('admin.positions.index')
            ->with('status', __('positions.created'));
    }

    public function edit(Position $position): View
    {
        $this->authorize('create', User::class);

        return view('admin.positions.edit', ['position' => $position]);
    }

    public function update(StorePositionRequest $request, Position $position): RedirectResponse
    {
        $position->update($request->validated());

        return redirect()
            ->route('admin.positions.index')
            ->with('status', __('positions.updated'));
    }

    /**
     * Un puesto con gente asignada no se borra.
     *
     * Borrarlo dejaría a esas personas sin puesto sin avisarle a nadie, y el
     * dato se perdería en silencio. Se pide primero moverlas — que es la
     * decisión que alguien tiene que tomar de todos modos.
     */
    public function destroy(Position $position): RedirectResponse
    {
        $this->authorize('create', User::class);

        $inUse = $position->users()->count();

        if ($inUse > 0) {
            return back()->with('error', __('positions.in_use', ['count' => $inUse]));
        }

        $position->delete();

        return redirect()
            ->route('admin.positions.index')
            ->with('status', __('positions.deleted'));
    }
}
