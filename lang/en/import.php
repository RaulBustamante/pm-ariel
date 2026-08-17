<?php

declare(strict_types=1);

return [

    'title' => 'Import from a spreadsheet',
    'intro' => 'Upload a CSV file with your tasks. Before anything is saved, you will see exactly what would be created.',

    'file' => 'CSV file',
    'see_preview' => 'See what would be imported',

    'replace' => 'Replace the current plan',
    'replace_help' => 'Unchecked, the tasks in the file are added to the ones already there.',
    'will_replace' => 'The current plan will be deleted',

    'preview_title' => ':count tasks would be imported',
    'confirm' => 'Import the :count tasks',

    'problems_found' => 'I found problems in the file',
    'problems_help' => 'Rows with problems are skipped; the rest are imported. Fix the file if you need all of them.',

    'needs_header_and_rows' => 'The file needs a header row and at least one task.',
    'no_name_column' => 'I could not find a name column. It should be called Nombre, Tarea, Name or Task.',
    'no_rows' => 'The file contains no tasks.',
    'row_without_name' => 'Row :row: no name, skipped.',
    'bad_duration' => 'Row :row: :reason',
    'nothing_to_import' => 'Nothing left to import.',

    'done' => ':count tasks imported and the plan recalculated.',

    'format_title' => 'What the file should look like',
    'format_help' => 'The first row is the headers. The Level column builds the hierarchy: 0 is top level, 1 nests under the last level-0 row.',
    'separator_help' => 'Works with commas or semicolons — Spanish Excel exports with semicolons and that is fine.',

];
