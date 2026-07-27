<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

function generateVersionMetadata(array $arguments, string $output): Process
{
    $bash = (new ExecutableFinder)->find('bash');

    if ($bash === null) {
        throw new RuntimeException('Bash is required for version metadata contract tests.');
    }

    $process = new Process([
        $bash,
        base_path('scripts/generate-version-metadata.sh'),
        ...$arguments,
        '--output',
        $output,
    ], base_path());
    $process->setTimeout(10);
    $process->run();

    return $process;
}

it('generates deterministic stable and recovery metadata', function (): void {
    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/version-metadata-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);
    $firstOutput = $state.'/stable.json';
    $rerunOutput = $state.'/recovery.json';
    $arguments = [
        '--channel', 'stable',
        '--version', '1.12.0',
        '--commit', '0123456789abcdef0123456789abcdef01234567',
        '--image', 'ghcr.io/yukazakiri/koakademy:sha-0123456789abcdef0123456789abcdef01234567',
        '--build-url', 'https://github.com/yukazakiri/koakademy/actions/runs/123456',
        '--repository', 'yukazakiri/koakademy',
        '--timestamp', '2026-07-26T12:00:00Z',
    ];

    try {
        $first = generateVersionMetadata($arguments, $firstOutput);
        $rerun = generateVersionMetadata($arguments, $rerunOutput);

        expect($first->isSuccessful())
            ->toBeTrue($first->getErrorOutput())
            ->and($rerun->isSuccessful())
            ->toBeTrue($rerun->getErrorOutput())
            ->and(file_get_contents($firstOutput))
            ->toBe(file_get_contents($rerunOutput));

        $metadata = json_decode(file_get_contents($firstOutput) ?: '{}', true, flags: JSON_THROW_ON_ERROR);

        expect($metadata)
            ->toMatchArray([
                'version' => '1.12.0',
                'commit' => '0123456789abcdef0123456789abcdef01234567',
                'release_type' => 'stable',
                'timestamp' => '2026-07-26T12:00:00Z',
            ])
            ->and($metadata['metadata']['channel'])->toBe('stable');
    } finally {
        $filesystem->remove($state);
    }
});

it('generates edge metadata without changing the tracked stable baseline', function (): void {
    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/version-metadata-edge-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);
    $output = $state.'/edge.json';
    $trackedPath = base_path('version.json');
    $trackedBefore = file_get_contents($trackedPath) ?: '{}';
    $tracked = json_decode($trackedBefore, true, flags: JSON_THROW_ON_ERROR);
    $stableVersion = $tracked['version'];

    try {
        $process = generateVersionMetadata([
            '--channel', 'edge',
            '--version', $stableVersion,
            '--commit', 'abcdef0123456789abcdef0123456789abcdef01',
            '--image', 'ghcr.io/yukazakiri/koakademy:sha-abcdef0123456789abcdef0123456789abcdef01',
            '--build-url', 'https://github.com/yukazakiri/koakademy/actions/runs/123457',
            '--repository', 'yukazakiri/koakademy',
            '--timestamp', '2026-07-26T12:01:00Z',
        ], $output);

        expect($process->isSuccessful())->toBeTrue($process->getErrorOutput());

        $metadata = json_decode(file_get_contents($output) ?: '{}', true, flags: JSON_THROW_ON_ERROR);
        $trackedAfter = file_get_contents($trackedPath) ?: '{}';

        expect($metadata['version'])
            ->toBe("{$stableVersion}-edge+sha.abcdef012345")
            ->and($metadata['release_type'])->toBe('edge')
            ->and($metadata['metadata']['channel'])->toBe('edge')
            ->and($trackedAfter)->toBe($trackedBefore);
    } finally {
        $filesystem->remove($state);
    }
});

it('rejects malformed versions and source commits', function (array $override): void {
    $filesystem = new Filesystem;
    $state = storage_path('framework/testing/version-metadata-invalid-'.bin2hex(random_bytes(6)));
    $filesystem->mkdir($state);
    $output = $state.'/invalid.json';
    $arguments = [
        '--channel', $override['channel'] ?? 'stable',
        '--version', $override['version'] ?? '1.12.0',
        '--commit', $override['commit'] ?? '0123456789abcdef0123456789abcdef01234567',
        '--image', 'ghcr.io/yukazakiri/koakademy:sha-0123456789abcdef0123456789abcdef01234567',
        '--build-url', 'https://github.com/yukazakiri/koakademy/actions/runs/123458',
        '--repository', 'yukazakiri/koakademy',
        '--timestamp', '2026-07-26T12:02:00Z',
    ];

    try {
        $process = generateVersionMetadata($arguments, $output);

        expect($process->isSuccessful())->toBeFalse();
    } finally {
        $filesystem->remove($state);
    }
})->with([
    'prerelease tag' => [['version' => '1.12.0-rc.1']],
    'prefixed tag' => [['version' => 'v1.12.0']],
    'short commit' => [['commit' => '0123456']],
    'unknown channel' => [['channel' => 'nightly']],
]);

it('renders stable and edge delivery metadata as explicit changelog channels', function (): void {
    $changelog = file_get_contents(base_path('resources/js/pages/changelog.tsx')) ?: '';

    expect($changelog)
        ->toContain('stable: { label: "Stable release"')
        ->toContain('edge: { label: "Edge build"');
});
