<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Los juegos de secciones de los documentos narrativos
    |--------------------------------------------------------------------------
    |
    | Veinticinco documentos que se redactan, y **nueve estructuras**. No es un
    | atajo: los trece planes subsidiarios de gestion del PMI tienen de verdad la
    | misma forma —como se aborda, quien responde, que proceso se sigue, con que
    | herramientas, con que umbrales, y como se informa—. Lo que cambia entre el
    | plan de alcance y el de costos es de que se habla, no la estructura.
    |
    | Escribir veinticinco plantillas distintas para nueve formas repetiria el
    | mismo trabajo dieciseis veces y garantizaria que se desincronicen: alguien
    | mejora la seccion de umbrales en uno y los otros doce se quedan atras.
    |
    | Cada seccion trae:
    |   `required`  si su ausencia se senala en el tablero. Nada impide guardar
    |               sin ella: se marca lo que falta, no se bloquea. Obligar a
    |               llenar todo de golpe solo consigue que la gente invente texto
    |               para poder avanzar.
    |   `rows`      alto sugerido del campo, para que el formulario insinue si se
    |               espera una linea o tres parrafos.
    |
    | Los titulos y las ayudas **no viven aqui**: estan en los archivos `sections.php` de cada idioma.
    |
    */

    'sets' => [

        /*
        | El plan de gestion. Sirve para los trece: alcance, requisitos,
        | cronograma, costos, calidad, recursos, comunicaciones, riesgos,
        | adquisiciones, interesados, cambios, configuracion y el plan para la
        | direccion del proyecto.
        */
        'management_plan' => [
            'approach' => ['required' => true, 'rows' => 4],
            'roles' => ['required' => true, 'rows' => 3],
            'process' => ['required' => true, 'rows' => 5],
            'tools' => ['required' => false, 'rows' => 3],
            'thresholds' => ['required' => false, 'rows' => 3],
            'reporting' => ['required' => false, 'rows' => 3],
        ],

        'business_case' => [
            'situation' => ['required' => true, 'rows' => 4],
            'options' => ['required' => true, 'rows' => 5],
            'recommendation' => ['required' => true, 'rows' => 3],
            'benefits' => ['required' => true, 'rows' => 4],
            'costs' => ['required' => false, 'rows' => 3],
            'risks' => ['required' => false, 'rows' => 3],
        ],

        'benefits_plan' => [
            'benefits' => ['required' => true, 'rows' => 4],
            'metrics' => ['required' => true, 'rows' => 4],
            'owner' => ['required' => true, 'rows' => 2],
            'timeline' => ['required' => false, 'rows' => 3],
            'sustainment' => ['required' => false, 'rows' => 3],
        ],

        'requirements' => [
            'sources' => ['required' => true, 'rows' => 3],
            'categories' => ['required' => true, 'rows' => 4],
            'prioritisation' => ['required' => false, 'rows' => 3],
            'acceptance' => ['required' => true, 'rows' => 4],
        ],

        'scope_statement' => [
            'description' => ['required' => true, 'rows' => 5],
            'deliverables' => ['required' => true, 'rows' => 5],
            'acceptance' => ['required' => true, 'rows' => 4],
            'exclusions' => ['required' => true, 'rows' => 3],
            'constraints' => ['required' => false, 'rows' => 3],
            'assumptions' => ['required' => false, 'rows' => 3],
        ],

        'team_charter' => [
            'values' => ['required' => true, 'rows' => 3],
            'communication' => ['required' => true, 'rows' => 3],
            'decisions' => ['required' => true, 'rows' => 3],
            'meetings' => ['required' => false, 'rows' => 3],
            'conflict' => ['required' => false, 'rows' => 3],
        ],

        'procurement' => [
            'what' => ['required' => true, 'rows' => 4],
            'criteria' => ['required' => true, 'rows' => 4],
            'contract' => ['required' => false, 'rows' => 3],
            'evaluation' => ['required' => false, 'rows' => 3],
        ],

        'quality_report' => [
            'findings' => ['required' => true, 'rows' => 5],
            'measurements' => ['required' => false, 'rows' => 4],
            'corrections' => ['required' => true, 'rows' => 4],
            'recommendations' => ['required' => false, 'rows' => 3],
        ],

        'closure' => [
            'result' => ['required' => true, 'rows' => 5],
            'acceptance' => ['required' => true, 'rows' => 3],
            'lessons' => ['required' => true, 'rows' => 5],
            'handover' => ['required' => true, 'rows' => 4],
            'pending' => ['required' => false, 'rows' => 3],
        ],
    ],

];
