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
