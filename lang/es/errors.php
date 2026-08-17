<?php

declare(strict_types=1);

/**
 * Mensajes de error escritos para quien no es programador.
 *
 * Cada uno responde tres cosas: que paso, por que, y que hacer ahora. Un error
 * que solo dice «403 Forbidden» obliga a preguntarle a alguien, y quien tiene
 * que preguntar por cada tropiezo deja de usar el sistema.
 */
return [

    'go_back' => 'Regresar',
    'go_home' => 'Ir al inicio',

    '403_title' => 'No tienes acceso a esto',
    '403_message' => 'Tu cuenta existe y está activa, pero este proyecto o esta pantalla no están dentro de lo que puedes ver. No es un error del sistema.',
    '403_action' => 'Si crees que sí deberías tener acceso, pídele a quien administra el proyecto que te agregue como miembro. Ser jefe de alguien del equipo da lectura, no edición.',

    '404_title' => 'Esto ya no está aquí',
    '404_message' => 'La dirección es válida pero no encontré nada en ella. Puede que se haya borrado, o que el enlace apunte a otro proyecto.',
    '404_action' => 'Revisa que el enlace esté completo. Si venías de un correo viejo, es probable que eso ya haya cambiado.',

    '419_title' => 'La página estuvo abierta demasiado tiempo',
    '419_message' => 'Por seguridad, los formularios caducan después de un rato sin usarse. Lo que escribiste no se guardó.',
    '419_action' => 'Regresa, vuelve a abrir la pantalla y captúralo de nuevo. Si te pasa seguido, avísalo: puede ajustarse.',

    '429_title' => 'Demasiados intentos seguidos',
    '429_message' => 'El sistema frena los intentos repetidos para proteger las cuentas. No es que algo esté roto.',
    '429_action' => 'Espera un minuto y vuelve a intentar. Si estás recuperando tu contraseña, revisa antes tu correo.',

    '500_title' => 'Algo falló de nuestro lado',
    '500_message' => 'No fue algo que hicieras mal. El error quedó registrado con la hora exacta.',
    '500_action' => 'Vuelve a intentarlo. Si sigue pasando, anota qué estabas haciendo y a qué hora — con eso se puede rastrear en la bitácora.',

    '503_title' => 'El sistema está en mantenimiento',
    '503_message' => 'Se está aplicando una actualización. Es temporal y planeado.',
    '503_action' => 'Vuelve a intentar en unos minutos. Nada de lo que ya habías guardado se pierde.',

];
