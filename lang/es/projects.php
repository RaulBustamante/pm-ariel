<?php

declare(strict_types=1);

return [

    'settings' => 'Ajustes',
    'details' => 'Datos del proyecto',

    'currency' => 'Moneda',
    'start_help' => 'Cambiar esta fecha recalcula el plan completo: todas las tareas se mueven con ella.',

    'status_draft' => 'Borrador',
    'status_active' => 'Activo',
    'status_on_hold' => 'En pausa',
    'status_closed' => 'Cerrado',
    'status_cancelled' => 'Cancelado',

    'updated' => 'Proyecto actualizado.',
    'updated_and_rescheduled' => 'Proyecto actualizado y plan recalculado con la fecha nueva.',

    'members' => 'Miembros',
    'members_help' => 'Ser miembro es lo que da permiso de editar. Ser jefe de alguien del proyecto da lectura, nunca escritura.',
    'add_member' => 'Agregar miembro',
    'project_role' => 'Rol en el proyecto',
    'owner' => 'dueño',
    'role_manager' => 'Gerente del proyecto',
    'role_member' => 'Miembro',
    'role_viewer' => 'Solo lectura',
    'member_added' => 'Miembro agregado.',
    'member_removed' => 'Miembro eliminado.',
    'cannot_remove_owner' => 'No se puede quitar al dueño del proyecto: se quedaría sin poder editarlo y haría falta un administrador para devolvérselo.',

    'baselines' => 'Líneas base',
    'baselines_help' => 'Una línea base congela el plan tal como se comprometió. No se edita ni se borra: para eso se captura otra.',
    'baseline_name' => 'Nombre',
    'baseline_default_name' => 'Línea base del :date',
    'capture_baseline' => 'Capturar línea base',
    'baseline_captured' => 'Línea base capturada.',
    'baseline_active' => 'Vigente',
    'no_baselines' => 'Todavía no hay ninguna. Captura una cuando el plan esté acordado.',
    'baseline_needs_tasks' => 'No hay tareas que congelar. Captura el plan primero.',

    'baseline_comparison' => 'Comparación contra línea base',
    'start_variance' => 'Desviación de inicio',
    'finish_variance' => 'Desviación de fin',
    'cost_variance' => 'Desviación de costo',
    'variance_help' => 'En tiempo de trabajo, no en días de calendario: un fin de semana no es retraso.',
    'on_time' => 'En fecha',
    'task_is_new' => 'nueva',
    'removed_tasks' => 'Tareas borradas',
    'removed_help' => 'Estaban comprometidas y ya no están en el plan.',
    'removed_warning' => 'Estas tareas se comprometieron en la línea base y ya no están en el plan:',

    'danger_zone' => 'Borrar este proyecto',
    'delete' => 'Borrar proyecto',
    'delete_help' => 'Se va a borrar :code con sus tareas, riesgos, interesados y lineas base. Deja de aparecer en todas las pantallas. La bitacora de auditoria se conserva, asi que queda registrado quien lo borro y cuando.',
    'delete_type_code' => 'Escribe :code para confirmar',
    'delete_confirmation_failed' => 'No se borro nada: lo que escribiste no coincide con :code.',
    'deleted' => 'Se borro el proyecto :name.',
    'planned_finish' => 'Fecha comprometida',
    'planned_finish_help' => 'Cuando se prometio que acabaria. Es otra cosa que la fecha que calcula el plan, y la diferencia entre las dos es la conversacion que hay que tener a tiempo.',
    'committed_vs_calculated' => 'Comprometido :committed · el plan calcula :calculated',
    'over_committed' => 'El plan acaba :days dia(s) despues de lo comprometido.',

];
