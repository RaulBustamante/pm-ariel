<?php

declare(strict_types=1);

return [

    'title' => 'Importar desde hoja de cálculo',
    'intro' => 'Sube un archivo CSV con tus tareas. Antes de guardar nada, verás exactamente qué se va a crear.',

    'file' => 'Archivo CSV',
    'see_preview' => 'Ver qué se importaría',

    'replace' => 'Reemplazar el plan actual',
    'replace_help' => 'Sin marcar, las tareas del archivo se agregan a las que ya existen.',
    'will_replace' => 'Se borrará el plan actual',

    'preview_title' => 'Se importarían :count tareas',
    'confirm' => 'Importar las :count tareas',

    'problems_found' => 'Encontré problemas en el archivo',
    'problems_help' => 'Los renglones con problema se saltan; los demás sí se importan. Corrige el archivo si necesitas todos.',

    'needs_header_and_rows' => 'El archivo necesita una fila de encabezados y al menos una tarea.',
    'no_name_column' => 'No encontré una columna de nombre. Debe llamarse Nombre, Tarea, Name o Task.',
    'no_rows' => 'El archivo no trae ninguna tarea.',
    'row_without_name' => 'Renglón :row: sin nombre, se salta.',
    'bad_duration' => 'Renglón :row: :reason',
    'nothing_to_import' => 'No quedó nada que importar.',

    'done' => 'Se importaron :count tareas y se recalculó el plan.',

    'format_title' => 'Cómo debe verse el archivo',
    'format_help' => 'La primera fila son los encabezados. La columna Nivel arma la jerarquía: 0 es primer nivel, 1 cuelga del último renglón de nivel 0.',
    'separator_help' => 'Funciona con coma o con punto y coma — Excel en español exporta con punto y coma y eso está bien.',

];
