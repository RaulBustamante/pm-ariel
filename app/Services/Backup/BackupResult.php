<?php

declare(strict_types=1);

namespace App\Services\Backup;

/**
 * Outcome of a single backup run. Immutable on purpose: a result that can be
 * edited after the fact is not evidence of anything.
 */
final readonly class BackupResult
{
    /**
     * @param  list<string>  $components  Which parts made it into the archive.
     */
    public function __construct(
        public string $path,
        public int $sizeBytes,
        public int $durationMs,
        public array $components,
        public int $prunedCount,
    ) {}

    public function humanSize(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = (float) $this->sizeBytes;
        $unit = 0;

        while ($size >= 1024 && $unit < count($units) - 1) {
            $size /= 1024;
            $unit++;
        }

        return sprintf('%.2f %s', $size, $units[$unit]);
    }
}
