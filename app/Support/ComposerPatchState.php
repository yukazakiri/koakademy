<?php

declare(strict_types=1);

namespace App\Support;

use const JSON_PRETTY_PRINT;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

use RuntimeException;

final class ComposerPatchState
{
    public const string STATE_RELATIVE_PATH = 'composer/koakademy-applied-patches.json';

    /**
     * @return array<string, string>
     */
    public static function readLockedPackageHashes(string $lockFilePath): array
    {
        if (! is_file($lockFilePath)) {
            return [];
        }

        $raw = file_get_contents($lockFilePath);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['patches']) || ! is_array($decoded['patches'])) {
            return [];
        }

        $hashes = [];

        foreach ($decoded['patches'] as $package => $patches) {
            if (! is_string($package) || ! is_array($patches) || $patches === []) {
                continue;
            }

            $hashes[mb_strtolower($package)] = hash('sha256', json_encode($patches, JSON_THROW_ON_ERROR));
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @return array<string, string>
     */
    public static function readAppliedPackageHashes(string $stateFilePath): array
    {
        if (! is_file($stateFilePath)) {
            return [];
        }

        $raw = file_get_contents($stateFilePath);
        if ($raw === false) {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded) || ! isset($decoded['packages']) || ! is_array($decoded['packages'])) {
            return [];
        }

        $hashes = [];

        foreach ($decoded['packages'] as $package => $hash) {
            if (! is_string($package) || ! is_string($hash) || $hash === '') {
                continue;
            }

            $hashes[mb_strtolower($package)] = $hash;
        }

        ksort($hashes);

        return $hashes;
    }

    /**
     * @param  array<string, string>  $lockedHashes
     * @param  array<string, string>  $appliedHashes
     * @param  list<string>  $installedPackages
     * @return list<string>
     */
    public static function staleInstalledPackages(array $lockedHashes, array $appliedHashes, array $installedPackages): array
    {
        $stale = [];

        foreach ($installedPackages as $package) {
            $normalized = mb_strtolower($package);
            $locked = $lockedHashes[$normalized] ?? null;
            $applied = $appliedHashes[$normalized] ?? null;

            if ($locked !== $applied) {
                $stale[] = $normalized;
            }
        }

        $stale = array_values(array_unique($stale));
        sort($stale);

        return $stale;
    }

    /**
     * @param  array<string, string>  $lockedHashes
     * @param  list<string>  $installedPackages
     */
    public static function writeAppliedPackageHashes(string $stateFilePath, array $lockedHashes, array $installedPackages): void
    {
        $recorded = [];

        foreach ($installedPackages as $package) {
            $normalized = mb_strtolower($package);

            if (isset($lockedHashes[$normalized])) {
                $recorded[$normalized] = $lockedHashes[$normalized];
            }
        }

        ksort($recorded);

        if ($recorded === []) {
            if (is_file($stateFilePath)) {
                unlink($stateFilePath);
            }

            return;
        }

        $directory = dirname($stateFilePath);

        if (! is_dir($directory) && ! mkdir($directory, 0777, true) && ! is_dir($directory)) {
            throw new RuntimeException("Unable to create directory [{$directory}].");
        }

        $encoded = json_encode(
            ['packages' => $recorded],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        )."\n";

        if (file_put_contents($stateFilePath, $encoded) === false) {
            throw new RuntimeException("Unable to write Composer patch state [{$stateFilePath}].");
        }
    }
}
