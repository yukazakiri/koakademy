<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\VersionService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();

    config([
        'inertia.testing.ensure_pages_exist' => false,
        'services.github.repo' => 'private-school/koakademy',
        'services.github.token' => 'rejected-test-token',
    ]);
});

function changelogPageReleasePayload(): array
{
    return [
        'name' => 'Mobile classroom update',
        'tag_name' => 'v1.10.0-dev.10.1',
        'prerelease' => true,
        'published_at' => '2026-07-20T08:15:00Z',
        'created_at' => '2026-07-20T08:15:00Z',
        'html_url' => 'https://github.com/private-school/koakademy/releases/tag/v1.10.0-dev.10.1',
        'body' => "## What's Changed\n- feat: improve the mobile classroom (abcdef1)",
    ];
}

it('retries a rejected GitHub token anonymously and shows public release notes', function (): void {
    Http::fake(function (Request $request) {
        if ($request->hasHeader('Authorization')) {
            return Http::response(['message' => 'Bad credentials'], 401);
        }

        return Http::response([changelogPageReleasePayload()]);
    });

    $this->get(route('changelog'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('changelog')
            ->where('changelog_status', 'live')
            ->has('changelog_last_synced_at')
            ->where('github_repo', 'private-school/koakademy')
            ->where('versionInfo.commit', null)
            ->where('versionInfo.build_url', null)
            ->where('changelog.0.title', 'Mobile classroom update')
            ->where('changelog.0.prerelease', true)
            ->where('changelog.0.source', 'github_release')
            ->where('changelog.0.changes.0.type', 'feature')
        );

    Http::assertSent(fn (Request $request): bool => $request->hasHeader('Authorization', 'Bearer rejected-test-token')
        && $request->url() === 'https://api.github.com/repos/private-school/koakademy/releases?per_page=30');
    Http::assertSent(fn (Request $request): bool => ! $request->hasHeader('Authorization')
        && $request->url() === 'https://api.github.com/repos/private-school/koakademy/releases?per_page=30');
});

it('shows an honest unavailable timeline state instead of a build metadata release', function (): void {
    Http::fake([
        'api.github.com/repos/private-school/koakademy/releases*' => Http::response(['message' => 'Service unavailable'], 500),
    ]);

    $this->get(route('changelog'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('changelog')
            ->where('changelog_status', 'unavailable')
            ->where('github_repo', 'private-school/koakademy')
            ->where('versionInfo.commit', null)
            ->where('versionInfo.build_url', null)
            ->where('changelog', [])
        );
});

it('identifies a valid but empty GitHub release history', function (): void {
    Http::fake([
        'api.github.com/repos/private-school/koakademy/releases*' => Http::response([], 200),
    ]);

    $this->get(route('changelog'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('changelog')
            ->where('changelog_status', 'empty')
            ->where('changelog', [])
        );
});

it('preserves build metadata for an administrator', function (): void {
    Http::fake([
        'api.github.com/repos/private-school/koakademy/releases*' => Http::response([changelogPageReleasePayload()]),
    ]);

    $expectedVersionInfo = app(VersionService::class)->getVersionInfo();
    $administrator = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($administrator)
        ->get(route('changelog'))
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->where('layout', 'admin')
            ->where('versionInfo.commit', $expectedVersionInfo['commit'])
            ->where('versionInfo.build_url', $expectedVersionInfo['build_url'])
        );
});
