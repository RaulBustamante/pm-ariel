<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Baseline;
use App\Models\Project;
use App\Services\Scheduling\BaselineManager;
use App\Support\Scheduling\WorkingCalendar;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Capturar el compromiso y compararlo contra el plan de hoy.
 *
 * La comparación es la mitad del valor de una línea base y casi nunca se ve:
 * congelarla sin poder consultarla después es coleccionar fotos que nadie mira.
 */
final class BaselineController extends Controller
{
    public function __construct(
        private readonly BaselineManager $baselines,
    ) {}

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $project->tasks()->exists()) {
            return back()->with('warning', __('projects.baseline_needs_tasks'));
        }

        $this->baselines->capture($project, $data['name'], $data['notes'] ?? null);

        return back()->with('status', __('projects.baseline_captured'));
    }

    public function compare(Project $project, Baseline $baseline): View
    {
        $this->authorize('view', $project);

        abort_unless($baseline->project_id === $project->id, 404);

        $calendar = $project->calendars()->where('is_default', true)->first()
            ?? $project->calendars()->first();

        $working = $calendar?->toWorkingCalendar() ?? WorkingCalendar::standard();

        return view('projects.baseline', [
            'project' => $project,
            'baseline' => $baseline,
            'comparison' => $this->baselines->compare($project, $baseline, $working),
            'calendar' => $working,
        ]);
    }
}
