<?php

declare(strict_types=1);

/*
| Los textos de los catorce registros que crecen durante el proyecto.
|
| Once juegos de estados cubren los catorce, asi que <<aprobada>> se escribe
| una vez y sirve para las solicitudes de cambio y para el registro de cambios.
|
| La ayuda de cada registro no describe la pantalla: dice **que se anota ahi y
| que no**. Es lo unico que evita que el registro de decisiones se llene de
| pendientes y el de incidencias de quejas.
*/

return [

    'add' => 'Anotar',
    'edit' => 'Editar el renglon',
    'recorded' => 'Anotado como :reference.',
    'amended' => 'Se actualizo :reference.',
    'deleted' => 'Renglon eliminado. Su numero no se vuelve a usar.',
    'empty' => 'Este registro todavia esta vacio.',
    'empty_filtered' => 'Ningun renglon coincide con el filtro.',
    'download' => 'Descargar en PDF',

    'reference' => 'Numero',
    'occurred_on' => 'Fecha',
    'occurred_on_help' => 'Cuando paso, no cuando lo estas capturando.',
    'entry_title' => 'Que paso',
    'entry_title_help' => 'Una linea que se entienda sola dentro de seis meses.',
    'detail' => 'Detalle',
    'owner' => 'Responsable',
    'owner_none' => 'Sin responsable',
    'due_on' => 'Fecha compromiso',
    'priority' => 'Prioridad',
    'priority_none' => 'Sin prioridad',
    'outcome' => 'Desenlace',
    'outcome_help' => 'Como se resolvio. Se llena al cerrar, no al abrir.',
    'recorded_by' => 'Lo anoto',

    'total' => 'Renglones',
    'open' => 'Abiertos',
    'overdue' => 'Vencidos',
    'overdue_help' => 'Siguen abiertos y su fecha compromiso ya paso.',

    'filter' => 'Filtrar',
    'filter_clear' => 'Quitar filtros',
    'filter_search' => 'Buscar en el texto',
    'filter_all_statuses' => 'Cualquier estado',
    'filter_all_owners' => 'Cualquier responsable',
    'showing' => 'Se muestran :shown de :total.',

    'confirm_delete' => 'Se elimina este renglon? Su numero no se vuelve a usar.',

    // --- Los estados, por juego (config/pmi_logs.php) --------------------
    'status_open' => 'Abierta',
    'status_in_progress' => 'En proceso',
    'status_resolved' => 'Resuelta',
    'status_closed' => 'Cerrada',

    'status_requested' => 'Solicitado',
    'status_under_review' => 'En analisis',
    'status_approved' => 'Aprobado',
    'status_rejected' => 'Rechazado',
    'status_implemented' => 'Implementado',
    'status_verified' => 'Verificado',

    'status_proposed' => 'Propuesta',
    'status_decided' => 'Decidida',
    'status_superseded' => 'Sustituida',

    'status_assumed' => 'Supuesto',
    'status_validated' => 'Confirmado',
    'status_invalidated' => 'Resulto falso',

    'status_captured' => 'Capturada',
    'status_applied' => 'Aplicada',
    'status_shared' => 'Compartida',

    'status_draft' => 'Borrador',
    'status_issued' => 'Emitida',

    'status_drafted' => 'Redactada',
    'status_sent' => 'Enviada',
    'status_acknowledged' => 'Acusada de recibido',

    'status_passed' => 'Conforme',
    'status_failed' => 'No conforme',
    'status_retest' => 'Se vuelve a probar',

    'status_identified' => 'Identificada',
    'status_updated' => 'Actualizada',

    'status_within_tolerance' => 'Dentro de tolerancia',
    'status_out_of_tolerance' => 'Fuera de tolerancia',
    'status_corrected' => 'Corregida',

    // --- Las prioridades -------------------------------------------------
    'priority_low' => 'Baja',
    'priority_medium' => 'Media',
    'priority_high' => 'Alta',
    'priority_critical' => 'Critica',

    // --- Que se anota en cada registro, y que no -------------------------
    'help_assumption_log' => 'Lo que se esta dando por cierto sin haberlo comprobado. Un supuesto que nadie escribio no se puede confirmar ni desmentir: simplemente se descubre falso el dia que rompe el plan.',
    'help_project_communications' => 'Que se comunico, a quien y cuando. Sirve el dia que alguien dice que nunca se le aviso.',
    'help_issue_log' => 'Lo que ya paso y esta estorbando hoy. Si todavia no pasa y podria pasar, es un riesgo y va en el registro de riesgos.',
    'help_change_requests' => 'Lo que alguien pide cambiar del alcance, del plazo o del costo. Se anota cuando se pide, no cuando se aprueba: la mitad del valor esta en las que se rechazaron.',
    'help_decision_log' => 'Que se decidio, cuando y por que. La razon es lo importante: sin ella, en tres meses la decision parece arbitraria y se vuelve a discutir.',
    'help_test_inspection_records' => 'Que se probo o inspecciono y como salio. Es la evidencia de que la calidad se reviso, no de que se prometio revisar.',
    'help_lessons_learned_register' => 'Lo que se aprendio, mientras se aprende. Una leccion recogida al cierre ya es un recuerdo; recogida el mismo dia todavia es util para el proyecto que sigue.',
    'help_meeting_minutes' => 'De que se hablo y a que se llego. Los pendientes que salgan de la junta van al registro de acciones, con dueno y fecha.',
    'help_action_item_log' => 'Los pendientes con dueno y fecha. Sin las dos cosas no es un pendiente: es una intencion.',
    'help_change_log' => 'Los cambios que ya se resolvieron, con su resultado. Es la historia de que se movio del plan original y con que autorizacion.',
    'help_approved_change_requests' => 'Los cambios autorizados y como va su ejecucion. Un cambio aprobado que nadie implementa es peor que uno rechazado: todos creen que ya esta.',
    'help_risk_updates' => 'Como cambio un riesgo: se materializo, bajo, subio o dejo de aplicar. Complementa el registro de riesgos, que dice el estado de hoy pero no como se llego a el.',
    'help_issue_updates' => 'Los avances de una incidencia entre que se abrio y que se cerro.',
    'help_quality_control_measurements' => 'Que se midio y contra que tolerancia. Una medicion sin su tolerancia es un numero, no un control.',

];
