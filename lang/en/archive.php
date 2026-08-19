<?php

declare(strict_types=1);

/*
| The archived project file.
|
| It is what turns seventy loose documents into something you hand over once. It
| carries a readable index alongside the files: a ZIP with seventy cryptic names
| is a backup, not a project file.
*/

return [

    'title' => 'Project file',
    'download' => 'Download the project file',
    'help' => 'Every issued version of the project in one package, with an index that opens in any browser. It carries **what was issued**, not what the system would generate today: a file that regenerates itself on opening would say something different every time, and then it proves nothing.',
    'empty' => 'No issued version to archive yet. Issue documents from this same board.',
    'count' => ':count issued version(s) ready to archive.',
    'generated' => 'File generated on :date',
    'index_note' => 'Each row is an issued version, exactly as it was issued. The files sit in the folders of this same package, grouped by PMI process group.',
    'file' => 'File',
    'file_missing' => 'The file is no longer on disk. The record of the issue is kept.',

];
