<?php

declare(strict_types=1);

namespace Tests\Feature\Backup;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use ZipArchive;

final class RunBackupCommandTest extends TestCase
{
    private string $destination;

    protected function setUp(): void
    {
        parent::setUp();

        $this->destination = storage_path('framework/testing/backups-'.bin2hex(random_bytes(4)));
        config()->set('backup.destination', $this->destination);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->destination);

        parent::tearDown();
    }

    #[Test]
    public function it_writes_an_archive_containing_the_database_dump(): void
    {
        config()->set('backup.include.repository', false);

        $this->artisan('backup:run')->assertSuccessful();

        $archives = File::files($this->destination);
        $this->assertCount(1, $archives, 'Exactly one archive should have been produced.');

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($archives[0]->getPathname()) === true);
        $this->assertNotFalse($zip->locateName('database.sql'), 'The archive must contain the database dump.');
        $zip->close();
    }

    #[Test]
    public function it_fails_loudly_when_every_component_is_disabled(): void
    {
        config()->set('backup.include.database', false);
        config()->set('backup.include.files', false);
        config()->set('backup.include.repository', false);

        $this->artisan('backup:run')->assertFailed();

        $this->assertEmpty(File::files($this->destination), 'A failed run must not leave an archive behind.');
    }

    #[Test]
    public function it_keeps_only_the_configured_number_of_archives(): void
    {
        File::ensureDirectoryExists($this->destination);

        // Older than anything the run will produce, so sorting by name is enough.
        foreach (['2020-01-01_000000', '2020-01-02_000000', '2020-01-03_000000'] as $stamp) {
            File::put($this->destination.DIRECTORY_SEPARATOR."backup_{$stamp}.zip", 'stale');
        }

        config()->set('backup.include.repository', false);
        config()->set('backup.keep', 2);

        $this->artisan('backup:run')->assertSuccessful();

        $this->assertCount(2, File::files($this->destination));
    }

    #[Test]
    public function it_does_not_leave_staging_directories_behind(): void
    {
        config()->set('backup.include.repository', false);

        $this->artisan('backup:run')->assertSuccessful();

        $leftovers = collect(File::directories(storage_path('app')))
            ->filter(fn (string $path): bool => str_contains(basename($path), 'backup-staging-'));

        $this->assertTrue($leftovers->isEmpty(), 'Staging directories hold a full database dump and must be removed.');
    }
}
