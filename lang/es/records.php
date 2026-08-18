<?php

declare(strict_types=1);

/*
| Las actas de aceptacion: la cuarta y ultima especie del catalogo.
|
| El texto que mas trabajo hace aqui es el que explica **que es y que no es la
| firma**. El sistema no tiene firma electronica y no finge tenerla: lo que
| queda registrado es que alguien con nombre y puesto acepto en una fecha, y
| quien lo asento. Un sello que promete mas de lo que vale es peor que no tener
| sello, y esa frase tiene que estar en la pantalla, no solo en el codigo.
*/

return [

    'open' => 'Levantar acta',
    'edit' => 'Corregir el borrador',
    'opened' => 'Acta :reference levantada como borrador.',
    'amended' => 'Se corrigio :reference.',
    'deleted' => 'Borrador eliminado. Su numero no se vuelve a usar.',
    'empty' => 'Todavia no hay actas de este tipo.',
    'download' => 'Descargar en PDF',

    'reference' => 'Numero',
    'subject' => 'Que se acepta',
    'subject_help' => 'Lo que se esta recibiendo, dicho como lo diria quien lo recibe.',
    'detail' => 'Que incluye',
    'detail_help' => 'El alcance de lo entregado. Lo que no se escribe aqui es lo que se discute despues.',
    'deliverable' => 'Entregable del plan',
    'deliverable_none' => 'Sin vincular a una tarea',
    'deliverable_help' => 'Vincularlo al plan es lo que permite rastrear «se acepto el modulo» hasta la tarea que lo produjo.',

    'decision' => 'Respuesta',
    'decision_accepted' => 'Aceptado',
    'decision_accepted_with_reservations' => 'Aceptado con reservas',
    'decision_rejected' => 'Rechazado',
    'decision_help' => '«Con reservas» existe a proposito: sin esa opcion, quien recibe algo casi bueno tiene que aceptarlo entero o rechazarlo entero, y ninguna de las dos es lo que paso en la junta.',
    'reservations' => 'Reservas y condiciones',
    'reservations_help' => 'Que falta o que se corrige, y para cuando. Es la parte del acta que se lee tres meses despues.',
    'reservations_required' => 'Si se acepta con reservas o se rechaza, hay que decir cuales. Un acta que afirma que hay condiciones y no dice ninguna se discute igual que si no existiera.',

    'accepted_by' => 'Quien acepta',
    'accepted_by_name' => 'Nombre',
    'accepted_by_name_help' => 'Texto libre a proposito: quien recibe casi siempre es alguien de fuera del equipo y no tiene cuenta en el sistema.',
    'accepted_by_role' => 'Puesto',
    'accepted_by_org' => 'Area o empresa',
    'accepted_on' => 'Fecha de aceptacion',

    // --- La firma --------------------------------------------------------
    'sign' => 'Firmar y archivar',
    'signed' => 'Acta :reference firmada y archivada.',
    'sign_help' => 'Al firmar, el acta queda **inmutable** y su PDF se archiva con numero de version, fecha y huella. Si despues cambia lo aceptado, se levanta otra acta y queda el rastro de que cambio.',
    'sign_confirm' => 'Firmar esta acta? Despues no se puede editar ni borrar.',
    'sign_disclaimer' => 'Esto no es una firma electronica: el sistema no la tiene y no finge tenerla. Lo que queda registrado es que la persona nombrada acepto en la fecha indicada, y quien lo asento en el sistema.',
    'already_signed' => 'Esta acta ya esta firmada.',
    'signed_cannot_be_deleted' => 'Un acta firmada no se borra: es el registro de lo que se acepto.',
    'signed_on' => 'Firmada el :date',
    'recorded_by' => 'Asentada por :who',
    'draft' => 'Borrador',
    'draft_warning' => 'Un acta sin firmar no vale: se puede editar y no esta archivada.',
    'checksum' => 'Huella',
    'not_signed_yet' => 'Sin firmar',

    // --- Las cifras de arriba --------------------------------------------
    'total' => 'Actas',
    'signed_count' => 'Firmadas',
    'draft_count' => 'Borradores',
    'rejected_count' => 'Rechazadas',

    // --- Que se levanta en cada tipo -------------------------------------
    'help_deliverable_acceptance_records' => 'Una por entregable recibido. Es la prueba de que alguien de fuera del equipo dijo que si lo recibia — y con que reservas. Sin ella, «ya se entrego» es la palabra de quien entrego contra la de quien recibio.',
    'help_acceptance_signoff' => 'El acta del proyecto entero: se entrego lo que se prometio y el patrocinador lo recibe. Es lo que cierra el proyecto formalmente; sin ella un proyecto no termina, solo deja de tener actividad.',

];
