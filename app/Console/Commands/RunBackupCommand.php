<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Backup\BackupService;
use Illuminate\Console\Command;
use Throwable;

final class RunBackupCommand extends Command
{
    protected $signature = 'backup:run';

    protected $description = 'Archive the database, uploaded files and git history into a single timestamped file';

    public function handle(BackupService $backups): int
    {
        $this->components->info('Running backup...');

        try {
            $result = $backups->run();
        } catch (Throwable $exception) {
            // Reported to the operator and to the log, then surfaced as a
            // non-zero exit code so a scheduler notices the failure.
            report($exception);
            $this->components->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->components->twoColumnDetail('Archive', $result->path);
        $this->components->twoColumnDetail('Size', $result->humanSize());
        $this->components->twoColumnDetail('Contents', implode(', ', $result->components));
        $this->components->twoColumnDetail('Duration', $result->durationMs.' ms');
        $this->components->twoColumnDetail('Pruned', (string) $result->prunedCount);

        $this->newLine();
        $this->components->info('Backup complete.');

        return self::SUCCESS;
    }
}
