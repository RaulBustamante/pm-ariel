<?php

declare(strict_types=1);

/*
| Los requisitos y su matriz de trazabilidad.
|
| La matriz no existe para tener la tabla: existe para producir **dos numeros**
| --lo que se pidio y nadie construye, y lo que se construye sin que nadie lo
| haya pedido--. El segundo casi nunca se busca y suele ser el mas caro.
*/

return [

    'title' => 'Requisitos y trazabilidad',
    'intro' => 'La documentacion de requisitos explica el alcance a una persona. Esto contesta otra pregunta: que tarea entrega cada requisito, y de donde salio cada trabajo. Para eso hacen falta requisitos con clave propia, no un parrafo.',
    'matrix' => 'Matriz de trazabilidad',
    'add' => 'Agregar requisito',
    'empty' => 'Todavia no hay requisitos capturados.',
    'saved' => 'Requisito guardado.',
    'deleted' => 'Requisito eliminado.',

    'reference' => 'Clave',
    'description' => 'Que se pidio',
    'description_help' => 'Una linea que se pueda comprobar. <<Que sea rapido>> no es un requisito; <<el cierre mensual se hace en un dia>> si.',
    'origin' => 'De quien salio',
    'origin_help' => 'Un interesado, una norma, el acta. Un requisito sin origen no se puede negociar cuando hay que recortar alcance, porque nadie sabe a quien habria que convencer.',
    'priority' => 'Prioridad',
    'priority_must' => 'Indispensable',
    'priority_should' => 'Importante',
    'priority_could' => 'Deseable',
    'delivered_by' => 'Lo entrega',
    'delivered_by_help' => 'La tarea del plan que lo cumple. Dejalo vacio si todavia no hay ninguna: ese hueco es justo lo que esta pantalla existe para encontrar.',
    'nobody' => 'Nadie lo entrega',
    'nobody_yet' => 'Todavia nadie',
    'acceptance' => 'Como se comprueba',
    'acceptance_help' => 'Sin esto, <<entregado>> es la opinion de quien entrego.',

    'status_proposed' => 'Propuesto',
    'status_approved' => 'Aprobado',
    'status_delivered' => 'Entregado',
    'status_verified' => 'Verificado',
    'status_dropped' => 'Descartado',

    'orphans' => 'Se pidio y nadie lo construye',
    'orphans_help' => 'Requisitos sin tarea que los entregue. Es el hallazgo mas caro de descubrir tarde: se acuerda algo, nadie lo baja al plan, y aparece el dia de la entrega.',
    'unrequested' => 'Se construye y nadie lo pidio',
    'unrequested_help' => 'Tareas que no cumplen ningun requisito capturado. Casi nunca se busca, y suele salir mas caro que lo anterior: es trabajo que se esta pagando sin que nadie lo haya pedido.',

];
