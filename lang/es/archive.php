<?php

declare(strict_types=1);

/*
| El expediente archivado.
|
| Es lo que convierte setenta documentos sueltos en algo que se entrega una vez.
| Lleva un indice legible ademas de los archivos: un ZIP con setenta nombres
| cripticos es un respaldo, no un expediente.
*/

return [

    'title' => 'Expediente del proyecto',
    'download' => 'Descargar el expediente',
    'help' => 'Todas las versiones emitidas del proyecto en un solo paquete, con un indice que se abre en cualquier navegador. Lleva lo que **se emitio**, no lo que el sistema generaria hoy: un expediente que se regenera al abrirlo diria cosas distintas cada vez, y entonces no prueba nada.',
    'empty' => 'Todavia no hay ninguna version emitida que archivar. Emite documentos desde este mismo tablero.',
    'count' => ':count version(es) emitida(s) lista(s) para archivar.',
    'generated' => 'Expediente generado el :date',
    'index_note' => 'Cada renglon es una version emitida, tal como se emitio. Los archivos estan en las carpetas de este mismo paquete, agrupados por grupo de procesos del PMI.',
    'file' => 'Archivo',
    'file_missing' => 'El archivo ya no esta en el disco. El registro de la emision se conserva.',

];
