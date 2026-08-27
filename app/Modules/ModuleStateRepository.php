<?php

declare(strict_types=1);

namespace App\Modules;

use App\Modules\Contracts\ModuleManifest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use JsonException;
use RuntimeException;
use Throwable;

final class ModuleStateRepository
{
    /**
     * @var array<string, bool>
     */
    private array $statuses = [];

    private bool $loaded = false;

    private ?bool $databaseAvailable = null;

    public function isEnabled(string $moduleName): bool
    {
        $this->loadStatuses();

        return $this->statuses[$moduleName] ?? false;
    }

    /**
     * @return list<string>
     */
    public function enabledModuleNames(): array
    {
        $this->loadStatuses();

        return array_values(array_keys(array_filter(
            $this->statuses,
            static fn (bool $enabled): bool => $enabled,
        )));
    }

    public function restartRequired(string $moduleName): bool
    {
        if (! $this->databaseIsAvailable()) {
            return false;
        }

        return (bool) DB::table($this->tableName())
            ->where('module_name', $moduleName)
            ->value('restart_required');
    }

    public function setEnabled(string $moduleName, bool $enabled): void
    {
        $this->loadStatuses();
        $this->statuses[$moduleName] = $enabled;

        if ($this->databaseIsAvailable()) {
            $now = now();
            $actorId = Auth::id();
            $actorId = is_int($actorId) ? $actorId : (is_numeric($actorId) ? (int) $actorId : null);

            DB::table($this->tableName())->upsert([[
                'module_name' => $moduleName,
                'enabled' => $enabled,
                'restart_required' => true,
                'changed_by' => $actorId,
                'enabled_at' => $enabled ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ]], ['module_name'], [
                'enabled',
                'restart_required',
                'changed_by',
                'enabled_at',
                'updated_at',
            ]);
        }

        $this->writeLegacyStatuses();
    }

    /**
     * @param  list<ModuleManifest>  $manifests
     */
    public function sync(array $manifests, string $mode = 'preserve', bool $clearRestartRequired = false): int
    {
        if (! $this->databaseIsAvailable()) {
            return 0;
        }

        $legacyStatuses = $this->readLegacyStatuses();
        $now = now();
        $rows = [];

        foreach ($manifests as $manifest) {
            $rows[] = [
                'module_name' => $manifest->name,
                'composer_package' => $manifest->composerPackage,
                'installed_version' => $manifest->version,
                'enabled' => $mode === 'preserve' && ($legacyStatuses[$manifest->name] ?? false) === true,
                'restart_required' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return 0;
        }

        $updateColumns = [
            'composer_package',
            'installed_version',
            'updated_at',
        ];

        if ($clearRestartRequired) {
            $updateColumns[] = 'restart_required';
        }

        DB::table($this->tableName())->upsert($rows, ['module_name'], $updateColumns);

        $this->loaded = false;
        $this->statuses = [];

        return count($rows);
    }

    public function databaseIsAvailable(): bool
    {
        if (config('modules.activator', 'database') !== 'database') {
            return false;
        }

        if ($this->databaseAvailable !== null) {
            return $this->databaseAvailable;
        }

        try {
            return $this->databaseAvailable = Schema::hasTable($this->tableName());
        } catch (Throwable $exception) {
            Log::debug('Module state database is not available; using the legacy status file.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->databaseAvailable = false;
        }
    }

    public function statusFilePath(): string
    {
        return (string) config(
            'modules.statuses-file',
            config('modules.activators.file.statuses-file', base_path('modules_statuses.json')),
        );
    }

    /**
     * @return array<string, bool>
     */
    public function readLegacyStatuses(): array
    {
        $path = $this->statusFilePath();

        if (! is_file($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            throw new RuntimeException("Unable to read module status file [{$path}].");
        }

        try {
            $statuses = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException("Module status file [{$path}] contains invalid JSON.", previous: $exception);
        }

        if (! is_array($statuses)) {
            throw new RuntimeException("Module status file [{$path}] must contain a JSON object.");
        }

        return array_filter(
            $statuses,
            static fn (mixed $enabled, mixed $moduleName): bool => is_string($moduleName) && is_bool($enabled),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    private function loadStatuses(): void
    {
        if ($this->loaded) {
            return;
        }

        $this->statuses = $this->readLegacyStatuses();

        if ($this->databaseIsAvailable()) {
            try {
                foreach (DB::table($this->tableName())->pluck('enabled', 'module_name') as $moduleName => $enabled) {
                    if (is_string($moduleName)) {
                        $this->statuses[$moduleName] = (bool) $enabled;
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('Unable to load database-backed module states; using the legacy status file.', [
                    'message' => $exception->getMessage(),
                ]);
                $this->databaseAvailable = false;
            }
        }

        $this->loaded = true;
    }

    private function writeLegacyStatuses(): void
    {
        $path = $this->statusFilePath();
        $directory = dirname($path);

        if (! is_dir($directory) && ! mkdir($directory, 0750, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create module status directory [{$directory}].");
        }

        try {
            $contents = json_encode($this->statuses, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode module statuses.', previous: $exception);
        }

        $temporaryPath = $path.'.'.bin2hex(random_bytes(8)).'.tmp';

        if (file_put_contents($temporaryPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write temporary module status file [{$temporaryPath}].");
        }

        if (! rename($temporaryPath, $path)) {
            @unlink($temporaryPath);

            throw new RuntimeException("Unable to replace module status file [{$path}].");
        }
    }

    private function tableName(): string
    {
        return 'module_installations';
    }
}
