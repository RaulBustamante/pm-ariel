<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | El catalogo de documentos del PMI
    |--------------------------------------------------------------------------
    |
    | Setenta documentos. No se construyen con setenta plantillas escritas a
    | mano: se clasifican por **especie**, y cada especie tiene una sola
    | maquinaria detras. Esa es la diferencia entre un trabajo de un mes y uno de
    | una semana, y entre algo que se puede mantener y algo que no.
    |
    |   derived   Sale entero de datos que el sistema ya tiene. No se captura
    |             nada: se genera. El cronograma, la EDT, el registro de riesgos,
    |             el informe de estado. Son los que se automatizan al 100 %.
    |
    |   narrative Texto con secciones fijas. Se captura una vez y se actualiza.
    |             Aqui es donde el asistente de redaccion sirve de verdad (D-018),
    |             porque la estructura la pone el sistema y las palabras el
    |             usuario o el modelo.
    |
    |   log       Una lista que crece durante el proyecto: incidencias,
    |             decisiones, cambios, lecciones, minutas. **Los ocho comparten
    |             la misma forma** --fecha, quien, que, estado-- asi que son una
    |             sola tabla con un tipo, no ocho tablas.
    |
    |   record    Un acta de aceptacion o firma. Congela un estado y se archiva.
    |
    | El campo `state` dice que tanto existe hoy, y es lo que pinta el tablero de
    | documentos. Se mantiene aqui y no en la vista para que actualizarlo al
    | terminar un bloque sea una linea, y para que nadie tenga que adivinar
    | leyendo codigo si algo ya se puede emitir.
    |
    |   ready     Se puede emitir hoy.
    |   partial   El dato existe pero todavia no hay documento formal.
    |   planned   Registrado, sin construir. Ver el BUILD_PLAN.
    |
    | El campo `source` nombra de donde sale, y sirve para explicarle al usuario
    | por que un documento esta bloqueado: los de costos esperan a que se pueda
    | capturar costo por hora y materiales.
    |
    | Los nombres visibles **no viven aqui**: estan en los archivos `documents.php` de cada idioma,
    | como todo el texto del sistema (D-004).
    |
    */

    'catalogue' => [

        'project_charter' => [
            'group' => 'initiating',
            'kind' => 'narrative',
            'state' => 'ready',
            'source' => 'charter',
        ],

        'business_case' => [
            'group' => 'initiating',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => 'charter',
        ],

        'benefits_management_plan' => [
            'group' => 'initiating',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'stakeholder_register' => [
            'group' => 'initiating',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'stakeholders',
        ],

        'assumption_log' => [
            'group' => 'initiating',
            'kind' => 'log',
            'state' => 'partial',
            'source' => 'charter',
        ],

        'project_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'scope_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'requirements_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'schedule_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'cost_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'quality_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'resource_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'communications_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'risk_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'procurement_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'stakeholder_engagement_plan' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'stakeholders',
        ],

        'change_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'configuration_management_plan' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'requirements_documentation' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'requirements_traceability_matrix' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => null,
        ],

        'project_scope_statement' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'partial',
            'source' => 'charter',
        ],

        'wbs' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'tasks',
        ],

        'wbs_dictionary' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => 'tasks',
        ],

        'activity_list' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'tasks',
        ],

        'activity_attributes' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => 'tasks',
        ],

        'milestone_list' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'tasks',
        ],

        'project_schedule' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'schedule',
        ],

        'schedule_baseline' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'baselines',
        ],

        'cost_estimates' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'costs',
        ],

        'cost_baseline' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'costs',
        ],

        'resource_requirements' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'resources',
        ],

        'resource_breakdown_structure' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'resources',
        ],

        'team_charter' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'risk_register' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'risks',
        ],

        'risk_report' => [
            'group' => 'planning',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'risks',
        ],

        'procurement_documentation' => [
            'group' => 'planning',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'project_communications' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'issue_log' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'change_requests' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'decision_log' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'quality_reports' => [
            'group' => 'executing',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'test_inspection_records' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'team_performance_assessments' => [
            'group' => 'executing',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'lessons_learned_register' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'meeting_minutes' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'action_item_log' => [
            'group' => 'executing',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'deliverable_acceptance_records' => [
            'group' => 'executing',
            'kind' => 'record',
            'state' => 'planned',
            'source' => null,
        ],

        'work_performance_data' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'schedule',
        ],

        'work_performance_information' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'schedule',
        ],

        'work_performance_reports' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'weekly',
        ],

        'project_status_report' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'weekly',
        ],

        'progress_report' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'weekly',
        ],

        'schedule_forecasts' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'schedule',
        ],

        'cost_forecasts' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'costs',
        ],

        'variance_analysis' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'ready',
            'source' => 'baselines',
        ],

        'earned_value_report' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'partial',
            'source' => 'costs',
        ],

        'change_log' => [
            'group' => 'monitoring',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'approved_change_requests' => [
            'group' => 'monitoring',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'risk_updates' => [
            'group' => 'monitoring',
            'kind' => 'log',
            'state' => 'partial',
            'source' => 'risks',
        ],

        'issue_updates' => [
            'group' => 'monitoring',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'quality_control_measurements' => [
            'group' => 'monitoring',
            'kind' => 'log',
            'state' => 'planned',
            'source' => null,
        ],

        'requirements_traceability_updates' => [
            'group' => 'monitoring',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => null,
        ],

        'final_project_report' => [
            'group' => 'closing',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => null,
        ],

        'lessons_learned_report' => [
            'group' => 'closing',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => null,
        ],

        'project_closure_report' => [
            'group' => 'closing',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'transition_handover' => [
            'group' => 'closing',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'acceptance_signoff' => [
            'group' => 'closing',
            'kind' => 'record',
            'state' => 'planned',
            'source' => null,
        ],

        'procurement_closure' => [
            'group' => 'closing',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

        'archived_project_documents' => [
            'group' => 'closing',
            'kind' => 'derived',
            'state' => 'planned',
            'source' => null,
        ],

        'benefits_transition' => [
            'group' => 'closing',
            'kind' => 'narrative',
            'state' => 'planned',
            'source' => null,
        ],

    ],

];
