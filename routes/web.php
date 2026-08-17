<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\HierarchyController;
use App\Http\Controllers\Admin\OrgUnitController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\CalendarViewController;
use App\Http\Controllers\GanttController;
use App\Http\Controllers\Initiation\InitiationController;
use App\Http\Controllers\Initiation\InitiationPackageController;
use App\Http\Controllers\Initiation\RiskController;
use App\Http\Controllers\Initiation\StakeholderController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\TaskController;
use App\Support\Initiation\InitiationStep;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');

Route::middleware('guest')->group(function (): void {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])
        ->middleware('throttle:6,1')
        ->name('password.email');

    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'store'])->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('logout', [LoginController::class, 'destroy'])->name('logout');

    // Alcanzables con contraseña temporal vigente: son la salida de ese estado.
    Route::get('change-password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('change-password', [PasswordChangeController::class, 'update'])->name('password.change.update');

    Route::view('dashboard', 'dashboard')->name('dashboard');

    Route::get('preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::put('preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::prefix('projects/{project}')->group(function (): void {
        Route::get('initiation', [InitiationController::class, 'overview'])
            ->name('projects.initiation.overview');
        Route::get('initiation/package', [InitiationPackageController::class, 'show'])
            ->name('projects.initiation.package');
        Route::post('initiation/approve', [InitiationPackageController::class, 'approve'])
            ->name('projects.initiation.approve');

        // Un paso por nombre y no por número: si algún día se inserta uno, los
        // enlaces que la gente guardó siguen llevando a donde decían. Los pasos
        // salen del enum, así que agregar uno no obliga a tocar este archivo.
        foreach (InitiationStep::ordered() as $initiationStep) {
            Route::get("initiation/{$initiationStep->value}", [InitiationController::class, 'step'])
                ->defaults('step', $initiationStep->value)
                ->name($initiationStep->route());

            Route::put("initiation/{$initiationStep->value}", [InitiationController::class, 'update'])
                ->defaults('step', $initiationStep->value)
                ->name($initiationStep->route().'.update');

            Route::post("initiation/{$initiationStep->value}/suggest/{field}", [InitiationController::class, 'suggest'])
                ->defaults('step', $initiationStep->value)
                ->name($initiationStep->route().'.suggest');
        }

        Route::post('stakeholders', [StakeholderController::class, 'store'])->name('projects.stakeholders.store');
        Route::post('stakeholders/suggest', [StakeholderController::class, 'suggest'])->name('projects.stakeholders.suggest');
        Route::put('stakeholders/{stakeholder}', [StakeholderController::class, 'update'])->name('projects.stakeholders.update');
        Route::delete('stakeholders/{stakeholder}', [StakeholderController::class, 'destroy'])->name('projects.stakeholders.destroy');

        Route::post('risks', [RiskController::class, 'store'])->name('projects.risks.store');
        Route::post('risks/suggest', [RiskController::class, 'suggest'])->name('projects.risks.suggest');
        Route::put('risks/{risk}', [RiskController::class, 'update'])->name('projects.risks.update');
        Route::delete('risks/{risk}', [RiskController::class, 'destroy'])->name('projects.risks.destroy');
        Route::post('risks/{risk}/responses', [RiskController::class, 'storeResponse'])->name('projects.risks.responses.store');
        Route::delete('risks/{risk}/responses/{response}', [RiskController::class, 'destroyResponse'])->name('projects.risks.responses.destroy');

        // --- El plan de trabajo -------------------------------------------
        Route::get('tasks', [TaskController::class, 'index'])->name('projects.tasks.index');
        Route::post('tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
        // Antes de `tasks/{task}` no hace falta: «recalculate» no es un número y
        // el enlace de modelo no lo confundiría, pero el orden explícito ahorra
        // el susto a quien lea esto en un año.
        Route::get('tasks/{task}', [TaskController::class, 'show'])
            ->whereNumber('task')
            ->name('projects.tasks.show');
        Route::put('tasks/{task}', [TaskController::class, 'update'])->name('projects.tasks.update');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('projects.tasks.destroy');
        Route::post('tasks/{task}/outline/{action}', [TaskController::class, 'outline'])
            ->whereIn('action', ['indent', 'outdent', 'up', 'down'])
            ->name('projects.tasks.outline');
        Route::post('tasks/recalculate', [TaskController::class, 'recalculate'])->name('projects.tasks.recalculate');

        Route::get('gantt', [GanttController::class, 'show'])->name('projects.gantt');

        Route::get('calendar', [CalendarViewController::class, 'show'])->name('projects.calendar');

        Route::get('kanban', [KanbanController::class, 'show'])->name('projects.kanban');
        Route::post('kanban/{task}/move', [KanbanController::class, 'move'])->name('projects.kanban.move');

        Route::get('advisor', [AdvisorController::class, 'show'])->name('projects.advisor');
        Route::post('advisor', [AdvisorController::class, 'analyze'])->name('projects.advisor.analyze');

        Route::post('resources', [ResourceController::class, 'store'])->name('projects.resources.store');
        Route::delete('resources/{resource}', [ResourceController::class, 'destroy'])->name('projects.resources.destroy');
        Route::post('tasks/{task}/assignments', [ResourceController::class, 'assign'])->name('projects.assignments.store');
        Route::delete('tasks/{task}/assignments/{resource}', [ResourceController::class, 'unassign'])->name('projects.assignments.destroy');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('org-units', [OrgUnitController::class, 'index'])->name('org-units.index');
        Route::get('org-units/create', [OrgUnitController::class, 'create'])->name('org-units.create');
        Route::post('org-units', [OrgUnitController::class, 'store'])->name('org-units.store');
        Route::get('org-units/{orgUnit}/edit', [OrgUnitController::class, 'edit'])->name('org-units.edit');
        Route::put('org-units/{orgUnit}', [OrgUnitController::class, 'update'])->name('org-units.update');
        Route::delete('org-units/{orgUnit}', [OrgUnitController::class, 'destroy'])->name('org-units.destroy');

        Route::get('hierarchy', [HierarchyController::class, 'index'])->name('hierarchy.index');
        Route::get('hierarchy/{user}', [HierarchyController::class, 'edit'])->name('hierarchy.edit');
        Route::put('hierarchy/{user}', [HierarchyController::class, 'update'])->name('hierarchy.update');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});
