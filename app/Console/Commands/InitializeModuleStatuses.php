<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use Illuminate\Console\Command;
use JsonException;
use Override;
use RuntimeException;

final class InitializeModuleStatuses extends Command
{
    #[Override]
    protected $signature = 'modules:initialize-statuses
                            {--mode=preserve : preserve the image status file or disable every discovered module}';

    #[Override]
    protected $description = 'Create the persistent module status file once without overwriting administrator choices.';

    public function handle(ModuleManifestRepository $manifests, ModuleStateRepository $states): int
    {
        $statusesPath = $states->statusFilePath();

        if ($statusesPath === '') {
            throw new RuntimeException('The module status file path cannot be empty.');
        }

        if (is_file($statusesPath)) {
            $this->line("Module status file already exists at [{$statusesPath}]; preserving it.");

            return self::SUCCESS;
        }

        $mode = (string) $this->option('mode');

        if (! in_array($mode, ['disabled', 'preserve'], true)) {
            $this->error('The --mode option must be either disabled or preserve.');

            return self::INVALID;
        }

        $statuses = $mode === 'disabled'
            ? array_fill_keys(array_map(static fn ($manifest): string => $manifest->name, $manifests->all()), false)
            : $this->readImageStatuses($manifests);

        $directory = dirname($statusesPath);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create module status directory [{$directory}].");
        }

        try {
            $contents = json_encode($statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode the module status file.', previous: $exception);
        }

        $temporaryPath = $statusesPath.'.'.bin2hex(random_bytes(8)).'.tmp';

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write temporary module status file [{$temporaryPath}].");
        }

        if (! rename($temporaryPath, $statusesPath)) {
            @unlink($temporaryPath);

            throw new RuntimeException("Unable to move module status file into place at [{$statusesPath}].");
        }

        $this->info("Initialized module statuses in {$mode} mode at [{$statusesPath}].");

        return self::SUCCESS;
    }

    /**
     * @return array<string, bool>
     */
    private function readImageStatuses(ModuleManifestRepository $manifests): array
    {
        $sourcePath = base_path('modules_statuses.json');

        if (! is_file($sourcePath)) {
            return array_fill_keys(array_map(static fn ($manifest): string => $manifest->name, $manifests->all()), true);
        }

        $contents = file_get_contents($sourcePath);

        if ($contents === false) {
            throw new RuntimeException("Unable to read image module status file [{$sourcePath}].");
        }

        try {
            $statuses = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Image module status file [{$sourcePath}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($statuses)) {
            throw new RuntimeException("Image module status file [{$sourcePath}] must contain a JSON object.");
        }

        return array_filter(
            $statuses,
            static fn (mixed $enabled, mixed $moduleName): bool => is_string($moduleName) && is_bool($enabled),
            ARRAY_FILTER_USE_BOTH,
        );
    }
}
