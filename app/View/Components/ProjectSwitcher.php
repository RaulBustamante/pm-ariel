<?php

declare(strict_types=1);

namespace App\View\Components;

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use App\Support\Visibility\VisibilityScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * El nombre del proyecto en la barra de arriba, convertido en selector.
 *
 * Antes, cambiarse de proyecto costaba tres pasos: salir al listado, buscarlo en
 * la tabla y volver a entrar por el módulo que se estaba viendo. El nombre ya
 * estaba en pantalla; lo único que faltaba era poder abrirlo.
 *
 * Se cambia **al mismo módulo**: del Gantt del 22 al Gantt del 25. Comparar dos
 * proyectos por la misma pantalla es la razón real por la que alguien se cambia,
 * y aterrizar siempre en el tablero obliga a volver a navegar cada vez.
 */
final class ProjectSwitcher extends Component
{
    /**
     * Debajo de este número la lista se lee de un tirón y el buscador solo
     * estorba; encima, sin buscador es un scroll a ciegas.
     */
    private const FILTER_THRESHOLD = 7;

    /**
     * Las pantallas que exigen escritura sobre el proyecto y no traen ningún
     * otro parámetro que las delate.
     *
     * Sin esta lista, un gerente que sea solo lector del proyecto de destino
     * cambiaría desde Ajustes y recibiría un 403: el selector se sentiría roto
     * cuando lo que falla es el permiso. Al resto de las pantallas de escritura
     * las ataja la regla de los parámetros —traen una tarea, un recurso o un
     * renglón, y eso ya manda al tablero.
     */
    private const WRITING_SCREENS = [
        'projects.edit',
        'projects.tasks.import',
    ];

    public function __construct(
        public readonly Project $project,
        private readonly VisibilityScope $visibility,
    ) {}

    public function render(): View
    {
        $projects = $this->reachableProjects();

        return view('components.project-switcher', [
            'projects' => $projects,
            // El buscador filtra en el navegador sobre una lista ya pintada, así
            // que aparece según lo que hay, no según lo que se busque.
            'showFilter' => $projects->count() > self::FILTER_THRESHOLD,
        ]);
    }

    /**
     * Los proyectos que este usuario puede abrir, con la dirección a la que lo
     * lleva cada uno.
     *
     * @return Collection<int, array{code: string, name: string, url: string, current: bool}>
     */
    private function reachableProjects(): Collection
    {
        /** @var User $viewer */
        $viewer = auth()->user();

        [$route, $parameters] = $this->currentScreen();

        // Solo las tres columnas que se pintan. Esta consulta corre en **cada**
        // pantalla de proyecto, y traer el modelo completo de un portafolio
        // entero para escribir clave y nombre se paga en todas ellas.
        $query = Project::query()->select(['id', 'code', 'name']);

        if (! $viewer->hasRole(Role::ADMIN) && ! $viewer->hasRole(Role::AUDITOR)) {
            $this->visibility->scopeProjects($query, $viewer);
        }

        // La membresía se trae solo cuando hay que preguntarla, y solo la de
        // quien está mirando: `update` se decide con el rol de proyecto de este
        // usuario y nada más. Sin esto, preguntar proyecto por proyecto sería
        // una consulta por renglón —y con la carga perezosa apagada, un 500.
        if (in_array($route, self::WRITING_SCREENS, strict: true)) {
            $query->with(['members' => fn (Relation $members) => $members->whereKey($viewer->id)]);
        }

        $projects = $query->orderBy('name')->get();

        // El proyecto abierto siempre aparece, aunque la regla de visibilidad lo
        // dejara fuera: la lista es también el rótulo de dónde se está parado, y
        // una lista que no incluye lo que se está viendo se lee como un error.
        if (! $projects->contains(fn (Project $item): bool => $item->is($this->project))) {
            $projects->prepend($this->project);
        }

        return $projects->map(fn (Project $item): array => [
            'code' => (string) $item->code,
            'name' => (string) $item->name,
            'url' => $this->urlFor($item, $route, $parameters, $viewer),
            'current' => $item->is($this->project),
        ])->values();
    }

    /**
     * La pantalla a la que hay que llegar en el proyecto de destino.
     *
     * @return array{0: string, 1: array<string, mixed>}
     */
    private function currentScreen(): array
    {
        $route = request()->route();
        $name = $route?->getName();

        if ($route === null || $name === null || ! str_starts_with($name, 'projects.')) {
            return ['projects.dashboard', []];
        }

        $parameters = [];

        foreach ($route->parameters() as $key => $value) {
            if ($key === 'project') {
                continue;
            }

            // Un parámetro que cuelga del proyecto —una tarea, un recurso, un
            // renglón de un registro— no existe en el proyecto de destino.
            // Arrastrarlo daría un 404, así que la pantalla se cae al tablero.
            //
            // Los que no son modelos sí viajan: el código de un documento
            // («weekly», «risk-log») nombra un tipo del catálogo, no una fila de
            // este proyecto, y por eso el mismo documento se abre en el otro.
            if ($value instanceof Model) {
                return ['projects.dashboard', []];
            }

            $parameters[$key] = $value;
        }

        return [$name, $parameters];
    }

    /**
     * @param  array<string, mixed>  $parameters
     */
    private function urlFor(Project $target, string $route, array $parameters, User $viewer): string
    {
        if (in_array($route, self::WRITING_SCREENS, strict: true)) {
            // El proyecto abierto entra a la lista por su cuenta y puede no
            // traer la membresía que cargó la consulta. `loadMissing` es la
            // carga explícita que la regla de carga perezosa sí permite, y no
            // hace nada cuando ya venía cargada.
            $target->loadMissing('members');

            if (! $viewer->can('update', $target)) {
                return route('projects.dashboard', $target);
            }
        }

        return route($route, ['project' => $target, ...$parameters]);
    }
}
