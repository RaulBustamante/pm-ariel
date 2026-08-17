<?php

declare(strict_types=1);

return [

    'title' => 'Analisis',
    'workload' => 'Carga de recursos',
    'workload_help' => 'Cada barra es una semana. La linea punteada es la capacidad: lo que sobresale de ella es trabajo que no cabe en la jornada, y ahi la tarea tarda mas de lo planeado o alguien trabaja horas que no existen.',
    'workload_reader' => ':name llega a un pico de :peak horas en una semana, con una capacidad de :capacity horas.',
    'weeks' => 'semanas',
    'peak' => 'Pico',
    'capacity' => 'capacidad',
    'over' => 'por encima',
    'no_workload' => 'Todavia no hay trabajo asignado a ningun recurso.',
    'hours' => 'Distribucion de horas',
    'by_phase' => 'Costo por fase',
    'phase' => 'Fase',
    'no_costs' => 'Todavia no hay costo capturado.',
    'vs_baseline' => 'Contra la linea base',
    'baseline_cost' => 'Comprometido',
    'current_cost' => 'Hoy',
    'variance' => 'Desviacion',
    'no_baseline' => 'Este proyecto no tiene linea base capturada, asi que no hay contra que comparar el costo. Se captura desde los ajustes del proyecto.',
    'baseline_before_costs' => 'La desviacion es muy grande. Si esta linea base se capturo antes de que se cargaran los costos de recursos, solo congelo el costo fijo: la diferencia seria un cambio de metodo y no un sobrecosto. Conviene capturar una linea base nueva.',
];
