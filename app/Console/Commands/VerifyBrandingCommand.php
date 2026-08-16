<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Finder\Finder;

/**
 * Falla si el nombre comercial se filtró al código fuente.
 *
 * Sin esta verificación en el CI, la regla de portabilidad de marca dura hasta
 * el primer día con prisa.
 */
final class VerifyBrandingCommand extends Command
{
    protected $signature = 'branding:verify';

    protected $description = 'Fail if the product name leaked out of config/branding.php into the source';

    /**
     * config/branding.php queda fuera porque es justamente donde el nombre debe
     * estar, y .env* porque son configuración de entorno, no código.
     */
    private const SCANNED_PATHS = ['app', 'database', 'routes', 'resources', 'config'];

    private const EXCLUDED_FILES = ['branding.php'];

    public function handle(): int
    {
        /** @var list<string> $terms */
        $terms = config('branding.forbidden_terms', []);

        if ($terms === []) {
            $this->components->warn('No forbidden terms configured; nothing to verify.');

            return self::SUCCESS;
        }

        $existingPaths = array_values(array_filter(
            array_map(base_path(...), self::SCANNED_PATHS),
            is_dir(...),
        ));

        $finder = Finder::create()
            ->files()
            ->in($existingPaths)
            ->name(['*.php', '*.blade.php', '*.js', '*.css', '*.json'])
            ->notName(self::EXCLUDED_FILES);

        $violations = [];

        foreach ($finder as $file) {
            $contents = $file->getContents();

            foreach ($terms as $term) {
                if (stripos($contents, $term) === false) {
                    continue;
                }

                foreach (explode("\n", $contents) as $number => $line) {
                    if (stripos($line, $term) !== false) {
                        $violations[] = [
                            str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname()),
                            $number + 1,
                            trim($line),
                        ];
                    }
                }
            }
        }

        if ($violations !== []) {
            $this->components->error(sprintf(
                'The product name leaked into %d place(s). It belongs in config/branding.php only.',
                count($violations),
            ));
            $this->table(['File', 'Line', 'Content'], $violations);

            return self::FAILURE;
        }

        $this->components->info('Branding is clean: the product name appears only in configuration.');

        return self::SUCCESS;
    }
}
