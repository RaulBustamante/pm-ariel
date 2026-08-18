<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Las actas: la cuarta y ultima especie del catalogo
    |--------------------------------------------------------------------------
    |
    | Son dos --la aceptacion de un entregable y la del proyecto entero-- y
    | tienen la misma forma: alguien de fuera del equipo dice **si lo recibe**,
    | con nombre, fecha y reservas si las hay.
    |
    | Lo que las separa de un registro (`log`) no es la estructura sino lo que
    | pasa al final: **un acta se firma y se congela**. Un registro crece; un
    | acta cierra. Y toda la utilidad de un acta viene de que no se pueda editar
    | despues: un documento de aceptacion que se puede cambiar no prueba nada,
    | exactamente igual que una linea base (D-011) o una version emitida (7.1).
    |
    | Al firmar se **emite y archiva** el PDF con el mismo motor del bloque 7.1,
    | asi que el acta queda con su numero de version, su fecha y su huella. No es
    | una firma electronica --el sistema no la tiene y no finge tenerla--: es el
    | registro de que alguien con nombre y puesto acepto en una fecha, y de quien
    | lo asento. La pantalla lo dice con esas palabras.
    |
    | Los nombres visibles **no viven aqui**: estan en los archivos `records.php`
    | de cada idioma, como todo el texto del sistema (D-004).
    |
    */

    'types' => [

        'deliverable_acceptance_records' => [
            'prefix' => 'ACE',
            // Se aceptan entregables concretos, asi que el acta apunta a la
            // tarea que los produjo. Sin ese vinculo, «se acepta el modulo de
            // inventario» no se puede rastrear hasta el plan.
            'links_deliverable' => true,
        ],

        'acceptance_signoff' => [
            'prefix' => 'ACT',
            // El proyecto entero no es una tarea. Pedir que apunte a una
            // obligaria a escoger una arbitraria.
            'links_deliverable' => false,
        ],

    ],

    /*
    | Las tres respuestas posibles.
    |
    | **<<Aceptado con reservas>> existe a proposito.** Sin esa opcion, quien
    | recibe algo casi bueno solo puede aceptarlo --y las reservas se quedan en
    | un correo que nadie vuelve a abrir-- o rechazarlo entero, que casi nunca
    | es lo que quiere. Es la casilla que hace que el acta refleje lo que de
    | verdad paso en la junta.
    */
    'decisions' => ['accepted', 'accepted_with_reservations', 'rejected'],

];
