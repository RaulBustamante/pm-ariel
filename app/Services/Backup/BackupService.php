<?php

declare(strict_types=1);

namespace App\Services\Backup;

use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Produces a single timestamped archive containing the database, the uploaded
 * files and a bundle of the git history.
 *
 * Nothing here is silent: every failure throws with the reason. A backup that
 * fails quietly is worse than no backup, because it buys false confidence.
 */
final class BackupService
{
    private const TIMESTAMP_FORMAT = 'Y-m-d_His';

    private const ARCHIVE_PREFIX = 'backup_';

    public function __construct(
        private readonly Filesystem $files,
    ) {}

    public function run(): BackupResult
    {
        $startedAt = hrtime(true);

        $destination = trim((string) config('backup.destination'));

        if ($destination === '') {
            throw new RuntimeException(
                'No backup destination is configured. Set BACKUP_DESTINATION in .env to an absolute path.'
            );
        }

        $this->files->ensureDirectoryExists($destination);

        if (! is_writable($destination)) {
            throw new RuntimeException("Backup destination is not writable: {$destination}");
        }

        $staging = $this->makeStagingDirectory();
        $components = [];

        try {
            if (config('backup.include.database')) {
                $this->dumpDatabase($staging.DIRECTORY_SEPARATOR.'database.sql');
                $components[] = 'database';
            }

            if (config('backup.include.files')) {
                if ($this->copyFilePaths($staging.DIRECTORY_SEPARATOR.'files')) {
                    $components[] = 'files';
                }
            }

            if (config('backup.include.repository')) {
                if ($this->bundleRepository($staging.DIRECTORY_SEPARATOR.'repository.bundle')) {
                    $components[] = 'repository';
                }
            }

            if ($components === []) {
                throw new RuntimeException('Every backup component is disabled; there is nothing to back up.');
            }

            $archive = $destination.DIRECTORY_SEPARATOR
                .self::ARCHIVE_PREFIX.now()->format(self::TIMESTAMP_FORMAT).'.zip';

            $this->compress($staging, $archive);
        } finally {
            // The staging copy holds a full database dump. It goes away whether
            // the run succeeded or not.
            $this->files->deleteDirectory($staging);
        }

        // Pruning happens only after a successful archive, so a failed run can
        // never take the previous good backup with it.
        $pruned = $this->prune($destination);

        return new BackupResult(
            path: $archive,
            sizeBytes: (int) filesize($archive),
            durationMs: (int) ((hrtime(true) - $startedAt) / 1_000_000),
            components: $components,
            prunedCount: $pruned,
        );
    }

    /**
     * Staging lives under storage/framework, never under a directory listed in
     * `backup.file_paths`. Staging inside the copy source makes copyDirectory
     * recurse into its own output until the path length blows up.
     */
    private function makeStagingDirectory(): string
    {
        $staging = storage_path('framework/backup-staging-'.bin2hex(random_bytes(6)));
        $this->files->ensureDirectoryExists($staging);

        return $staging;
    }

    private function dumpDatabase(string $target): void
    {
        $connection = (string) config('database.default');
        $config = config("database.connections.{$connection}");

        if (($config['driver'] ?? null) !== 'mysql') {
            throw new RuntimeException(
                "Backup only supports the mysql driver; connection [{$connection}] uses [{$config['driver']}]."
            );
        }

        // Credentials go through a defaults file rather than the command line:
        // arguments are visible to anyone who can list processes.
        $credentials = tempnam(sys_get_temp_dir(), 'bk');

        if ($credentials === false) {
            throw new RuntimeException('Could not create a temporary credentials file for mysqldump.');
        }

        try {
            $this->files->put($credentials, sprintf(
                "[client]\nuser=%s\npassword=\"%s\"\nhost=%s\nport=%s\n",
                $config['username'],
                $config['password'],
                $config['host'],
                $config['port'],
            ));

            $process = new Process([
                (string) config('backup.mysqldump_path'),
                '--defaults-extra-file='.$credentials,
                '--single-transaction',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                '--result-file='.$target,
                $config['database'],
            ]);

            $this->mustRun($process, 'mysqldump');
        } finally {
            $this->files->delete($credentials);
        }
    }

    /**
     * @return bool Whether anything was actually copied.
     */
    private function copyFilePaths(string $target): bool
    {
        $copiedAnything = false;

        /** @var list<string> $paths */
        $paths = config('backup.file_paths', []);

        foreach ($paths as $relativePath) {
            $source = base_path($relativePath);

            if (! $this->files->isDirectory($source)) {
                continue;
            }

            // Refuse to copy a directory that contains the staging area: doing
            // so recurses into its own output. Cheap insurance against someone
            // later adding a broader path to backup.file_paths.
            if (str_starts_with($this->normalise($target), $this->normalise($source).DIRECTORY_SEPARATOR)) {
                throw new RuntimeException(
                    "Backup path [{$relativePath}] contains the staging directory; copying it would recurse indefinitely."
                );
            }

            $destination = $target.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], '_', $relativePath);
            $this->files->ensureDirectoryExists($destination);
            $this->files->copyDirectory($source, $destination);
            $copiedAnything = true;
        }

        return $copiedAnything;
    }

    /**
     * @return bool Whether a bundle was produced. False when the project is not
     *              a git repository yet, which is not an error.
     */
    private function bundleRepository(string $target): bool
    {
        if (! $this->files->isDirectory(base_path('.git'))) {
            return false;
        }

        $process = new Process(
            [(string) config('backup.git_path'), 'bundle', 'create', $target, '--all'],
            base_path(),
        );

        $this->mustRun($process, 'git bundle');

        return true;
    }

    private function compress(string $staging, string $archive): void
    {
        $zip = new ZipArchive;
        $opened = $zip->open($archive, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        if ($opened !== true) {
            throw new RuntimeException("Could not create archive [{$archive}]: ZipArchive error {$opened}.");
        }

        foreach ($this->files->allFiles($staging, hidden: true) as $file) {
            $zip->addFile($file->getPathname(), $file->getRelativePathname());
        }

        if (! $zip->close()) {
            throw new RuntimeException("Could not finalise archive [{$archive}].");
        }
    }

    private function prune(string $destination): int
    {
        $keep = (int) config('backup.keep');

        if ($keep <= 0) {
            return 0;
        }

        $archives = collect($this->files->files($destination))
            ->filter(fn ($file): bool => str_starts_with($file->getFilename(), self::ARCHIVE_PREFIX))
            ->sortByDesc(fn ($file): string => $file->getFilename())
            ->values();

        $stale = $archives->slice($keep);

        foreach ($stale as $file) {
            $this->files->delete($file->getPathname());
        }

        return $stale->count();
    }

    private function normalise(string $path): string
    {
        return rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path), DIRECTORY_SEPARATOR);
    }

    private function mustRun(Process $process, string $label): void
    {
        $process->setTimeout((float) config('backup.process_timeout'));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(sprintf(
                '%s failed with exit code %d: %s',
                $label,
                (int) $process->getExitCode(),
                trim($process->getErrorOutput()) ?: 'no error output',
            ));
        }
    }
}
