<?php

declare(strict_types=1);

use App\Services\ChangelogService;
use App\Services\VersionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
});

it('syncs feature showcase current version from version.json', function (): void {
    $versionService = app(VersionService::class);
    $versionData = $versionService->getVersionData();

    $configuredVersion = config('filament-feature-showcase.current');

    if ($versionData) {
        expect($configuredVersion)->toBe($versionData['version']);
    } else {
        // If version.json is missing, should fall back to static config or latest stable
        expect($configuredVersion)->not->toBeEmpty();
    }
});

it('populates showcase changelog with stable releases only', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            [
                'tag_name' => 'v0.2.0',
                'prerelease' => false,
                'published_at' => '2026-04-15T00:00:00Z',
                'created_at' => '2026-04-15T00:00:00Z',
                'html_url' => 'https://github.com/yukazakiri/koakademy/releases/tag/v0.2.0',
                'body' => "## Features\n- New dashboard\n\n## Bug Fixes\n- Fixed login issue",
            ],
            [
                'tag_name' => 'v0.2.1-dev.0.1',
                'prerelease' => true,
                'published_at' => '2026-04-16T00:00:00Z',
                'created_at' => '2026-04-16T00:00:00Z',
                'html_url' => 'https://github.com/yukazakiri/koakademy/releases/tag/v0.2.1-dev.0.1',
                'body' => '- Minor tweak',
            ],
        ], 200),
    ]);

    $service = app(ChangelogService::class);
    $showcase = $service->getShowcaseChangelog();

    expect($showcase)->toHaveKey('0.2.0');
    expect($showcase)->not->toHaveKey('0.2.1-dev.0.1');
});

it('cleans URLs and mentions from showcase feature text', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            [
                'tag_name' => 'v0.1.0',
                'prerelease' => false,
                'published_at' => '2026-04-09T00:00:00Z',
                'created_at' => '2026-04-09T00:00:00Z',
                'html_url' => 'https://github.com/yukazakiri/koakademy/releases/tag/v0.1.0',
                'body' => "- Move announcements to module and wire data service by @yukazakiri in https://github.com/yukazakiri/koakademy/pull/1\n- **Bold feature** with details",
            ],
        ], 200),
    ]);

    $service = app(ChangelogService::class);
    $showcase = $service->getShowcaseChangelog();

    $features = $showcase['0.1.0']['features'];

    // Titles should be short, no URLs or @mentions
    foreach ($features as $feature) {
        expect($feature['title'])->not->toContain('https://');
        expect($feature['title'])->not->toContain('@yukazakiri');
        expect(mb_strlen($feature['title']))->toBeLessThanOrEqual(63); // 60 + '...' margin
    }

    // Descriptions should be clean too
    foreach ($features as $feature) {
        expect($feature['description'])->not->toContain('https://');
        expect($feature['description'])->not->toContain('@');
    }
});

it('groups multiple changes of the same type under one feature card', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            [
                'tag_name' => 'v0.3.0',
                'prerelease' => false,
                'published_at' => '2026-04-20T00:00:00Z',
                'created_at' => '2026-04-20T00:00:00Z',
                'html_url' => 'https://github.com/yukazakiri/koakademy/releases/tag/v0.3.0',
                'body' => "## Features\n- New dashboard\n- New reports page\n\n## Bug Fixes\n- Fixed login crash\n- Fixed pagination",
            ],
        ], 200),
    ]);

    $service = app(ChangelogService::class);
    $showcase = $service->getShowcaseChangelog();

    $features = $showcase['0.3.0']['features'];

    // Two grouped cards: one for features, one for fixes
    expect($features)->toHaveCount(2);
    expect($features[0]['title'])->toBe('New Features');
    expect($features[0]['description'])->toContain('New dashboard');
    expect($features[0]['description'])->toContain('New reports page');
    expect($features[1]['title'])->toBe('Bug Fixes');
});

it('falls back to static config when GitHub API is unreachable', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([], 500),
    ]);

    $service = app(ChangelogService::class);
    $showcase = $service->getShowcaseChangelog();

    $staticChangelog = config('filament-feature-showcase.changelog', []);
    expect($showcase)->toBe($staticChangelog);
});

it('falls back to static config when GitHub API rate limit is exceeded', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            'message' => 'API rate limit exceeded for 58.69.240.72.',
        ], 403),
    ]);

    $service = app(ChangelogService::class);
    $showcase = $service->getShowcaseChangelog();

    $staticChangelog = config('filament-feature-showcase.changelog', []);
    expect($showcase)->toBe($staticChangelog);
});

it('returns latest stable version from GitHub', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            [
                'tag_name' => 'v0.1.0',
                'prerelease' => false,
                'published_at' => '2026-04-09T00:00:00Z',
                'created_at' => '2026-04-09T00:00:00Z',
                'html_url' => 'https://github.com/yukazakiri/koakademy/releases/tag/v0.1.0',
                'body' => '- Initial release',
            ],
        ], 200),
    ]);

    $service = app(ChangelogService::class);
    $version = $service->getLatestStableVersion();

    expect($version)->toBe('0.1.0');
});

it('clears all showcase caches', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            [
                'tag_name' => 'v0.1.0',
                'prerelease' => false,
                'published_at' => '2026-04-09T00:00:00Z',
                'created_at' => '2026-04-09T00:00:00Z',
                'html_url' => 'https://github.com/yukazakiri/koakademy/releases/tag/v0.1.0',
                'body' => '- Initial release',
            ],
        ], 200),
    ]);

    $service = app(ChangelogService::class);

    $service->getShowcaseChangelog();
    $service->getLatestStableVersion();

    $service->clearCache();

    expect(Cache::has('changelog_entries'))->toBeFalse()
        ->and(Cache::has('showcase_changelog'))->toBeFalse()
        ->and(Cache::has('latest_stable_version'))->toBeFalse();
});
