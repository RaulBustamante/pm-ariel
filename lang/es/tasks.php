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
    'predecessors_help' => 'Se escribe el número de la primera columna de la tarea que va antes. «1.2» significa que esta empieza cuando aquella termine; «1.2+2d» deja 2 días de espera; «1.2SS» hace que las dos arranquen juntas. Separa varias con coma.',
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
    'requested_start' => 'Fecha de inicio',
    'requested_start_help' => 'La tarea no comenzará antes de esta fecha. Si una dependencia termina después, el inicio se recorre automáticamente.',
    'deadline' => 'Fecha límite',
    'deadline_help' => 'Compromiso máximo de cierre. Si el plan ya no llega, la tarea mostrará holgura negativa.',
    'deadline_before_start' => 'La fecha límite no puede ser anterior a la fecha de inicio.',
    'row' => 'Renglón',
    'wbs' => 'N.º',
    'reference_of' => 'Número de «:name». Es el que se escribe en «Depende de».',

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

    // Capturar dentro de un paquete sin tener que indentar despues. El alta
    // siempre creaba al primer nivel: capturar cinco subtareas costaba cinco
    // altas mas cinco indentadas, y la mitad del trabajo era acomodar.
    'add_subtask' => 'Subtarea',
    'add_subtask_of' => 'Agregar una subtarea dentro de «:name»',
    'new_subtask_of' => 'Nueva subtarea de «:name»',
    'new_subtask_hint' => 'Nace ya dentro del paquete: no hay que indentarla despues. Al guardar, el formulario se queda aqui para capturar la siguiente.',
    'new_subtask_exit' => 'Mejor crearla al primer nivel',

    // Reacomodar en grupo. Una tarea a la vez es un recalculo del proyecto y
    // una recarga por cada flechita; cinco subtareas seguidas eran cinco.
    'bulk_title' => 'Mover varias de una vez',
    'bulk_hint' => 'Marca las tareas en la primera columna y escoge dentro de que paquete van. Se mueven todas juntas, con un solo recalculo.',
    'bulk_select' => 'Seleccionar «:name» para moverla en grupo',
    'bulk_select_all' => 'Seleccionar todas las tareas visibles',
    'bulk_parent' => 'Meterlas dentro de',
    'bulk_top_level' => 'Primer nivel (sacarlas de su paquete)',
    'bulk_apply' => 'Mover las seleccionadas',
    'bulk_selected_count' => ':count seleccionada(s)',
    'bulk_none_selected' => 'No marcaste ninguna tarea. Marca al menos una en la primera columna.',
    'bulk_moved' => ':count tarea(s) movidas y plan recalculado.',
    'bulk_cycle' => 'Una tarea no puede quedar dentro de una de sus propias subtareas: «:name» es descendiente de la que quieres mover.',
    'bulk_into_itself' => 'Una tarea no puede quedar dentro de si misma.',

    'legend_subtask' => 'Crea una subtarea ya dentro de este paquete, sin tener que indentarla despues.',
    'legend_bulk' => 'Marca varias tareas para moverlas juntas al mismo paquete.',

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

    // --- Seguimiento del trabajo (Etapa 8) -------------------------------
    'state' => 'Estado',
    // --- La espera --------------------------------------------------------
    //
    // Un eje aparte del avance, no un cuarto estado suyo: una tarea puede estar
    // en curso al 85 % y esperando aprobacion al mismo tiempo.
    'waiting' => 'En espera de',
    'waiting_none' => 'No está esperando nada',
    'waiting_help' => 'Para cuando la tarea no avanza por algo de afuera: una firma, una prueba del usuario, una respuesta. Convive con el avance, no lo reemplaza — una tarea al 85 % puede estar esperando. No mueve ninguna fecha del plan.',
    'waiting_approval' => 'Esperando aprobación',
    'waiting_uat' => 'En pruebas del usuario (UAT)',
    'waiting_client' => 'Esperando respuesta del cliente',
    'waiting_third_party' => 'Esperando a un tercero',
    'waiting_blocked' => 'Detenida por un impedimento',
    'waiting_note' => '¿A quién esperas?',
    'waiting_note_help' => 'Quién tiene que responder, y el folio si hay uno. Ejemplo: «Sistemas, para dar de alta al usuario» o «Ana Ruiz, acta de UAT».',
    'waiting_since' => 'Esperando desde',
    'waiting_since_date' => 'Esperando desde el :date (:count día(s))',
    'waiting_days_short' => ':count d',
    'waiting_clock_help' => 'La fecha la pone el sistema al empezar la espera. Cambiar el tipo la reinicia; corregir la nota no. Al llegar al 100 % la espera se limpia sola.',
    'only_waiting' => 'Solo las que esperan',

    'state_todo' => 'Sin empezar',
    'state_doing' => 'En curso',
    'state_done' => 'Terminada',
    'state_help' => 'Sale del avance capturado: 0 % sin empezar, 100 % terminada. No hay un estado aparte que mantener al día.',
    'notes' => 'Notas',
    'notes_help' => 'Lo que hay que saber para trabajar esta tarea, y lo que se acordó mientras se trabajaba. Se ve en el detalle y se anuncia en la lista y en el tablero.',
    'has_notes' => 'Tiene notas',
    'real_dates' => 'Fechas reales',
    'actual_start' => 'Arrancó',
    'actual_finish' => 'Terminó',
    'not_started_yet' => 'Todavía no arranca.',
    'in_progress_since' => 'En curso desde el :date.',
    'finished_on' => 'Terminada el :date.',
    'finished_late' => ':days día(s) después de lo planeado.',
    'finished_early' => ':days día(s) antes de lo planeado.',
    'finished_on_time' => 'En la fecha planeada.',
    'real_dates_help' => 'Se anotan solas al capturar avance: la de arranque en cuanto pasa de cero, la de cierre al llegar a 100 %. Nadie las teclea.',
    'open_detail' => 'Abrir el detalle',
    'detail_hint' => 'Doble clic en la tarjeta para abrir el detalle.',
    'detail_hint_row' => 'Doble clic en un renglón —fuera de los campos— abre el detalle de esa tarea. También lo abre el «⋯» del final del renglón.',

    // --- Leyenda de la lista ---------------------------------------------
    'legend' => '¿Qué significa cada símbolo?',
    'legend_summary' => 'Paquete: agrupa a las tareas de abajo. Su duración y su avance salen de ellas, no se capturan.',
    'legend_milestone' => 'Hito: no dura nada, marca una fecha —una entrega, una firma, un arranque.',
    'legend_critical' => 'Si esta tarea se retrasa un día, la entrega del proyecto se retrasa un día.',
    'legend_detail' => 'Abre el detalle de la tarea: notas, adjuntos, dependencias e historial.',
    'legend_notes' => 'Igual que el anterior, pero esta tarea ya tiene notas escritas.',
    'legend_indent' => 'Mete la tarea dentro del paquete de arriba, o la saca.',
    'legend_move' => 'Sube o baja la tarea dentro de su grupo.',
    'legend_delete' => 'Elimina la tarea. Si tiene tareas debajo, también se van.',
    'mark_done' => 'Marcar terminada',

    // --- Depende de, en español (Etapa 9) ---------------------------------
    'depends_on' => 'Depende de',
    'depends_on_help' => 'Qué tiene que pasar antes de que esta tarea pueda avanzar. Escoge la tarea de la lista; no hace falta saber ningún código.',
    'depends_on_none' => 'Esta tarea no espera a ninguna otra.',
    'add_dependency' => 'Agregar',
    'which_task' => 'Cuál tarea',
    'relationship' => 'Cuándo puede empezar esta',
    'lag_days' => 'Días de espera',
    'lag_days_help' => 'Cuántos días hay que dejar pasar después. Cero si arranca de inmediato; un número negativo si puede traslaparse.',
    'dependency_added' => 'Dependencia agregada y plan recalculado.',
    'dependency_removed' => 'Dependencia quitada y plan recalculado.',
    'dependency_would_loop' => 'Esa dependencia haria un circulo: la tarea acabaria esperandose a si misma. No se agrego, y el plan quedo como estaba.',
    'blocks' => 'Esto detiene a',
    'blocks_none' => 'Ninguna otra tarea espera a esta.',

    // Las cuatro relaciones dichas como se dirian en voz alta.
    'rel_FS' => 'Después de que termine',
    'rel_SS' => 'Cuando aquella empiece',
    'rel_FF' => 'Terminan al mismo tiempo',
    'rel_SF' => 'Termina cuando aquella empiece',
    'rel_FS_short' => 'después de',
    'rel_SS_short' => 'a la par de',
    'rel_FF_short' => 'cierra con',
    'rel_SF_short' => 'cierra al empezar',
    'lag_after' => 'con :days día(s) de espera',
    'lag_before' => 'traslapada :days día(s)',
    'expression_help' => 'Atajo para quien viene de MS Project: «12», «12FS+2d», «15SS». Si prefieres no aprenderlo, usa «Depende de» en el detalle de la tarea — hace lo mismo escogiendo de una lista.',

    // --- Comentarios ------------------------------------------------------
    'comments' => 'Qué ha pasado aquí',
    'comments_help' => 'Lo que se fue diciendo mientras se trabajaba, junto con lo que el sistema registró. La nota de arriba dice cómo está la tarea hoy; esto dice cómo llegó ahí.',
    'comment_placeholder' => 'Escribe qué pasó...',
    'comment_add' => 'Comentar',
    'comment_added' => 'Comentario guardado.',
    'comment_deleted' => 'Comentario eliminado.',
    'comment_delete_confirm' => '¿Eliminar este comentario?',
    'timeline_empty' => 'Todavía no hay nada. El primer comentario es el que hace útil al resto.',
    'changed' => 'cambió',
    'parent' => 'Paquete',

    'calendar' => 'Jornada de esta tarea',
    'calendar_default' => 'La del proyecto',
    'calendar_help' => 'Solo si esta tarea se trabaja con un horario distinto: un turno de noche, un contratista con otra jornada.',

];
