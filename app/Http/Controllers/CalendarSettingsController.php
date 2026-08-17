<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Calendar;
use App\Models\Project;
use App\Services\Scheduling\ProjectScheduler;
use App\Support\Scheduling\ScheduleResult;
use App\Support\Scheduling\WorkShift;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Los calendarios del proyecto: jornada semanal y días que no se trabajan.
 *
 * **Antes de aplicar un cambio se dice qué se va a mover.** Marcar el 16 de
 * septiembre como feriado puede recorrer la entrega dos semanas, y enterarse
 * después de guardar es la peor forma de descubrirlo. El aviso de impacto no es
 * cortesía: es lo que permite decir «mejor no» a tiempo.
 */
final class CalendarSettingsController extends Controller
{
    /** Nombres de los días, en orden ISO. */
    private const WEEKDAYS = [1, 2, 3, 4, 5, 6, 7];

    public function __construct(
        private readonly ProjectScheduler $scheduler,
    ) {}

    public function index(Project $project): View
    {
        $this->authorize('view', $project);

        return view('calendars.index', [
            'project' => $project,
            'calendars' => $project->calendars()->get(),
            'weekdays' => self::WEEKDAYS,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('update', $project);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'key' => [
                'required', 'string', 'max:50', 'regex:/^[a-z0-9_-]+$/',
                Rule::unique('calendars', 'key')->where('project_id', $project->id)->withoutTrashed(),
            ],
            'timezone' => ['required', 'timezone'],
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:1,7'],
            'start' => ['required', 'date_format:H:i'],
            'end' => ['required', 'date_format:H:i', 'after:start'],
        ]);

        Calendar::query()->create([
            'project_id' => $project->id,
            'name' => $data['name'],
            'key' => $data['key'],
            'timezone' => $data['timezone'],
            'week' => array_fill_keys(
                array_map(intval(...), $data['days']),
                [['start' => $data['start'], 'end' => $data['end']]],
            ),
            'is_default' => ! $project->calendars()->exists(),
        ]);

        return back()->with('status', __('calendars.created'));
    }

    /**
     * Muestra qué pasaría con el cambio, sin aplicarlo.
     */
    public function preview(Request $request, Project $project, Calendar $calendar): View
    {
        $this->authorize('update', $project);
        abort_unless($calendar->project_id === $project->id, 404);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'action' => ['required', Rule::in(['holiday', 'workday'])],
        ]);

        $before = $project->tasks()->max('early_finish');

        // Se calcula sobre una copia en memoria: el calendario guardado no se
        // toca hasta que el usuario confirme.
        $candidate = $data['action'] === 'holiday'
            ? $calendar->toWorkingCalendar()->withHoliday($data['date'])
            : $calendar->toWorkingCalendar()->withException($data['date'], [WorkShift::fromTimes('09:00', '18:00')]);

        $simulated = $this->scheduler->simulate($project, $candidate);

        return view('calendars.preview', [
            'project' => $project,
            'calendar' => $calendar,
            'date' => new DateTimeImmutable($data['date']),
            'action' => $data['action'],
            'before' => $before === null ? null : new DateTimeImmutable((string) $before),
            'after' => $simulated?->projectFinish,
            'movedTasks' => $simulated === null ? 0 : $this->countMoved($project, $simulated),
        ]);
    }

    public function applyException(Request $request, Project $project, Calendar $calendar): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($calendar->project_id === $project->id, 404);

        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'action' => ['required', Rule::in(['holiday', 'workday', 'remove'])],
        ]);

        $exceptions = $calendar->exceptions ?? [];

        $exceptions = match ($data['action']) {
            'holiday' => [...$exceptions, $data['date'] => []],
            'workday' => [...$exceptions, $data['date'] => [['start' => '09:00', 'end' => '18:00']]],
            default => array_diff_key($exceptions, [$data['date'] => null]),
        };

        $calendar->update(['exceptions' => $exceptions]);

        $this->scheduler->reschedule($project->refresh());

        return redirect()
            ->route('projects.calendars.index', $project)
            ->with('status', __('calendars.applied_and_rescheduled'));
    }

    public function makeDefault(Project $project, Calendar $calendar): RedirectResponse
    {
        $this->authorize('update', $project);
        abort_unless($calendar->project_id === $project->id, 404);

        $project->calendars()->update(['is_default' => false]);
        $calendar->update(['is_default' => true]);

        $this->scheduler->reschedule($project->refresh());

        return back()->with('status', __('calendars.default_changed'));
    }

    private function countMoved(Project $project, ScheduleResult $simulated): int
    {
        $moved = 0;

        foreach ($project->tasks()->get() as $task) {
            $key = (string) $task->id;

            // Una tarea recién agregada puede no estar en el resultado simulado;
            // no contarla es preferible a romper la pantalla del aviso.
            if (! array_key_exists($key, $simulated->tasks)) {
                continue;
            }

            $now = $task->early_finish?->format('Y-m-d H:i');
            $then = $simulated->tasks[$key]->earlyFinish->format('Y-m-d H:i');

            if ($now !== null && $now !== $then) {
                $moved++;
            }
        }

        return $moved;
    }
}
