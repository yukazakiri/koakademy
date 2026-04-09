<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Log;

final class VersionService
{
    private const string CACHE_KEY = 'application_version';

    private const int CACHE_TTL = 3600; // 1 hour

    private const string VERSION_FILE = 'version.json';

    /**
     * Get the current application version.
     */
    public function getVersion(): string
    {
        return config('app.version', '1.0.0');
    }

    /**
     * Get the full version data from version.json.
     *
     * @return array{
     *     version: string,
     *     image: string,
     *     commit: string,
     *     branch: string,
     *     timestamp: string,
     *     build_url: string,
     *     release_type: string,
     *     changelog: array{
     *         current: string,
     *         previous: string
     *     },
     *     metadata: array{
     *         author: string,
     *         workflow: string,
     *         repository: string
     *     }
     * }|null
     */
    public function getVersionData(): ?array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () {
            $versionFile = base_path(self::VERSION_FILE);

            if (! File::exists($versionFile)) {
                return null;
            }

            try {
                $content = File::get($versionFile);
                $data = json_decode($content, true);

                if (json_last_error() !== JSON_ERROR_NONE) {
                    Log::warning('Invalid JSON in version.json: '.json_last_error_msg());

                    return null;
                }

                return $this->validateVersionData($data) ? $data : null;
            } catch (Exception $e) {
                Log::error('Failed to read version.json: '.$e->getMessage());

                return null;
            }
        });
    }

    /**
     * Get the current version with additional metadata.
     *
     * @return array{
     *     version: string,
     *     release_type: string,
     *     commit: string|null,
     *     build_url: string|null,
     *     timestamp: string|null,
     *     is_latest: bool
     * }
     */
    public function getVersionInfo(): array
    {
        $versionData = $this->getVersionData();
        $currentVersion = $this->getVersion();

        return [
            'version' => $currentVersion,
            'release_type' => $versionData['release_type'] ?? 'patch',
            'commit' => $versionData['commit'] ?? null,
            'build_url' => $versionData['build_url'] ?? null,
            'timestamp' => $versionData['timestamp'] ?? null,
            'is_latest' => $this->isLatestVersionFromData($currentVersion, $versionData),
        ];
    }

    /**
     * Check if the current version is the latest.
     */
    public function isLatestVersion(string $version): bool
    {
        $this->clearCache();
        $versionData = $this->getVersionData();

        return $this->isLatestVersionFromData($version, $versionData);
    }

    /**
     * Clear the version cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Refresh the version data by clearing cache and re-reading.
     */
    public function refresh(): void
    {
        $this->clearCache();
        $this->getVersionData(); // Pre-populate cache
    }

    /**
     * Get version comparison result.
     *
     * @return 'major'|'minor'|'patch'|'equal'|'unknown'
     */
    public function compareVersions(string $version1, string $version2): string
    {
        $result = version_compare($version1, $version2);

        return match ($result) {
            1 => 'major',
            -1 => 'minor',
            0 => 'equal',
            default => 'unknown'
        };
    }

    /**
     * Get the version file path.
     */
    public function getVersionFilePath(): string
    {
        return base_path(self::VERSION_FILE);
    }

    /**
     * Check if version file exists.
     */
    public function versionFileExists(): bool
    {
        return File::exists($this->getVersionFilePath());
    }

    /**
     * Check if provided version is latest based on already loaded version data.
     *
     * @param  array{
     *     version: string,
     *     image: string,
     *     commit: string,
     *     branch: string,
     *     timestamp: string,
     *     build_url: string,
     *     release_type: string,
     *     changelog: array{
     *         current: string,
     *         previous: string
     *     },
     *     metadata: array{
     *         author: string,
     *         workflow: string,
     *         repository: string
     *     }
     * }|null  $versionData
     */
    private function isLatestVersionFromData(string $version, ?array $versionData): bool
    {
        if (! $versionData) {
            return true; // Assume latest if we can't determine
        }

        return version_compare($version, $versionData['version'], '>=');
    }

    /**
     * Validate the version data structure.
     *
     * @param  mixed  $data
     */
    private function validateVersionData($data): bool
    {
        if (! is_array($data)) {
            return false;
        }

        $requiredFields = ['version', 'image', 'commit', 'branch', 'timestamp', 'build_url', 'release_type'];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field]) || ! is_string($data[$field])) {
                return false;
            }
        }

        // Validate changelog structure
        if (! isset($data['changelog']) || ! is_array($data['changelog'])) {
            return false;
        }

        if (! isset($data['changelog']['current']) || ! is_string($data['changelog']['current'])) {
            return false;
        }

        // Validate metadata structure
        if (! isset($data['metadata']) || ! is_array($data['metadata'])) {
            return false;
        }

        $requiredMetadata = ['author', 'workflow', 'repository'];

        return array_all($requiredMetadata, fn ($field): bool => isset($data['metadata'][$field]) && is_string($data['metadata'][$field]));
    }
}
