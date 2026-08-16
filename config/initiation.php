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
