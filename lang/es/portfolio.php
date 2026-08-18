<?php

declare(strict_types=1);

/*
| La cartera: todos los proyectos en un renglon cada uno.
|
| La pregunta que contesta no es <<como va este proyecto>> --para eso esta el
| tablero de cada uno-- sino <<como vamos>>, que es la que hace quien tiene mas
| de un proyecto encima y hasta ahora obligaba a abrir doce pantallas y sumar a
| mano.
*/

return [

    'title' => 'Todos los proyectos',
    'help' => 'Lo que peor va, arriba. Ordenar por nombre o por fecha pone en el primer renglon el proyecto que no necesita atencion, y quien abre esto lo abre para encontrar el que si.',

    'project' => 'Proyecto',
    'tasks' => 'Cerradas',
    'hours' => 'Horas',
    'owner' => 'Responsable',
    'weight' => 'Cuanto pesa cada proyecto',

    'total_projects' => 'Proyectos',
    'total_late_projects' => 'Necesitan atencion',
    'total_overdue' => 'Tareas vencidas',
    'total_alerts' => 'Avisos abiertos',
    'total_cost' => 'Costo de la cartera',
    'earned_share' => ':percent % devengado segun el avance capturado.',

];
