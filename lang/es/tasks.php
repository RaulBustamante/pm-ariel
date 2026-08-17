<?php

declare(strict_types=1);

return [

    'title' => 'Plan de trabajo',
    'list_view' => 'Lista',
    'gantt_view' => 'Gantt',

    'intro' => 'Captura las tareas, su duración y de qué dependen. Las fechas se calculan solas cada vez que cambias algo.',

    'add' => 'Agregar tarea',
    'new_task' => 'Nueva tarea',
    'name' => 'Tarea',
    'duration' => 'Duración',
    'duration_help' => 'Escríbela como quieras: 3d, 4h, 30m, 2s. Déjala en 0 para un hito.',
    'predecessors' => 'Depende de',
    'predecessors_help' => 'El número de renglón. «12» es fin a inicio; «12FS+2d» agrega 2 días de espera; «15SS» arranca junto con la 15. Separa varias con coma.',
    'owner' => 'Responsable',
    'start' => 'Inicio',
    'finish' => 'Fin',
    'float' => 'Holgura',
    'total_float' => 'Holgura total',
    'free_float' => 'Holgura libre',
    'critical' => 'Crítica',
    'milestone' => 'Hito',
    'summary' => 'Resumen',
    'progress' => 'Avance',
    'cost' => 'Costo',
    'constraint' => 'Restricción',
    'constraint_date' => 'Fecha de la restricción',
    'row' => 'Renglón',

    'created' => 'Tarea agregada y plan recalculado.',
    'updated' => 'Tarea actualizada y plan recalculado.',
    'deleted' => 'Tarea eliminada y plan recalculado.',
    'moved' => 'Plan reorganizado y recalculado.',
    'recalculated' => 'Plan recalculado.',

    'indent' => 'Indentar',
    'outdent' => 'Desindentar',
    'move_up' => 'Subir',
    'move_down' => 'Bajar',

    'cannot_indent' => 'No hay una tarea encima bajo la cual colgarla. Súbela primero o agrega una tarea arriba.',
    'cannot_outdent' => 'Ya está en el primer nivel: no hay de dónde sacarla.',
    'cannot_up' => 'Ya es la primera de su grupo.',
    'cannot_down' => 'Ya es la última de su grupo.',

    'dependency_unreadable' => 'No entiendo «:piece». Prueba con 12, 12FS+2d o 15SS.',
    'dependency_unknown_task' => 'No existe la tarea :reference en este proyecto.',
    'dependency_repeated' => 'La tarea :reference aparece dos veces. Con una basta.',
    'dependency_on_itself' => 'Una tarea no puede depender de sí misma.',
    'constraint_needs_date' => 'Esa restricción necesita una fecha para significar algo.',
    'could_not_calculate' => 'No se pudo calcular el plan. Revisa las dependencias.',

    'empty' => 'Todavía no hay tareas. Agrega la primera abajo — puedes escribir la lista completa y después organizarla con las flechas.',

    'recalculate' => 'Recalcular',
    'last_run' => 'Último cálculo: :when · :count tareas · :ms ms',
    'never_calculated' => 'Sin calcular todavía.',

    'critical_path' => 'Ruta crítica',
    'critical_explained' => 'Estas tareas no pueden retrasarse ni un día sin mover la fecha de entrega.',
    'float_explained' => 'La holgura es cuánto puede retrasarse una tarea sin mover la entrega. En cero, es crítica.',
    'negative_float_explained' => 'Holgura negativa: esta tarea ya va tarde contra una fecha comprometida.',

    'project_start' => 'Arranque del proyecto',
    'project_finish' => 'Fin calculado',

    'confirm_delete' => '¿Eliminar esta tarea? Si tiene tareas debajo, también se van.',

    'history' => 'Historial',
    'no_history' => 'Sin cambios registrados todavia.',
    'system' => 'Sistema',
    'dates' => 'Fechas calculadas',
    'successors' => 'De esto dependen',
    'calendar_view' => 'Calendario',

    'showing_capped' => 'Se muestran :shown de :total tareas. Usa el filtro para acotar la lista.',

];
