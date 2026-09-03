<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * El mapa: todos los proyectos y quién tiene cuáles.
 *
 * Existe porque «¿qué trae cada quién?» no se podía contestar en ninguna
 * pantalla. El inicio y el listado de proyectos contestan **«¿qué traigo yo?»**
 * —cada uno filtrado a lo del que mira— y la única forma de ver el trabajo
 * ajeno era abrir proyecto por proyecto y leer su equipo.
 *
 * El atajo que se usaba en su lugar era meter al administrador como miembro de
 * todo. Funciona, y cobra caro: su inicio se llena de proyectos que no trabaja,
 * «mi semana» cuenta tareas que no son suyas, y no queda registro de quién sí
 * está a cargo. Esta pantalla es el lugar donde ver todo **sin** tener que
 * pertenecer a todo.
 *
 * Deliberadamente no se toca el inicio: ver todo y trabajar en algo son dos
 * preguntas distintas, y responderlas en la misma pantalla es lo que confundía.
 */
final class ProjectOverviewController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('viewAll', Project::class);

        // El equipo se trae con la relación, no consultando por renglón: son
        // siete proyectos hoy y podrían ser doscientos, y la tabla dibuja los
        // miembros de cada uno.
        $query = Project::query()->with(['owner', 'members']);

        // «¿Qué trae Alfredo?» es la pregunta concreta que se le hace a esta
        // pantalla. Cuenta como dueño **o** como miembro: alguien puede cargar
        // un proyecto sin aparecer en su propia lista de miembros.
        $filtered = $this->userFilter($request);

        if ($filtered !== null) {
            $query->where(function ($inner) use ($filtered): void {
                $inner->where('owner_id', $filtered->id)
                    ->orWhereHas('members', fn ($members) => $members->whereKey($filtered->id));
            });
        }

        $projects = $query->orderByDesc('id')->paginate(50)->withQueryString();

        // Quién no tiene nada. Un usuario activo con cero proyectos casi siempre
        // es un alta a medias —se creó la cuenta y nadie lo asignó—, y es
        // justamente lo que no se ve en ninguna otra parte.
        $idle = User::query()
            ->where('is_active', true)
            ->whereDoesntHave('projectMemberships')
            // Ni miembro ni dueño. Sin la segunda mitad, quien carga un
            // proyecto sin estar en su lista de miembros saldría aquí como si
            // no tuviera nada.
            ->whereNotExists(fn ($sub) => $sub->selectRaw('1')
                ->from('projects')
                ->whereColumn('projects.owner_id', 'users.id'))
            ->orderBy('name')
            ->get();

        return view('admin.projects.index', [
            'projects' => $projects,
            'candidates' => User::query()->orderBy('name')->get(['id', 'name']),
            'filtered' => $filtered,
            'idle' => $idle,
            'totalProjects' => Project::count(),
        ]);
    }

    /**
     * El usuario por el que se filtra, si viene y existe.
     *
     * Un número que no corresponde a nadie se ignora en vez de responder 404:
     * la pantalla existe igual, y el único efecto es que se vea la lista
     * completa. Es lo mismo que hace la lista de tareas con `?parent=`.
     */
    private function userFilter(Request $request): ?User
    {
        $id = $request->integer('user');

        return $id > 0 ? User::query()->find($id) : null;
    }
}
