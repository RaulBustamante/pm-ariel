<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Role;
use Database\Seeders\DemoProjectSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * El recorrido de bienvenida y el proyecto de ejemplo.
 *
 * **El ejemplo se borra de un clic.** Un demo que no se puede quitar acaba
 * conviviendo con los proyectos de verdad, y el día que alguien lo confunde en
 * un reporte, la culpa es de quien no puso el botón.
 */
final class OnboardingController extends Controller
{
    public function show(): View
    {
        $demo = Project::query()->where('code', 'DEMO-01')->first();

        return view('onboarding.tour', [
            'demo' => $demo,
            'canCreate' => Auth::user()?->can('create', Project::class) ?? false,
        ]);
    }

    /**
     * Carga el proyecto de ejemplo. Solo un administrador: es un proyecto que
     * aparece para todos, y que cualquiera pueda sembrarlo llenaría el listado.
     */
    public function loadDemo(): RedirectResponse
    {
        $this->authorize('create', Project::class);

        abort_unless(Auth::user()?->hasRole(Role::ADMIN) ?? false, 403);

        Artisan::call('db:seed', ['--class' => DemoProjectSeeder::class, '--force' => true]);

        $demo = Project::query()->where('code', 'DEMO-01')->firstOrFail();

        return redirect()
            ->route('projects.dashboard', $demo)
            ->with('status', __('onboarding.demo_loaded'));
    }

    public function removeDemo(): RedirectResponse
    {
        $this->authorize('create', Project::class);

        abort_unless(Auth::user()?->hasRole(Role::ADMIN) ?? false, 403);

        // Definitivo y no en suave: el ejemplo no tiene historia que valga la
        // pena conservar, y dejarlo borrado en suave lo mantendría en los
        // reportes que consultan con `withTrashed`.
        Project::withTrashed()->where('code', 'DEMO-01')->forceDelete();

        return redirect()
            ->route('onboarding')
            ->with('status', __('onboarding.demo_removed'));
    }
}
