<?php

declare(strict_types=1);

return [

    'zoom' => 'Escala',
    'zoom_day' => 'Días',
    'zoom_week' => 'Semanas',
    'zoom_month' => 'Meses',

    'today' => 'Hoy',

    'empty' => 'No hay nada que dibujar todavía. Captura tareas en la vista Lista y vuelve aquí.',

    'legend_task' => 'Tarea',

    'chart_label' => 'Diagrama de Gantt de :project, con :count tareas.',
    'chart_description' => 'Cada barra es una tarea; su largo es su duración. Las flechas van de una tarea a la que depende de ella. Las barras rojas son la ruta crítica. Los rombos son hitos. La línea punteada roja es hoy. La misma información está en la vista Lista, en forma de tabla.',

    'reading_help' => 'Las franjas grises son días no laborables: por eso una barra puede parecer que salta dos días. Pasa el cursor sobre una barra para ver sus fechas.',

    'baseline_bar' => 'Linea base: :from a :to',

    'row_summary' => 'del :from al :to. :state',
    'keyboard_help' => 'Con Tab recorres las tareas; Enter abre el detalle. Las fechas de cada barra se leen en la lista de la izquierda.',
    'move_dates' => 'Mover fechas',

    'moved' => ':task se fijo para no empezar antes del :date, y el plan se recalculo.',
    'cannot_move_summary' => 'Un paquete no se mueve: sus fechas salen de las tareas que tiene dentro.',
    'drag_confirm' => 'Mover esta tarea :days dias? Se recalcula el proyecto completo.',
    'drag_help' => 'Tambien puedes arrastrar una barra para moverla. Al soltar se te pregunta antes de guardar.',

];
