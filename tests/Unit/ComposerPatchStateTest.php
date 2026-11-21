<?php

declare(strict_types=1);

use App\Support\ComposerPatchState;

function makePatchStateTempDir(): string
{
    $dir = sys_get_temp_dir().'/koakademy-patch-state-'.bin2hex(random_bytes(6));
    mkdir($dir, 0777, true);

    return $dir;
}

function removePatchStateTempDir(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.'/'.$item;

        if (is_dir($path)) {
            removePatchStateTempDir($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

it('reads per-package deterministic hashes from patches.lock.json and handles missing or invalid files', function (): void {
    $dir = makePatchStateTempDir();

    try {
        expect(ComposerPatchState::readLockedPackageHashes($dir.'/missing.json'))->toBe([]);

        file_put_contents($dir.'/malformed.json', '{not json');
        expect(ComposerPatchState::readLockedPackageHashes($dir.'/malformed.json'))->toBe([]);

        file_put_contents($dir.'/empty.json', json_encode(['_hash' => 'abc', 'patches' => []], JSON_THROW_ON_ERROR));
        expect(ComposerPatchState::readLockedPackageHashes($dir.'/empty.json'))->toBe([]);

        $lockPath = $dir.'/patches.lock.json';
        $patches = [
            [
                'package' => 'Coolsam/Modules',
                'description' => 'Filament 5 MakeWidgetCommand::handle(Filesystem) signature',
                'url' => 'patches/coolsam-modules-filament-5-widget-handle.patch',
                'sha256' => '9b141a3a0988ba9bd48ce4cc7c04620350e532258ce319641d03d97e12080a2f',
                'depth' => 1,
            ],
        ];
        file_put_contents($lockPath, json_encode([
            '_hash' => 'lockhash',
            'patches' => ['Coolsam/Modules' => $patches],
        ], JSON_THROW_ON_ERROR));

        $hashes = ComposerPatchState::readLockedPackageHashes($lockPath);

        expect($hashes)->toBe([
            'coolsam/modules' => hash('sha256', json_encode($patches, JSON_THROW_ON_ERROR)),
        ]);
    } finally {
        removePatchStateTempDir($dir);
    }
});

it('flags an already-installed package as stale when a locked patch is newly added', function (): void {
    $stale = ComposerPatchState::staleInstalledPackages(
        lockedHashes: ['coolsam/modules' => 'hash-v1'],
        appliedHashes: [],
        installedPackages: ['laravel/framework', 'Coolsam/Modules'],
    );

    expect($stale)->toBe(['coolsam/modules']);
});

it('returns no stale packages when applied hashes match locked hashes and ignores unpatched packages', function (): void {
    $stale = ComposerPatchState::staleInstalledPackages(
        lockedHashes: ['coolsam/modules' => 'hash-v1'],
        appliedHashes: ['coolsam/modules' => 'hash-v1'],
        installedPackages: ['laravel/framework', 'coolsam/modules', 'filament/widgets'],
    );

    expect($stale)->toBe([]);
});

it('flags installed packages whose patch changed or whose previously applied patch was removed', function (): void {
    $stale = ComposerPatchState::staleInstalledPackages(
        lockedHashes: [
            'coolsam/modules' => 'hash-v2',
        ],
        appliedHashes: [
            'coolsam/modules' => 'hash-v1',
            'vendor/removed-patch' => 'old-hash',
        ],
        installedPackages: ['coolsam/modules', 'vendor/removed-patch', 'laravel/framework'],
    );

    expect($stale)->toBe(['coolsam/modules', 'vendor/removed-patch']);
});

it('does not flag packages that are not currently installed', function (): void {
    $stale = ComposerPatchState::staleInstalledPackages(
        lockedHashes: ['coolsam/modules' => 'hash-v1'],
        appliedHashes: [],
        installedPackages: [],
    );

    expect($stale)->toBe([]);
});

it('persists applied patch hashes for installed packages and removes the state file when empty', function (): void {
    $dir = makePatchStateTempDir();
    $statePath = $dir.'/composer/koakademy-applied-patches.json';

    try {
        ComposerPatchState::writeAppliedPackageHashes(
            stateFilePath: $statePath,
            lockedHashes: [
                'coolsam/modules' => 'hash-v1',
                'other/not-installed' => 'hash-v2',
            ],
            installedPackages: ['laravel/framework', 'Coolsam/Modules'],
        );

        expect(is_file($statePath))->toBeTrue()
            ->and(ComposerPatchState::readAppliedPackageHashes($statePath))->toBe([
                'coolsam/modules' => 'hash-v1',
            ]);

        ComposerPatchState::writeAppliedPackageHashes(
            stateFilePath: $statePath,
            lockedHashes: [],
            installedPackages: ['laravel/framework', 'coolsam/modules'],
        );

        expect(is_file($statePath))->toBeFalse()
            ->and(ComposerPatchState::readAppliedPackageHashes($statePath))->toBe([]);
    } finally {
        removePatchStateTempDir($dir);
    }
});
