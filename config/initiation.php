<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Sugerencias asistidas por IA
    |--------------------------------------------------------------------------
    |
    | Apagado, el recorrido funciona igual con plantillas y reglas (D-016). Es el
    | modo de referencia: nada depende de que haya internet ni credenciales.
    |
    | Encendido (D-018), aparecen botones de "sugerir" que redactan borradores a
    | partir de lo que el usuario ya escribió. Nunca se llama solo: siempre lo
    | dispara una acción explícita, para que nadie mande información de un
    | proyecto de Ariel a un tercero sin querer.
    |
    */

    'ai' => [
        'enabled' => env('INITIATION_AI_ENABLED', false),

        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
        'endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1/chat/completions'),

        // Corto a propósito: el usuario está esperando frente a un formulario.
        // Si tarda más, la plantilla responde y el recorrido no se detiene.
        'timeout_seconds' => (int) env('INITIATION_AI_TIMEOUT', 25),

        'max_suggestions' => 8,

        /*
        | Techo de gasto.
        |
        | Cada botón de "sugerir" es una llamada facturada a una tarjeta que hoy
        | es personal (D-018). Sin un tope, alguien que oprime el botón veinte
        | veces porque no le gustó el borrador, o una pestaña que se recarga
        | sola, gastan sin que nadie se entere hasta que llegue el estado de
        | cuenta. El límite es por usuario, no global: que una persona agote su
        | cuota no debe dejar sin asistente al resto.
        |
        | Al toparse responde la plantilla —igual que cuando no hay internet—,
        | así que el recorrido no se detiene, solo deja de costar.
        */
        'rate_limit' => [
            // Diez y no cinco porque crear un proyecto ya gasta tres —el
            // recorrido precarga entregables, interesados y riesgos—, y quedaría
            // muy poco margen para el primer minuto de trabajo real.
            'per_minute' => (int) env('INITIATION_AI_PER_MINUTE', 10),
            'per_day' => (int) env('INITIATION_AI_PER_DAY', 100),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Qué sale de la red de Ariel
    |--------------------------------------------------------------------------
    |
    | Lista blanca explícita. Solo estos campos se incluyen en la petición; todo
    | lo demás se queda dentro, aunque exista en la base. Los datos de contacto
    | de los interesados —correo y teléfono— nunca salen: no aportan nada a una
    | sugerencia y son justo lo que no conviene mandar afuera.
    |
    */

    'ai_shared_fields' => [
        'project.name',
        'project.description',
        'template.name',
        'charter.problem_statement',
        'charter.opportunity',
        'charter.expected_benefit',
        'charter.objectives',
    ],

];
