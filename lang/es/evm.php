<?php

declare(strict_types=1);

/*
| El informe de valor ganado.
|
| Cada indice lleva su nombre corto —el que usa quien esta certificado— y una
| linea que dice **que significa el numero que salio**, no que formula lo
| produjo. Un tablero que dice <<CPI 0.82>> y nada mas obliga a preguntarle a
| alguien, y ese alguien es el que ya sabia la respuesta.
*/

return [

    'title' => 'Valor ganado',
    'intro' => 'Las tres cifras de las que sale todo: cuanto deberia estar ganado hoy, cuanto se gano de verdad y cuanto se gasto. Es la unica forma de distinguir ir caro de ir tarde, que en un tablero de avance se ven igual.',
    'download' => 'Descargar en PDF',
    'status_date' => 'Fecha de corte',
    'status_date_help' => 'Los indices de hoy no explican una junta de hace tres semanas. Mueve la fecha para ver que se sabia entonces.',
    'recalculate' => 'Recalcular',
    'as_of' => 'Al :date',

    // --- Las tres cifras -------------------------------------------------
    'pv' => 'Valor planeado',
    'pv_short' => 'VP',
    'pv_help' => 'Cuanto del presupuesto deberia estar ganado a la fecha de corte, segun la linea base.',
    'ev' => 'Valor ganado',
    'ev_short' => 'VG',
    'ev_help' => 'Cuanto se gano de verdad: el presupuesto de cada tarea por su avance capturado.',
    'ac' => 'Costo real',
    'ac_short' => 'CR',
    'ac_help' => 'Cuanto se gasto de verdad. Se captura tarea por tarea; no se deduce del avance.',
    'bac' => 'Presupuesto total',
    'bac_short' => 'PT',
    'bac_help' => 'Lo que se comprometio en la linea base, mas lo que se agrego al plan despues.',

    // --- Las varianzas ---------------------------------------------------
    'cv' => 'Varianza de costo',
    'cv_short' => 'VC',
    'cv_help' => 'Valor ganado menos costo real. Negativo es sobrecosto.',
    'sv' => 'Varianza de cronograma',
    'sv_short' => 'VC-t',
    'sv_help' => 'Valor ganado menos valor planeado, en dinero. Negativo es atraso.',

    // --- Los indices -----------------------------------------------------
    'cpi' => 'Indice de costo',
    'cpi_short' => 'CPI',
    'cpi_help' => 'Cuanto vale lo hecho por cada peso gastado. Abajo de 1.00 es caro.',
    'spi' => 'Indice de cronograma',
    'spi_short' => 'SPI',
    'spi_help' => 'Cuanto se avanzo por cada peso que se debio avanzar. Abajo de 1.00 es tarde.',

    // --- Los pronosticos -------------------------------------------------
    'forecast' => 'Pronostico',
    'eac' => 'Costo final estimado',
    'eac_short' => 'EAC',
    'eac_help' => 'En cuanto acaba el proyecto si lo que ha pasado sigue pasando.',
    'etc' => 'Falta por gastar',
    'etc_short' => 'ETC',
    'etc_help' => 'Lo que queda por gastar de aqui al cierre, segun el pronostico.',
    'vac' => 'Desviacion final',
    'vac_short' => 'VAC',
    'vac_help' => 'Cuanto se va a pasar del presupuesto. Negativo es pasarse.',
    'tcpi' => 'Eficiencia necesaria',
    'tcpi_short' => 'TCPI',
    'tcpi_help' => 'A que eficiencia habria que trabajar de aqui en adelante para todavia caber en el presupuesto.',

    // --- Las lecturas en palabras ----------------------------------------
    'reading' => 'Como se lee',
    'cost_ok' => 'El costo va bajo control: lo hecho vale mas de lo que costo.',
    'cost_tight' => 'El costo va justo.',
    'cost_over' => 'Se esta gastando mas de lo que vale lo hecho.',
    'schedule_ok' => 'El avance va adelantado respecto al plan.',
    'schedule_tight' => 'El avance va a la par del plan.',
    'schedule_late' => 'El avance va atrasado respecto al plan.',
    'forecast_over' => 'Al ritmo de hoy el proyecto termina :amount por arriba del presupuesto.',
    'forecast_under' => 'Al ritmo de hoy el proyecto termina :amount por debajo del presupuesto.',
    'tcpi_hard' => 'Para caber en el presupuesto habria que hacer lo que falta a :factor de eficiencia. Cuando ese numero pasa de 1.10 conviene renegociar, no prometer.',

    // --- Lo que falta para poder calcular --------------------------------
    'no_baseline' => 'Este proyecto no tiene linea base. El valor planeado se esta calculando contra el plan de hoy, que se ajusta solo: la varianza de cronograma saldra casi siempre en cero. Captura una linea base para que la comparacion signifique algo.',
    'baseline_used' => 'Medido contra la linea base «:name».',
    'no_actuals_title' => 'Falta el costo real',
    'no_actuals' => 'De :started tarea(s) ya arrancadas, :missing no tiene(n) costo real capturado. Los indices que dependen del costo —CPI, EAC, ETC y la desviacion final— no se calculan hasta que esten completos: con la mitad del gasto capturado saldrian espectaculares por la sencilla razon de que falta la otra mitad.',
    'no_actuals_where' => 'Se captura en cada tarea, junto al avance.',
    'nothing_started' => 'Todavia no arranca ninguna tarea, asi que no hay nada que medir.',
    'not_available' => 'No se puede calcular',

    // --- La tabla por tarea ----------------------------------------------
    'by_task' => 'Renglon por renglon',
    'task' => 'Tarea',
    'budget' => 'Presupuesto',
    'progress' => 'Avance',
    'actual_cost' => 'Costo real',
    'actual_cost_help' => 'Lo que de verdad costo. Dejalo vacio mientras no lo sepas: un cero se lee como que salio gratis.',

];
