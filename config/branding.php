<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Marca
|--------------------------------------------------------------------------
|
| El nombre del producto vive aquí y en ningún otro lado: ni en clases, ni en
| tablas, ni en rutas, ni en claves de traducción, ni en la interfaz.
|
| Cambiar el nombre del producto debe costar editar este archivo. El comando
| `php artisan branding:verify` falla si la marca se filtró al código.
|
*/

return [

    'name' => env('BRANDING_NAME', env('APP_NAME', 'Tesseract Flow')),

    'short_name' => env('BRANDING_SHORT_NAME', 'Tesseract Flow'),

    'tagline' => env('BRANDING_TAGLINE', 'Gestión de proyectos · PMI'),

    // El logotipo **horizontal** de la entrada, mark y texto juntos. Se espera
    // una relacion de al menos 2:1 y sin franjas muertas: un archivo casi
    // cuadrado deja el logotipo nadando en su propio fondo, que es como se veia
    // antes de recortarlo. `BrandingTest` fija esa forma.
    'logo' => env('BRANDING_LOGO', 'images/tesseract-flow-logo.png'),

    'mark' => env('BRANDING_MARK', 'images/tesseract-flow-mark.png'),

    'colors' => [
        'primary' => env('BRANDING_COLOR_PRIMARY', '#1d4ed8'),
        'accent' => env('BRANDING_COLOR_ACCENT', '#0f766e'),
    ],

    'footer' => env('BRANDING_FOOTER', ''),

    'contact_email' => env('BRANDING_CONTACT_EMAIL', 'no-reply@localhost'),

    /*
    | Cuenta del primer administrador que crea el seeder.
    */

    'admin_email' => env('BRANDING_ADMIN_EMAIL', 'admin@localhost'),

    /*
    |--------------------------------------------------------------------------
    | Términos vigilados
    |--------------------------------------------------------------------------
    |
    | `branding:verify` falla si alguno aparece en el código fuente. Es lo único
    | que sostiene la regla: sin verificación automática se erosiona en meses.
    |
    */

    'forbidden_terms' => ['pm-ariel', 'pmariel', 'PmAriel'],

];
