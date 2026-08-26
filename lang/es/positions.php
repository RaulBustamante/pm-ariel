<?php

declare(strict_types=1);

return [

    'title' => 'Puestos',
    'intro' => 'Los puestos de la organizacion. El nivel ordena de mayor a menor jerarquia y sirve para presentar la lista y para saber a quien escalar algo. No otorga permisos: eso lo hacen los roles.',
    'create' => 'Nuevo puesto',
    'edit' => 'Editar puesto',
    'name' => 'Nombre del puesto',
    'name_help' => 'Como se le llama en tu organización. Por ejemplo: Gerencia de Sistemas.',
    'level' => 'Nivel',
    'level_help' => '1 es lo mas alto de la organizacion. Solo ordena; no da acceso a nada.',
    'people' => 'Personas',
    'created' => 'Puesto creado.',
    'updated' => 'Puesto actualizado.',
    'deleted' => 'Puesto borrado.',
    'in_use' => 'No se puede borrar: :count persona(s) tienen este puesto. Muevelas primero.',
    'confirm_delete' => 'Borrar este puesto?',
    'empty_title' => 'Todavia no hay puestos',
    'empty_body' => 'El alta de usuarios pide un puesto y la lista sale vacia hasta que se cree el primero. Puedes cargar un catalogo de arranque con: artisan db:seed --class=PositionsSeeder',
];
