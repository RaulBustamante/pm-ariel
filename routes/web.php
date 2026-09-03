<?php

declare(strict_types=1);

use App\Http\Controllers\AcceptanceRecordController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\HierarchyController;
use App\Http\Controllers\Admin\OrgUnitController;
use App\Http\Controllers\Admin\PositionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordController;
use App\Http\Controllers\AdvisorController;
use App\Http\Controllers\AnalysisController;
use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\BaselineController;
use App\Http\Controllers\BuzonController;
use App\Http\Controllers\CalendarSettingsController;
use App\Http\Controllers\CalendarViewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DerivedDocumentController;
use App\Http\Controllers\DocumentIssueController;
use App\Http\Controllers\EarnedValueController;
use App\Http\Controllers\GanttController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Initiation\InitiationController;
use App\Http\Controllers\Initiation\InitiationPackageController;
use App\Http\Controllers\Initiation\RiskController;
use App\Http\Controllers\Initiation\StakeholderController;
use App\Http\Controllers\KanbanController;
use App\Http\Controllers\NarrativeDocumentController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\PreferencesController;
use App\Http\Controllers\ProjectArchiveController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectLogController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RequirementController;
use App\Http\Controllers\ResourceController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TaskDependencyController;
use App\Http\Controllers\TaskImportController;
use App\Http\Controllers\TeamActivityController;
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

    Route::get('dashboard', [HomeController::class, 'index'])->name('dashboard');
    Route::get('team-activities', [TeamActivityController::class, 'index'])->name('team-activities.index');

    Route::get('onboarding', [OnboardingController::class, 'show'])->name('onboarding');
    Route::post('onboarding/demo', [OnboardingController::class, 'loadDemo'])->name('onboarding.demo.store');
    Route::delete('onboarding/demo', [OnboardingController::class, 'removeDemo'])->name('onboarding.demo.destroy');

    Route::get('preferences', [PreferencesController::class, 'edit'])->name('preferences.edit');
    Route::put('preferences', [PreferencesController::class, 'update'])->name('preferences.update');

    Route::post('buzon', [BuzonController::class, 'store'])->name('buzon.store');
    Route::get('buzon/adjuntos/{adjunto}', [BuzonController::class, 'attachment'])->name('buzon.adjunto');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::prefix('projects/{project}')->group(function (): void {
        Route::get('edit', [ProjectController::class, 'edit'])->name('projects.edit');
        Route::put('/', [ProjectController::class, 'update'])->name('projects.update');
        Route::delete('/', [ProjectController::class, 'destroy'])->name('projects.destroy');
        Route::post('members', [ProjectController::class, 'addMember'])->name('projects.members.store');
        Route::delete('members/{user}', [ProjectController::class, 'removeMember'])->name('projects.members.destroy');

        Route::get('calendars', [CalendarSettingsController::class, 'index'])->name('projects.calendars.index');
        Route::post('calendars', [CalendarSettingsController::class, 'store'])->name('projects.calendars.store');
        Route::post('calendars/{calendar}/preview', [CalendarSettingsController::class, 'preview'])->name('projects.calendars.preview');
        Route::post('calendars/{calendar}/exception', [CalendarSettingsController::class, 'applyException'])->name('projects.calendars.exception');
        Route::post('calendars/{calendar}/default', [CalendarSettingsController::class, 'makeDefault'])->name('projects.calendars.default');

        Route::post('baselines', [BaselineController::class, 'store'])->name('projects.baselines.store');
        Route::get('baselines/{baseline}', [BaselineController::class, 'compare'])->name('projects.baselines.compare');

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
        // Mover varias tareas al mismo paquete de un golpe. Va sin `{task}` en
        // la direccion porque el sujeto es una seleccion y no una tarea suelta:
        // los identificadores viajan en el cuerpo. No compite con
        // `tasks/{task}`, que solo responde a PUT y DELETE.
        Route::post('tasks/reparent', [TaskController::class, 'reparent'])->name('projects.tasks.reparent');

        // Comentarios y dependencias de una tarea. Van aparte del controlador de
        // tareas porque no recalculan lo mismo: comentar no toca el plan, y
        // ligar dos tareas lo recalcula y puede tener que deshacerse.
        Route::post('tasks/{task}/comments', [TaskCommentController::class, 'store'])
            ->name('projects.tasks.comments.store');
        Route::delete('tasks/{task}/comments/{comment}', [TaskCommentController::class, 'destroy'])
            ->name('projects.tasks.comments.destroy');

        Route::post('tasks/{task}/dependencies', [TaskDependencyController::class, 'store'])
            ->name('projects.tasks.dependencies.store');
        Route::delete('tasks/{task}/dependencies/{dependency}', [TaskDependencyController::class, 'destroy'])
            ->name('projects.tasks.dependencies.destroy');

        Route::get('import', [TaskImportController::class, 'show'])->name('projects.tasks.import');
        Route::post('import/preview', [TaskImportController::class, 'preview'])->name('projects.tasks.import.preview');
        Route::post('import', [TaskImportController::class, 'store'])->name('projects.tasks.import.store');

        Route::get('gantt', [GanttController::class, 'show'])->name('projects.gantt');
        Route::post('gantt/move', [GanttController::class, 'move'])->name('projects.gantt.move');

        Route::get('calendar', [CalendarViewController::class, 'show'])->name('projects.calendar');

        Route::get('kanban', [KanbanController::class, 'show'])->name('projects.kanban');
        Route::post('kanban/{task}/move', [KanbanController::class, 'move'])->name('projects.kanban.move');

        Route::get('dashboard', [DashboardController::class, 'show'])->name('projects.dashboard');

        Route::get('reports/pdf', [ReportController::class, 'pdf'])->name('projects.reports.pdf');
        Route::get('reports/complete', [ReportController::class, 'complete'])->name('projects.reports.complete');
        Route::get('reports/weekly', [ReportController::class, 'weekly'])->name('projects.reports.weekly');
        Route::get('documents', [ReportController::class, 'documents'])->name('projects.documents');
        Route::post('documents/issue/{code}', [DocumentIssueController::class, 'store'])
            ->whereIn('code', ['weekly', 'complete', 'sheet'])
            ->name('projects.documents.issue');
        Route::get('documents/issues/{issue}', [DocumentIssueController::class, 'download'])
            ->name('projects.documents.download');

        // El expediente: todas las versiones emitidas en un solo paquete.
        Route::get('documents/archive', [ProjectArchiveController::class, 'download'])
            ->name('projects.documents.archive');

        // Los veinticinco documentos que se redactan, sobre un solo controlador.
        // El codigo va en la direccion y lo valida el motor contra el catalogo:
        // uno inventado da 404 en vez de abrir un formulario vacio.
        Route::get('documents/write/{code}', [NarrativeDocumentController::class, 'edit'])
            ->name('projects.documents.narrative');
        Route::put('documents/write/{code}', [NarrativeDocumentController::class, 'update'])
            ->name('projects.documents.narrative.update');
        Route::post('documents/write/{code}/suggest/{section}', [NarrativeDocumentController::class, 'suggest'])
            ->name('projects.documents.narrative.suggest');
        Route::get('documents/write/{code}/pdf', [NarrativeDocumentController::class, 'pdf'])
            ->name('projects.documents.narrative.pdf');

        // Los catorce registros que crecen durante el proyecto, sobre un solo
        // controlador. Igual que los narrativos: el codigo va en la direccion y
        // lo valida el motor contra el catalogo.
        //
        // El PDF va declarado **antes** que las rutas de un renglon, para que
        // ninguna que se agregue despues con la forma `{code}/{algo}` se coma la
        // palabra «pdf» y deje la descarga en 404.
        Route::get('documents/log/{code}/pdf', [ProjectLogController::class, 'pdf'])
            ->name('projects.documents.log.pdf');
        Route::get('documents/log/{code}', [ProjectLogController::class, 'index'])
            ->name('projects.documents.log');
        Route::post('documents/log/{code}', [ProjectLogController::class, 'store'])
            ->name('projects.documents.log.store');
        Route::get('documents/log/{code}/{entry}/edit', [ProjectLogController::class, 'edit'])
            ->name('projects.documents.log.edit');
        Route::put('documents/log/{code}/{entry}', [ProjectLogController::class, 'update'])
            ->name('projects.documents.log.update');
        Route::delete('documents/log/{code}/{entry}', [ProjectLogController::class, 'destroy'])
            ->name('projects.documents.log.destroy');

        // Las actas de aceptacion: la cuarta y ultima especie del catalogo.
        // Mismo patron que los registros; el PDF va declarado antes que las
        // rutas de un renglon.
        Route::get('documents/record/{code}', [AcceptanceRecordController::class, 'index'])
            ->name('projects.documents.record');
        Route::post('documents/record/{code}', [AcceptanceRecordController::class, 'store'])
            ->name('projects.documents.record.store');
        Route::get('documents/record/{code}/{record}/pdf', [AcceptanceRecordController::class, 'pdf'])
            ->name('projects.documents.record.pdf');
        Route::get('documents/record/{code}/{record}/edit', [AcceptanceRecordController::class, 'edit'])
            ->name('projects.documents.record.edit');
        Route::put('documents/record/{code}/{record}', [AcceptanceRecordController::class, 'update'])
            ->name('projects.documents.record.update');
        Route::post('documents/record/{code}/{record}/sign', [AcceptanceRecordController::class, 'sign'])
            ->name('projects.documents.record.sign');
        Route::delete('documents/record/{code}/{record}', [AcceptanceRecordController::class, 'destroy'])
            ->name('projects.documents.record.destroy');

        // Los documentos que se generan solos. La quinta maquinaria: una
        // consulta que devuelve renglones y una tabla que los pinta.
        Route::get('documents/derived/{code}/pdf', [DerivedDocumentController::class, 'pdf'])
            ->name('projects.documents.derived.pdf');
        Route::get('documents/derived/{code}', [DerivedDocumentController::class, 'show'])
            ->name('projects.documents.derived');

        // Requisitos: la unica pieza de la Etapa 7 que necesito datos nuevos.
        // Un parrafo explica el alcance; una matriz contesta quien entrega que.
        Route::get('requirements', [RequirementController::class, 'index'])
            ->name('projects.requirements');
        Route::post('requirements', [RequirementController::class, 'store'])
            ->name('projects.requirements.store');
        Route::put('requirements/{requirement}', [RequirementController::class, 'update'])
            ->name('projects.requirements.update');
        Route::delete('requirements/{requirement}', [RequirementController::class, 'destroy'])
            ->name('projects.requirements.destroy');
        Route::get('reports/gantt', [ReportController::class, 'ganttPrint'])->name('projects.reports.gantt');
        Route::get('reports/csv', [ReportController::class, 'csv'])->name('projects.reports.csv');

        Route::get('analysis', [AnalysisController::class, 'show'])->name('projects.analysis');

        // Valor ganado y pronostico de costos. Van juntos porque son el mismo
        // calculo leido de dos maneras: los indices contestan como vamos, y los
        // pronosticos, en cuanto acaba esto.
        Route::get('earned-value', [EarnedValueController::class, 'show'])->name('projects.earned-value');
        Route::get('earned-value/pdf', [EarnedValueController::class, 'pdf'])->name('projects.earned-value.pdf');

        Route::get('advisor', [AdvisorController::class, 'show'])->name('projects.advisor');
        Route::post('advisor', [AdvisorController::class, 'analyze'])->name('projects.advisor.analyze');

        Route::get('resources', [ResourceController::class, 'index'])->name('projects.resources.index');
        Route::post('resources', [ResourceController::class, 'store'])->name('projects.resources.store');
        Route::get('resources/{resource}/edit', [ResourceController::class, 'edit'])->name('projects.resources.edit');
        Route::put('resources/{resource}', [ResourceController::class, 'update'])->name('projects.resources.update');
        Route::delete('resources/{resource}', [ResourceController::class, 'destroy'])->name('projects.resources.destroy');
        Route::post('tasks/{task}/attachments', [AttachmentController::class, 'store'])->name('projects.attachments.store');
        Route::get('attachments/{attachment}', [AttachmentController::class, 'download'])->name('projects.attachments.download');
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy'])->name('projects.attachments.destroy');

        Route::post('tasks/{task}/assignments', [ResourceController::class, 'assign'])->name('projects.assignments.store');
        Route::delete('tasks/{task}/assignments/{resource}', [ResourceController::class, 'unassign'])->name('projects.assignments.destroy');
    });

    Route::prefix('admin')->name('admin.')->group(function (): void {
        Route::get('buzon', [BuzonController::class, 'index'])->name('buzon.index');
        Route::patch('buzon/{ticket}', [BuzonController::class, 'update'])->name('buzon.update');
        Route::delete('buzon/{ticket}', [BuzonController::class, 'destroy'])->name('buzon.destroy');
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

        // Aparte de 'users.update': la contraseña no viaja en el formulario de
        // perfil para que un guardado de rutina no pueda tocarla.
        Route::put('users/{user}/password', [UserPasswordController::class, 'update'])->name('users.password.update');
        Route::post('users/{user}/password/reset', [UserPasswordController::class, 'reset'])->name('users.password.reset');

        Route::get('org-units', [OrgUnitController::class, 'index'])->name('org-units.index');
        Route::get('org-units/create', [OrgUnitController::class, 'create'])->name('org-units.create');
        Route::post('org-units', [OrgUnitController::class, 'store'])->name('org-units.store');
        Route::get('org-units/{orgUnit}/edit', [OrgUnitController::class, 'edit'])->name('org-units.edit');
        Route::put('org-units/{orgUnit}', [OrgUnitController::class, 'update'])->name('org-units.update');
        Route::delete('org-units/{orgUnit}', [OrgUnitController::class, 'destroy'])->name('org-units.destroy');

        Route::get('positions', [PositionController::class, 'index'])->name('positions.index');
        Route::get('positions/create', [PositionController::class, 'create'])->name('positions.create');
        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
        Route::get('positions/{position}/edit', [PositionController::class, 'edit'])->name('positions.edit');
        Route::put('positions/{position}', [PositionController::class, 'update'])->name('positions.update');
        Route::delete('positions/{position}', [PositionController::class, 'destroy'])->name('positions.destroy');

        Route::get('hierarchy', [HierarchyController::class, 'index'])->name('hierarchy.index');
        Route::get('hierarchy/{user}', [HierarchyController::class, 'edit'])->name('hierarchy.edit');
        Route::put('hierarchy/{user}', [HierarchyController::class, 'update'])->name('hierarchy.update');

        Route::get('audit', [AuditLogController::class, 'index'])->name('audit.index');
    });
});
