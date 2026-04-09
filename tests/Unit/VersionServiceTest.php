<?php

declare(strict_types=1);

use App\Services\VersionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    // Clear cache before each test
    Cache::flush();

    // Mock the config function
    config(['app.version' => '1.24.3']);
});

afterEach(function (): void {
    // Clean up after each test
    Cache::flush();
});

describe('VersionService', function (): void {
    test('can get version from config', function (): void {
        $service = new VersionService();

        expect($service->getVersion())->toBe('1.24.3');
    });

    test('can get version data from valid version.json', function (): void {
        $versionData = [
            'version' => '1.24.3',
            'image' => 'docker.io/yukazaki/dccpadminv3:v1.24.3',
            'commit' => '6ef467e3c60eac270e47828c3cd271aa4f42b965',
            'branch' => 'main',
            'timestamp' => '2025-12-08T06:41:26Z',
            'build_url' => 'https://github.com/yukazakiri/koakademy/actions/runs/20019052482',
            'release_type' => 'patch',
            'changelog' => [
                'current' => 'Version 1.24.3 - Merge branch \'main\'',
                'previous' => '',
            ],
            'metadata' => [
                'author' => 'yukazakiri',
                'workflow' => 'Auto Release and Docker Tag',
                'repository' => 'yukazakiri/koakademy',
            ],
        ];

        // Mock File::exists and File::get
        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($versionData));

        $service = new VersionService();
        $result = $service->getVersionData();

        expect($result)->toBeArray();
        expect($result['version'])->toBe('1.24.3');
        expect($result['release_type'])->toBe('patch');
        expect($result['commit'])->toBe('6ef467e3c60eac270e47828c3cd271aa4f42b965');
    });

    test('returns null when version.json does not exist', function (): void {
        File::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $service = new VersionService();
        $result = $service->getVersionData();

        expect($result)->toBeNull();
    });

    test('returns null when version.json contains invalid JSON', function (): void {
        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn('invalid json');

        $service = new VersionService();
        $result = $service->getVersionData();

        expect($result)->toBeNull();
    });

    test('returns null when version.json has invalid structure', function (): void {
        $invalidData = [
            'version' => '1.24.3',
            // Missing required fields
        ];

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($invalidData));

        $service = new VersionService();
        $result = $service->getVersionData();

        expect($result)->toBeNull();
    });

    test('can get version info with metadata', function (): void {
        $versionData = [
            'version' => '1.24.3',
            'image' => 'docker.io/yukazaki/dccpadminv3:v1.24.3',
            'commit' => '6ef467e3c60eac270e47828c3cd271aa4f42b965',
            'branch' => 'main',
            'timestamp' => '2025-12-08T06:41:26Z',
            'build_url' => 'https://github.com/yukazakiri/koakademy/actions/runs/20019052482',
            'release_type' => 'patch',
            'changelog' => [
                'current' => 'Version 1.24.3',
                'previous' => '',
            ],
            'metadata' => [
                'author' => 'yukazakiri',
                'workflow' => 'Auto Release and Docker Tag',
                'repository' => 'yukazakiri/koakademy',
            ],
        ];

        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andReturn(json_encode($versionData));

        $service = new VersionService();
        $result = $service->getVersionInfo();

        expect($result)->toBeArray();
        expect($result['version'])->toBe('1.24.3');
        expect($result['release_type'])->toBe('patch');
        expect($result['commit'])->toBe('6ef467e3c60eac270e47828c3cd271aa4f42b965');
        expect($result['build_url'])->toBe('https://github.com/yukazakiri/koakademy/actions/runs/20019052482');
        expect($result['timestamp'])->toBe('2025-12-08T06:41:26Z');
        expect($result['is_latest'])->toBeTrue();
    });

    test('can determine if version is latest', function (): void {
        $versionData = [
            'version' => '1.24.3',
            'image' => 'docker.io/yukazaki/dccpadminv3:v1.24.3',
            'commit' => '6ef467e3c60eac270e47828c3cd271aa4f42b965',
            'branch' => 'main',
            'timestamp' => '2025-12-08T06:41:26Z',
            'build_url' => 'https://github.com/yukazakiri/koakademy/actions/runs/20019052482',
            'release_type' => 'patch',
            'changelog' => [
                'current' => 'Version 1.24.3',
                'previous' => '',
            ],
            'metadata' => [
                'author' => 'yukazakiri',
                'workflow' => 'Auto Release and Docker Tag',
                'repository' => 'yukazakiri/koakademy',
            ],
        ];

        File::shouldReceive('exists')
            ->times(3)
            ->andReturn(true);

        File::shouldReceive('get')
            ->times(3)
            ->andReturn(json_encode($versionData));

        $service = new VersionService();

        expect($service->isLatestVersion('1.24.3'))->toBeTrue();
        expect($service->isLatestVersion('1.24.2'))->toBeFalse();
        expect($service->isLatestVersion('1.25.0'))->toBeTrue(); // Future version
    });

    test('assumes latest when version data is unavailable', function (): void {
        File::shouldReceive('exists')
            ->once()
            ->andReturn(false);

        $service = new VersionService();
        $result = $service->isLatestVersion('1.24.3');

        expect($result)->toBeTrue();
    });

    test('can clear cache', function (): void {
        $versionData = [
            'version' => '1.24.3',
            'image' => 'docker.io/yukazaki/dccpadminv3:v1.24.3',
            'commit' => '6ef467e3c60eac270e47828c3cd271aa4f42b965',
            'branch' => 'main',
            'timestamp' => '2025-12-08T06:41:26Z',
            'build_url' => 'https://github.com/yukazakiri/koakademy/actions/runs/20019052482',
            'release_type' => 'patch',
            'changelog' => [
                'current' => 'Version 1.24.3',
                'previous' => '',
            ],
            'metadata' => [
                'author' => 'yukazakiri',
                'workflow' => 'Auto Release and Docker Tag',
                'repository' => 'yukazakiri/koakademy',
            ],
        ];

        File::shouldReceive('exists')
            ->times(2)
            ->andReturn(true);

        File::shouldReceive('get')
            ->times(2)
            ->andReturn(json_encode($versionData));

        $service = new VersionService();

        // First call should cache the result
        $result1 = $service->getVersionData();

        // Clear cache
        $service->clearCache();

        // Second call should read from file again (hence the 2 expectations above)
        $result2 = $service->getVersionData();

        expect($result1)->toEqual($result2);
    });

    test('can refresh version data', function (): void {
        $versionData = [
            'version' => '1.24.3',
            'image' => 'docker.io/yukazaki/dccpadminv3:v1.24.3',
            'commit' => '6ef467e3c60eac270e47828c3cd271aa4f42b965',
            'branch' => 'main',
            'timestamp' => '2025-12-08T06:41:26Z',
            'build_url' => 'https://github.com/yukazakiri/koakademy/actions/runs/20019052482',
            'release_type' => 'patch',
            'changelog' => [
                'current' => 'Version 1.24.3',
                'previous' => '',
            ],
            'metadata' => [
                'author' => 'yukazakiri',
                'workflow' => 'Auto Release and Docker Tag',
                'repository' => 'yukazakiri/koakademy',
            ],
        ];

        File::shouldReceive('exists')
            ->times(2)
            ->andReturn(true);

        File::shouldReceive('get')
            ->times(2)
            ->andReturn(json_encode($versionData));

        $service = new VersionService();

        // First call
        $result1 = $service->getVersionData();

        // Refresh
        $service->refresh();

        // Should get same result after refresh
        $result2 = $service->getVersionData();

        expect($result1)->toEqual($result2);
    });

    test('can compare versions', function (): void {
        $service = new VersionService();

        expect($service->compareVersions('1.24.3', '1.24.2'))->toBe('major');
        expect($service->compareVersions('1.24.2', '1.24.3'))->toBe('minor');
        expect($service->compareVersions('1.24.3', '1.24.3'))->toBe('equal');
    });

    test('can get version file path', function (): void {
        $service = new VersionService();
        $path = $service->getVersionFilePath();

        expect($path)->toBe(base_path('version.json'));
    });

    test('can check if version file exists', function (): void {
        File::shouldReceive('exists')
            ->with(base_path('version.json'))
            ->once()
            ->andReturn(true);

        $service = new VersionService();
        $exists = $service->versionFileExists();

        expect($exists)->toBeTrue();
    });

    test('handles file read exceptions gracefully', function (): void {
        File::shouldReceive('exists')
            ->once()
            ->andReturn(true);

        File::shouldReceive('get')
            ->once()
            ->andThrow(new Exception('File read error'));

        $service = new VersionService();
        $result = $service->getVersionData();

        expect($result)->toBeNull();
    });

    test('caches version data for performance', function (): void {
        $versionData = [
            'version' => '1.24.3',
            'image' => 'docker.io/yukazaki/dccpadminv3:v1.24.3',
            'commit' => '6ef467e3c60eac270e47828c3cd271aa4f42b965',
            'branch' => 'main',
            'timestamp' => '2025-12-08T06:41:26Z',
            'build_url' => 'https://github.com/yukazakiri/koakademy/actions/runs/20019052482',
            'release_type' => 'patch',
            'changelog' => [
                'current' => 'Version 1.24.3',
                'previous' => '',
            ],
            'metadata' => [
                'author' => 'yukazakiri',
                'workflow' => 'Auto Release and Docker Tag',
                'repository' => 'yukazakiri/koakademy',
            ],
        ];

        File::shouldReceive('exists')
            ->once() // Should only be called once due to caching
            ->andReturn(true);

        File::shouldReceive('get')
            ->once() // Should only be called once due to caching
            ->andReturn(json_encode($versionData));

        $service = new VersionService();

        // Multiple calls should only hit the file system once
        $result1 = $service->getVersionData();
        $result2 = $service->getVersionData();
        $result3 = $service->getVersionData();

        expect($result1)->toEqual($result2);
        expect($result2)->toEqual($result3);
    });
});
