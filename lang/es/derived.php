<?php

declare(strict_types=1);

/*
| Los documentos que se generan solos.
|
| Dos textos por documento y ninguno decorativo:
|
|   `help_*`   de donde sale. Un informe que no dice de que datos se armo obliga
|              a confiar en el o a no usarlo, y casi siempre gana lo segundo.
|
|   `empty_*`  **que capturar para que deje de estar vacio**, y donde. Un
|              derivado vacio no se arregla capturandolo en su propia pantalla:
|              se arregla en la pantalla donde vive el dato. Decir cual es la
|              diferencia entre un documento inutil y una instruccion.
*/

return [

    'download' => 'Descargar en PDF',
    'empty' => 'Este documento todavia sale vacio.',
    'no_spi' => 'Falta linea base o avance capturado.',
    'no_actuals' => 'Falta capturar costo real en las tareas ya arrancadas.',

    // --- Titulos de columna ----------------------------------------------
    'col_wbs' => 'EDT',
    'col_name' => 'Nombre',
    'col_detail' => 'En que consiste',
    'col_owner' => 'Responsable',
    'col_duration' => 'Duracion',
    'col_start' => 'Inicio',
    'col_finish' => 'Fin',
    'col_cost' => 'Costo',
    'col_predecessors' => 'Depende de',
    'col_constraint' => 'Restriccion',
    'col_float' => 'Holgura',
    'col_critical' => 'Ruta critica',
    'col_kind' => 'Tipo',
    'col_role' => 'Puesto',
    'col_rate' => 'Tarifa',
    'col_capacity' => 'Capacidad',
    'col_supplier' => 'Proveedor',
    'col_origin' => 'Origen',
    'col_baseline_cost' => 'Comprometido',
    'col_current_cost' => 'Hoy',
    'col_variance' => 'Diferencia',
    'col_code' => 'Clave',
    'col_description' => 'Descripcion',
    'col_category' => 'Categoria',
    'col_probability' => 'Prob.',
    'col_impact' => 'Impacto',
    'col_level' => 'Nivel',
    'col_status' => 'Estado',
    'col_organization' => 'Organizacion',
    'col_power' => 'Poder',
    'col_interest' => 'Interes',
    'col_quadrant' => 'Que hacer',
    'col_strategy' => 'Estrategia',
    'col_expectations' => 'Que espera',
    'col_measure' => 'Indicador',
    'col_value' => 'Valor',
    'col_reading' => 'Como se lee',
    'col_reference' => 'Numero',
    'col_occurred_on' => 'Fecha',
    'col_title' => 'Que paso',
    'col_outcome' => 'Recomendacion',

    // --- Los cuadrantes de interesados ------------------------------------
    'quadrant_manage' => 'Gestionar de cerca',
    'quadrant_satisfy' => 'Mantener satisfecho',
    'quadrant_inform' => 'Mantener informado',
    'quadrant_monitor' => 'Vigilar sin esfuerzo',

    // --- Indicadores ------------------------------------------------------
    'measure_planned_finish' => 'Fin segun el plan',
    'measure_spi' => 'Indice de cronograma (SPI)',
    'measure_forecast_finish' => 'Fin al ritmo de hoy',
    'measure_tasks_done' => 'Tareas cerradas',
    'measure_progress' => 'Avance ganado',
    'measure_budget' => 'Presupuesto',
    'measure_actual_cost' => 'Costo real',
    'measure_cost_index' => 'Indice de costo (CPI)',
    'measure_schedule_index' => 'Indice de cronograma (SPI)',
    'forecast_late' => 'Al ritmo de hoy se entrega :days dia(s) despues de lo planeado.',
    'forecast_early' => 'Al ritmo de hoy se entrega :days dia(s) antes de lo planeado.',
    'forecast_blocked' => 'No se puede pronosticar sin linea base y sin avance capturado. Un pronostico inventado se cree.',

    // --- De donde sale cada uno -------------------------------------------
    'help_wbs_dictionary' => 'Cada paquete y cada tarea del plan, con en que consiste, quien responde, cuanto dura y cuanto cuesta. Sale del plan de trabajo; no se captura nada aqui.',
    'help_activity_attributes' => 'Lo que el estandar pide documentar de cada actividad mas alla de su nombre: de que depende, que restriccion tiene, cuanta holgura le queda y si esta en la ruta critica.',
    'help_resource_breakdown_structure' => 'La RBS: los recursos del proyecto agrupados por especie, con su tarifa, su capacidad y si son de casa o de fuera.',
    'help_cost_baseline' => 'Lo que se comprometio en la linea base contra lo que cuesta hoy, renglon por renglon. Sin linea base no hay contra que comparar y el documento sale vacio a proposito.',
    'help_risk_report' => 'El informe formal sobre el registro de riesgos, ordenado de mayor a menor exposicion. Un informe ordenado por clave obliga a leerlo entero para encontrar el que importa.',
    'help_stakeholder_engagement_plan' => 'Que hacer con cada interesado segun donde cae en la matriz de poder e interes. El cuadrante lo deduce el sistema; la estrategia y las expectativas se capturan en Interesados.',
    'help_schedule_forecasts' => 'El indice de avance llevado al calendario. El SPI dice «vas al 68 % del ritmo debido» y nadie sabe que hacer con eso; en fechas dice cuando acaba esto si nada cambia.',
    'help_lessons_learned_report' => 'El informe sobre el registro de lecciones que ya crece durante el proyecto. No se vuelve a capturar nada: se ordena y se imprime.',
    'help_final_project_report' => 'Las cifras con las que cierra el proyecto: cuanto se hizo, cuanto costo y como se compara con lo comprometido.',

    // --- Que falta capturar cuando sale vacio ------------------------------
    'empty_wbs_dictionary' => 'Captura tareas en el plan de trabajo.',
    'empty_activity_attributes' => 'Captura tareas en el plan de trabajo.',
    'empty_resource_breakdown_structure' => 'Da de alta recursos en la pestaña Recursos: quien trabaja y que se consume.',
    'empty_cost_baseline' => 'Captura una linea base desde los ajustes del proyecto. Sin ella no hay compromiso contra el cual comparar.',
    'empty_risk_report' => 'Registra riesgos en el recorrido de inicio.',
    'empty_stakeholder_engagement_plan' => 'Registra interesados en el recorrido de inicio.',
    'empty_schedule_forecasts' => 'Hace falta linea base y avance capturado.',
    'empty_lessons_learned_report' => 'Anota lecciones en su registro, en el centro de documentos. Una leccion recogida al cierre ya es un recuerdo.',
    'empty_final_project_report' => 'Captura tareas y avance en el plan de trabajo.',

];
