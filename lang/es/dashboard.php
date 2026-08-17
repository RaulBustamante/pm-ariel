<?php

declare(strict_types=1);

return [

    // --- Pantalla de inicio ------------------------------------------
    // Estas tres claves existen desde la Etapa 1. Se documentan aqui porque
    // ya se perdieron una vez al agregar las del tablero de proyecto.
    'empty_title' => 'Todavia no hay proyectos',
    'empty_body' => 'Aqui vas a ver como va cada proyecto: tus tareas del dia, lo que se esta desviando y el semaforo de cada uno. Empieza creando el primero, o carga el proyecto de ejemplo desde Primeros pasos.',
    'empty_action' => 'Crear el primer proyecto',

    'title' => 'Tablero',

    'progress' => 'Avance',
    'progress_help' => 'Ponderado por duración, no por número de tareas.',
    'elapsed' => 'Tiempo transcurrido',
    'elapsed_help' => 'Del arranque al fin calculado.',
    'behind_by' => 'El avance va :points puntos abajo del tiempo transcurrido.',
    'overdue' => 'Vencidas',
    'overdue_help' => 'Debieron terminar y no están al 100 %.',
    'finish' => 'Fin calculado',

    'light_green' => 'El proyecto va en fecha.',
    'light_amber' => 'Hay cosas que conviene revisar.',
    'light_red' => 'Algo amenaza la entrega.',

    'why_green' => 'Nada vencido y ningún aviso abierto.',
    'why_overdue' => 'Hay :count tarea(s) que debieron terminar y siguen abiertas.',
    'why_behind' => 'El avance va en :progress % y el tiempo transcurrido en :elapsed %.',
    'why_amber_generic' => 'Hay avisos abiertos en el panel de Avisos.',

    's_curve' => 'Curva S — avance acumulado',
    's_curve_label' => 'Curva de avance acumulado: lo planeado contra lo real.',
    's_curve_description' => 'La línea punteada gris es el trabajo que debería estar terminado a cada semana según el plan. La línea azul es el que de verdad está terminado, y se detiene en la semana actual. Donde se separan es donde el proyecto se está atrasando. Los mismos datos están en la vista Lista, tarea por tarea.',
    's_curve_help' => 'La línea real se detiene en hoy: dibujarla hacia el futuro sería afirmar un avance que todavía no ocurre.',
    'planned' => 'Planeado',
    'actual' => 'Real',

    'distribution' => 'Distribución de tareas',
    'no_data' => 'No hay tareas con fechas todavía.',

    'intro' => 'Como va cada proyecto que alcanzas a ver. Entra a uno para el detalle.',

];
