<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Backup destination
    |--------------------------------------------------------------------------
    |
    | Absolute path where backup archives are written. Point this at a drive
    | other than the one hosting the application: a backup that dies with its
    | disk is not a backup.
    |
    | The `?:` is deliberate. An empty entry in .env yields an empty string,
    | not null, so env()'s default would never apply and the path would be "".
    |
    */

    'destination' => env('BACKUP_DESTINATION') ?: storage_path('backups'),

    /*
    |--------------------------------------------------------------------------
    | Retention
    |--------------------------------------------------------------------------
    |
    | How many archives to keep. Older ones are pruned after a successful run,
    | never before: a failed run must not destroy the previous good backup.
    |
    */

    'keep' => (int) env('BACKUP_KEEP', 14),

    /*
    |--------------------------------------------------------------------------
    | What goes into an archive
    |--------------------------------------------------------------------------
    |
    | The repository is bundled with `git bundle --all`, which packs the full
    | history into a single file. Until a remote exists, that bundle is the
    | only off-machine copy of the source history.
    |
    */

    'include' => [
        'database' => (bool) env('BACKUP_INCLUDE_DATABASE', true),
        'files' => (bool) env('BACKUP_INCLUDE_FILES', true),
        'repository' => (bool) env('BACKUP_INCLUDE_REPOSITORY', true),
    ],

    /*
    | Directories copied under the "files" component, relative to the app root.
    */

    'file_paths' => [
        'storage/app',
    ],

    /*
    |--------------------------------------------------------------------------
    | External binaries
    |--------------------------------------------------------------------------
    |
    | Left empty, the command falls back to whatever is on PATH.
    |
    */

    'mysqldump_path' => env('BACKUP_MYSQLDUMP_PATH', 'mysqldump'),
    'git_path' => env('BACKUP_GIT_PATH', 'git'),

    /*
    | Seconds before an external process is considered hung.
    */

    'process_timeout' => (int) env('BACKUP_PROCESS_TIMEOUT', 900),

];
