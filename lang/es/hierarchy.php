<?php

declare(strict_types=1);

return [

    'title' => 'Jerarquía',
    'heading' => 'Quién reporta a quién',
    'intro' => 'Esto no es solo el organigrama: un jefe ve todos los proyectos de su cadena hacia abajo. Cambiar un jefe cambia lo que esa persona alcanza a ver.',

    'person' => 'Persona',
    'manager' => 'Jefe directo',
    'no_manager' => 'Sin jefe (reporta a nadie)',
    'change' => 'Cambiar jefe',
    'assign_heading' => 'Jefe directo de :name',

    'current' => 'Jefe actual',
    'none' => '—',

    'roots' => 'Sin jefe asignado',
    'roots_help' => 'Normalmente es una sola persona: la cabeza de la organización. Si aquí aparecen varias, revisa si falta asignar alguna.',

    'updated' => 'Se actualizó el jefe de :name.',
    'would_create_cycle' => 'Ese cambio dejaría a la persona por encima de su propio jefe. Revisa la cadena antes de moverla.',
    'cannot_manage_self' => 'Nadie puede ser su propio jefe.',

    'history_note' => 'El cambio cierra la relación anterior con la fecha de hoy; no la borra. El histórico es lo que permite explicar después por qué alguien veía cierto proyecto.',

];
