<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Las zonas horarias que se ofrecen
    |--------------------------------------------------------------------------
    |
    | Una lista corta y no las 400+ que trae PHP.
    |
    | Antes era un campo de texto libre: había que escribir `America/Mexico_City`
    | de memoria, con el guion bajo y la mayúscula correctos, o la validación lo
    | rechazaba sin decir cómo se escribe bien. Nadie sabe eso, y el que lo sabe
    | no debería tener que teclearlo.
    |
    | Ofrecer las 400 tampoco sirve: buscar `Bahia_Banderas` entre `Africa/Abidjan`
    | y `Pacific/Wallis` es peor que escribirla. Aquí están **las cuatro de
    | México** —que son las que se usan— y las de los países con los que Ariel
    | opera, que es lo que hace falta para una filial o un proveedor.
    |
    | Agregar una es una línea. El texto visible sale del propio identificador y
    | del desfase que PHP calcula, así que no hay que traducir nada.
    |
    */

    'offered' => [

        // México. `Mexico_City` cubre la mayor parte del país; las otras tres
        // existen porque Baja California, Sonora y Quintana Roo tienen horario
        // propio y una junta agendada mal ahí se nota de inmediato.
        'America/Mexico_City',
        'America/Tijuana',
        'America/Hermosillo',
        'America/Cancun',

        // Estados Unidos y Canadá: proveedores y matriz.
        'America/Los_Angeles',
        'America/Denver',
        'America/Chicago',
        'America/New_York',
        'America/Toronto',

        // Resto de América.
        'America/Bogota',
        'America/Lima',
        'America/Santiago',
        'America/Sao_Paulo',
        'America/Buenos_Aires',

        // Europa y Asia: proveeduría e importaciones.
        'Europe/Madrid',
        'Europe/London',
        'Europe/Berlin',
        'Asia/Shanghai',
        'Asia/Hong_Kong',
        'Asia/Tokyo',
        'Asia/Kolkata',

        'UTC',
    ],

];
