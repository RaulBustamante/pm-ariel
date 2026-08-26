<?php

declare(strict_types=1);

return [

    'title' => 'Avisos',
    'heading' => 'Qué conviene revisar',
    'intro' => 'El sistema revisa el plan después de cada cambio y señala lo que amenaza la entrega. Dice qué pasa y por qué; la decisión sigue siendo tuya.',

    'why_no_suggestion' => 'Todavía no propone qué hacer. Detectar que alguien está al 180 % es aritmética que puedes comprobar; decirte a quién moverle el trabajo es un juicio, y sin proyectos reales de la organización de los cuales aprender, una sugerencia mala costaría más de lo que ahorra.',

    'none' => 'Nada que señalar. El plan se ve sano.',
    'analyze' => 'Revisar el plan',
    'analyzed' => 'Plan revisado.',
    'last_check' => 'Última revisión: :when',

    'severity_critical' => 'Amenaza la entrega',
    'severity_warning' => 'Conviene revisar',
    'severity_info' => 'Para tu información',

    'workload' => 'Carga de trabajo',
    'workload_intro' => 'El pico es lo más alto que llega esa persona cuando sus tareas se traslapan. Arriba de 100 % está prometiendo horas que no tiene.',
    'peak' => 'Pico',
    'assigned_tasks' => 'Tareas asignadas',
    'capacity' => 'Capacidad',
    'no_resources' => 'Todavía no hay recursos dados de alta.',

    // --- Reglas ---

    'overallocated' => ':name llega a :percent % cuando se traslapan: :tasks',
    'overallocated_why' => 'Su capacidad es :capacity %. Por encima de eso, o la tarea tarda más de lo planeado o alguien trabaja horas que no existen. El plan dice una cosa y la realidad hará otra.',

    'duplicated' => ':name aparece :count veces en la lista de recursos.',
    'duplicated_why' => 'Si es la misma persona dada de alta dos veces, su carga real está partida a la mitad: cada registro se ve tranquilo y la persona no lo está.',

    'duplicated_email' => 'Hay dos recursos con el correo :email y nombres distintos.',

    'critical_without_owner' => ':task está en la ruta crítica y no tiene responsable.',
    'critical_without_owner_why' => 'En la ruta crítica no hay margen: un día de retraso es un día de retraso en la entrega. Una tarea sin nombre encima es una que nadie está empujando.',

    'negative_float' => ':task va :amount tarde contra su fecha comprometida.',
    'negative_float_why' => 'La holgura negativa significa que la fecha ya no se alcanza con el plan actual. No se arregla sola: o se mueve la fecha, o se recorta el alcance, o se agrega gente.',

    'overdue' => ':task debió terminar el :date y sigue en cero por ciento.',
    'overdue_why' => 'O el avance no se está capturando, o la tarea no ha empezado. Las dos cosas importan, y las dos se arreglan hoy más barato que en dos semanas.',

    // La espera olvidada: el unico atraso que se arregla con una llamada.
    'waiting_too_long' => 'La tarea :task lleva :days dia(s) laborales en «:reason», desde el :date.',
    'waiting_too_long_why' => 'Una tarea detenida esperando una firma, una prueba o la respuesta de un tercero no se atrasa sola: se atrasa porque nadie volvio a preguntar. Es el unico atraso que se arregla con una llamada, y el mas facil de no ver, porque la tarea no se ve mal — tiene avance, tiene responsable y su fecha todavia no llego.',
    'waiting_too_long_why_note' => 'Se espera a: :note. Una tarea detenida esperando a alguien no se atrasa sola: se atrasa porque nadie volvio a preguntar. Es el unico atraso que se arregla con una llamada, y el mas facil de no ver, porque la tarea no se ve mal.',

    'milestone_orphan' => 'El hito :task no depende de nada.',
    'milestone_orphan_why' => 'Un hito marca que algo termino. Si nada lo alimenta, se queda pegado al arranque del proyecto y dice que la entrega ocurre el primer dia — que es justo lo que nadie nota en un Gantt largo.',

    // Nombres cortos de cada regla, para cuando se agrupan en un reporte:
    // «18 tareas criticas sin responsable». En plural, porque agrupar de uno
    // no se agrupa: ese caso usa el mensaje completo del aviso.
    'rule_resource_overallocated' => 'recursos sobreasignados',
    'rule_resource_duplicated' => 'personas dadas de alta dos veces',
    'rule_resource_duplicated_email' => 'correos repetidos entre recursos',
    'rule_task_critical_without_owner' => 'tareas de la ruta crítica sin responsable',
    'rule_task_negative_float' => 'tareas con holgura negativa',
    'rule_task_overdue_without_progress' => 'tareas vencidas sin avance capturado',
    'rule_milestone_without_predecessors' => 'hitos sin nada que los produzca',
];
