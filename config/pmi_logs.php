<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Los catorce registros que crecen durante el proyecto
    |--------------------------------------------------------------------------
    |
    | Incidencias, decisiones, cambios, lecciones, minutas, acciones, supuestos,
    | mediciones de calidad, registros de prueba. **Los catorce tienen la misma
    | forma** --una fecha, un numero que se cita, que paso, quien responde y en
    | que estado esta-- asi que son una sola tabla con un tipo, no catorce tablas
    | con catorce pantallas.
    |
    | Lo que de verdad cambia entre uno y otro son tres cosas, y las tres viven
    | aqui:
    |
    |   `prefix`    El codigo que la gente cita en una junta: INC-004, DEC-011.
    |               Se guarda el numero, no el texto, y el prefijo se pega al
    |               mostrarlo: asi corregir un prefijo no obliga a reescribir la
    |               base.
    |
    |               **Nunca se traduce.** Un mismo asunto no puede llamarse
    |               INC-004 en una pantalla y ISS-004 en la otra: quien lo cite
    |               en un correo dejaria de encontrarlo.
    |
    |   `statuses`  El juego de estados. Una solicitud de cambio se aprueba o se
    |               rechaza; una leccion no. Darle a los catorce el mismo
    |               <<abierto / cerrado>> obligaria a traducir mentalmente el
    |               estado real al unico que ofrece la pantalla, y ahi es donde
    |               un registro deja de leerse.
    |
    |   `fields`    Que columnas opcionales aplican. Una minuta no tiene fecha
    |               compromiso ni prioridad, y ensenarlas invita a llenar la que
    |               no corresponde.
    |
    | Los nombres visibles **no viven aqui**: estan en los archivos `logs.php` de
    | cada idioma, como todo el texto del sistema (D-004).
    |
    */

    /*
    | Los juegos de estados. Once para catorce registros, por la misma razon por
    | la que hay nueve juegos de secciones para veinticinco documentos: los que
    | comparten forma comparten definicion, y los que no, no se fuerzan.
    |
    | `closed` dice cuales significan <<ya no hay nada que hacer>>. Es lo unico
    | que permite contar pendientes sin que cada pantalla decida por su cuenta
    | que es estar abierto.
    */
    'statuses' => [

        'resolution' => [
            'values' => ['open', 'in_progress', 'resolved', 'closed'],
            'closed' => ['resolved', 'closed'],
        ],

        'change' => [
            'values' => ['requested', 'under_review', 'approved', 'rejected', 'implemented'],
            'closed' => ['rejected', 'implemented'],
        ],

        'implementation' => [
            'values' => ['approved', 'in_progress', 'implemented', 'verified'],
            'closed' => ['verified'],
        ],

        'decision' => [
            'values' => ['proposed', 'decided', 'superseded'],
            'closed' => ['decided', 'superseded'],
        ],

        'assumption' => [
            'values' => ['assumed', 'validated', 'invalidated'],
            'closed' => ['validated', 'invalidated'],
        ],

        'lesson' => [
            'values' => ['captured', 'applied', 'shared'],
            'closed' => ['applied', 'shared'],
        ],

        'minutes' => [
            'values' => ['draft', 'issued'],
            'closed' => ['issued'],
        ],

        'delivery' => [
            'values' => ['drafted', 'sent', 'acknowledged'],
            'closed' => ['acknowledged'],
        ],

        'verdict' => [
            'values' => ['passed', 'failed', 'retest'],
            'closed' => ['passed'],
        ],

        'update' => [
            'values' => ['identified', 'updated', 'closed'],
            'closed' => ['closed'],
        ],

        'measurement' => [
            'values' => ['within_tolerance', 'out_of_tolerance', 'corrected'],
            'closed' => ['within_tolerance', 'corrected'],
        ],

    ],

    /*
    | Los catorce tipos. Cada renglon vale por una pantalla que no hubo que
    | construir; agregar el decimoquinto cuesta cuatro lineas y dos textos.
    */
    'types' => [

        // --- Inicio ---------------------------------------------------------
        'assumption_log' => [
            'prefix' => 'ASM',
            'statuses' => 'assumption',
            'fields' => ['owner', 'outcome'],
        ],

        // --- Ejecucion ------------------------------------------------------
        'project_communications' => [
            'prefix' => 'COM',
            'statuses' => 'delivery',
            'fields' => ['owner'],
        ],

        'issue_log' => [
            'prefix' => 'INC',
            'statuses' => 'resolution',
            'fields' => ['owner', 'due', 'priority', 'outcome'],
        ],

        'change_requests' => [
            'prefix' => 'SC',
            'statuses' => 'change',
            'fields' => ['owner', 'priority', 'outcome'],
        ],

        'decision_log' => [
            'prefix' => 'DEC',
            'statuses' => 'decision',
            'fields' => ['owner', 'outcome'],
        ],

        'test_inspection_records' => [
            'prefix' => 'PRU',
            'statuses' => 'verdict',
            'fields' => ['owner', 'outcome'],
        ],

        'lessons_learned_register' => [
            'prefix' => 'LEC',
            'statuses' => 'lesson',
            'fields' => ['owner', 'outcome'],
        ],

        'meeting_minutes' => [
            'prefix' => 'MIN',
            'statuses' => 'minutes',
            'fields' => ['owner'],
        ],

        'action_item_log' => [
            'prefix' => 'ACC',
            'statuses' => 'resolution',
            'fields' => ['owner', 'due', 'priority', 'outcome'],
        ],

        // --- Monitoreo y control --------------------------------------------
        'change_log' => [
            'prefix' => 'CAM',
            'statuses' => 'change',
            'fields' => ['owner', 'outcome'],
        ],

        'approved_change_requests' => [
            'prefix' => 'SCA',
            'statuses' => 'implementation',
            'fields' => ['owner', 'due', 'outcome'],
        ],

        'risk_updates' => [
            'prefix' => 'ARI',
            'statuses' => 'update',
            'fields' => ['owner', 'outcome'],
        ],

        'issue_updates' => [
            'prefix' => 'AIN',
            'statuses' => 'update',
            'fields' => ['owner', 'outcome'],
        ],

        'quality_control_measurements' => [
            'prefix' => 'MED',
            'statuses' => 'measurement',
            'fields' => ['owner', 'outcome'],
        ],

    ],

    /*
    | Las prioridades. Solo aplican donde `fields` las pide: ponerle prioridad a
    | una minuta no significa nada.
    */
    'priorities' => ['low', 'medium', 'high', 'critical'],

];
