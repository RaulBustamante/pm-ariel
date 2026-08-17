<?php

declare(strict_types=1);

return [

    // Mensaje único para credenciales incorrectas, cuenta inexistente y cuenta
    // desactivada: distinguirlos le confirma a un atacante qué correos existen.
    'failed' => 'No pudimos iniciar sesión con esos datos. Revisa el correo y la contraseña.',
    'password' => 'La contraseña no es correcta.',
    'throttle' => 'Demasiados intentos. Vuelve a intentarlo en :seconds segundos.',

    // Aquí sí se puede ser explícito: quien lee esto ya había entrado, así que
    // el mensaje no le confirma a nadie la existencia de una cuenta ajena.
    'inactive' => 'Tu cuenta fue desactivada. Habla con el administrador si crees que es un error.',

    'sign_in' => 'Entrar',
    'sign_out' => 'Salir',
    'email' => 'Correo electrónico',
    'password_label' => 'Contraseña',
    'remember_me' => 'Mantener la sesión abierta',
    'forgot_password' => '¿Olvidaste tu contraseña?',

    'change_password_title' => 'Cambia tu contraseña',
    'change_password_intro' => 'La contraseña que te dio el administrador es temporal. Mientras siga vigente, dos personas conocen tu cuenta.',
    'current_password' => 'Contraseña actual',
    'new_password' => 'Contraseña nueva',
    'confirm_password' => 'Confirma la contraseña nueva',
    'password_rules' => 'Al menos 10 caracteres, con letras y números.',
    'password_updated' => 'Tu contraseña quedó actualizada.',
    'password_must_differ' => 'La contraseña nueva debe ser distinta de la actual.',

    'reset_title' => 'Recuperar acceso',
    'reset_intro' => 'Escribe tu correo y te enviamos un enlace para crear una contraseña nueva.',
    'reset_send_link' => 'Enviar enlace',
    'reset_new_title' => 'Crea tu contraseña nueva',
    'reset_submit' => 'Guardar contraseña',
    'back_to_login' => 'Volver al inicio de sesión',

];
