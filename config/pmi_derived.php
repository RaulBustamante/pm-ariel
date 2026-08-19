<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Los documentos que se generan solos
    |--------------------------------------------------------------------------
    |
    | La cuarta especie de D-022, y **la unica que nunca tuvo motor**. Las otras
    | tres se construyeron una vez y despues cada documento costo una definicion:
    | veinticinco narrativos, catorce registros, dos actas. Los derivados se
    | fueron resolviendo uno por uno, enrutando cada uno a la pantalla que ya
    | tenia sus datos --el cronograma al Gantt, los costos al analisis-- y por eso
    | los que **no** tenian pantalla se quedaron sin construir: no habia donde
    | ponerlos.
    |
    | Un documento derivado es siempre lo mismo: una consulta que devuelve
    | renglones y una tabla que los pinta. Lo que cambia entre el diccionario de
    | la EDT y el informe de riesgos es de donde salen los renglones y como se
    | llaman las columnas. Eso cabe aqui.
    |
    | Cada entrada trae:
    |
    |   `source`    el nombre del metodo que arma los renglones, en
    |               `App\Support\Documents\DerivedSources`. Agregar un documento
    |               es una linea aqui y un metodo alla.
    |
    |   `columns`   las columnas, en orden. La clave se usa para leer el renglon
    |               y para buscar su titulo en `derived.col_*` de cada idioma.
    |
    |   `numeric`   cuales van alineadas a la derecha y con cifras tabulares. Una
    |               columna de dinero alineada a la izquierda no se puede sumar
    |               con la vista, que es para lo que sirve una tabla de costos.
    |
    |   `landscape` si el PDF va girado. Mas de seis columnas no caben de pie.
    |
    | Los titulos visibles **no viven aqui**: estan en los archivos `derived.php`
    | de cada idioma (D-004).
    |
    */

    'documents' => [

        // --- Planeacion ------------------------------------------------------
        'wbs_dictionary' => [
            'source' => 'wbsDictionary',
            'columns' => ['wbs', 'name', 'detail', 'owner', 'duration', 'start', 'finish', 'cost'],
            'numeric' => ['duration', 'cost'],
            'landscape' => true,
        ],

        'activity_attributes' => [
            'source' => 'activityAttributes',
            'columns' => ['wbs', 'name', 'duration', 'predecessors', 'owner', 'constraint', 'float', 'critical'],
            'numeric' => ['duration', 'float'],
            'landscape' => true,
        ],

        'resource_breakdown_structure' => [
            'source' => 'resourceBreakdown',
            'columns' => ['kind', 'name', 'role', 'rate', 'capacity', 'supplier', 'origin'],
            'numeric' => ['rate', 'capacity'],
            'landscape' => false,
        ],

        'cost_baseline' => [
            'source' => 'costBaseline',
            'columns' => ['wbs', 'name', 'baseline_cost', 'current_cost', 'variance'],
            'numeric' => ['baseline_cost', 'current_cost', 'variance'],
            'landscape' => false,
        ],

        'risk_report' => [
            'source' => 'riskReport',
            'columns' => ['code', 'description', 'category', 'kind', 'probability', 'impact', 'level', 'status', 'owner'],
            'numeric' => ['probability', 'impact'],
            'landscape' => true,
        ],

        'stakeholder_engagement_plan' => [
            'source' => 'stakeholderEngagement',
            'columns' => ['name', 'organization', 'role', 'power', 'interest', 'quadrant', 'strategy', 'expectations'],
            'numeric' => ['power', 'interest'],
            'landscape' => true,
        ],

        // --- Monitoreo -------------------------------------------------------
        'schedule_forecasts' => [
            'source' => 'scheduleForecast',
            'columns' => ['measure', 'value', 'reading'],
            'numeric' => [],
            'landscape' => false,
        ],

        // --- Cierre ----------------------------------------------------------
        'lessons_learned_report' => [
            'source' => 'lessonsLearned',
            'columns' => ['reference', 'occurred_on', 'title', 'detail', 'outcome', 'status'],
            'numeric' => [],
            'landscape' => true,
        ],

        'final_project_report' => [
            'source' => 'finalReport',
            'columns' => ['measure', 'value', 'reading'],
            'numeric' => [],
            'landscape' => false,
        ],

    ],

];
