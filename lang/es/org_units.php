<?php

declare(strict_types=1);

return [

    'title' => 'Áreas',
    'heading' => 'Áreas de la organización',
    'intro' => 'El árbol de áreas define a qué parte de la organización pertenece cada persona. Se usa para agrupar proyectos y reportes.',

    'new' => 'Nueva área',
    'edit' => 'Editar área',

    'name' => 'Nombre',
    'code' => 'Clave',
    'code_help' => 'Opcional, corta y única. Sirve para reportes y para exportar.',
    'parent' => 'Depende de',
    'no_parent' => 'Ninguna (es de primer nivel)',
    'sort_order' => 'Orden',
    'sort_order_help' => 'Entre áreas hermanas, de menor a mayor. Empatadas, se ordenan por nombre.',
    'people' => 'Personas',
    'level' => 'Nivel',

    'created' => 'Área creada.',
    'updated' => 'Área actualizada.',
    'deleted' => 'Área eliminada.',

    'cannot_be_its_own_parent' => 'Un área no puede depender de sí misma.',
    'cannot_move_under_descendant' => 'No se puede colgar un área de una que ya está debajo de ella: las dos quedarían fuera del árbol.',
    'has_users' => 'Esta área tiene personas asignadas. Muévelas a otra área antes de eliminarla.',
    'has_children' => 'Esta área tiene áreas debajo. Muévelas o elimínalas primero.',

    'empty' => 'Todavía no hay áreas. Empieza creando la de primer nivel, normalmente la dirección o la empresa.',

    'confirm_delete' => '¿Eliminar esta área?',

];
