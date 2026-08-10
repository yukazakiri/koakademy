<?php

declare(strict_types=1);

use App\Enums\ChangelogFailureReason;
use App\Enums\ChangelogStatus;
use App\Services\ChangelogService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    Cache::flush();
    Http::preventStrayRequests();

    config([
        'services.github.repo' => 'yukazakiri/koakademy',
        'services.github.token' => null,
    ]);
});

function changelogServiceReleasePayload(string $tag = 'v1.18.0', string $body = '### Features'."\n".'* New timeline'): array
{
    return [
        'name' => "Release {$tag}",
        'tag_name' => $tag,
        'prerelease' => false,
        'published_at' => '2026-08-08T15:41:28Z',
        'created_at' => '2026-08-08T15:41:28Z',
        'html_url' => "https://github.com/yukazakiri/koakademy/releases/tag/{$tag}",
        'body' => $body,
    ];
}

it('retries a rejected token anonymously for a public repository', function (): void {
    config(['services.github.token' => 'rejected-test-token']);

    Http::fake(function (Request $request) {
        return $request->hasHeader('Authorization')
            ? Http::response(['message' => 'Bad credentials'], 401)
            : Http::response([changelogServiceReleasePayload()]);
    });

    $result = app(ChangelogService::class)->getChangelogResult();

    expect($result->status)->toBe(ChangelogStatus::Live)
        ->and($result->entries)->toHaveCount(1);

    $pageRequests = Http::recorded(
        fn (Request $request): bool => $request->url() === 'https://api.github.com/repos/yukazakiri/koakademy/releases?per_page=20',
    )->values();

    expect($pageRequests)->toHaveCount(2)
        ->and($pageRequests->get(0)[0]->hasHeader('Authorization', 'Bearer rejected-test-token'))->toBeTrue()
        ->and($pageRequests->get(1)[0]->hasHeader('Authorization'))->toBeFalse();
});

it('retries a non-rate-limit token authorization error anonymously', function (): void {
    config(['services.github.token' => 'forbidden-test-token']);

    Http::fake(function (Request $request) {
        return $request->hasHeader('Authorization')
            ? Http::response(['message' => 'Resource not accessible by token'], 403)
            : Http::response([changelogServiceReleasePayload()]);
    });

    $result = app(ChangelogService::class)->getChangelogResult();

    expect($result->status)->toBe(ChangelogStatus::Live)
        ->and($result->entries)->toHaveCount(1);

    Http::assertSentCount(2);
});

it('uses a valid empty release response as available history', function (): void {
    Http::fake(['api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([], 200)]);

    $result = app(ChangelogService::class)->getChangelogResult();

    expect($result->status)->toBe(ChangelogStatus::Empty)
        ->and($result->isAvailable())->toBeTrue()
        ->and($result->entries)->toBeEmpty()
        ->and($result->lastSyncedAt)->not->toBeNull();
});

it('parses Release Please notes into readable grouped changes', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response([
            changelogServiceReleasePayload(body: "### Features\n* **delivery:** publish a FrankenPHP Octane image variant ([#149](https://github.com/yukazakiri/koakademy/issues/149)) ([e07c411](https://github.com/yukazakiri/koakademy/commit/e07c411))\n\n### Bug Fixes\n* **newsletter:** repair the subscription prompt ([#143](https://github.com/yukazakiri/koakademy/issues/143)) ([fd0a45a](https://github.com/yukazakiri/koakademy/commit/fd0a45a))"),
        ]),
    ]);

    $changes = app(ChangelogService::class)->getChangelogResult()->entries->first()['changes'];

    expect($changes)->toBe([
        ['type' => 'feature', 'description' => 'Publish a FrankenPHP Octane image variant'],
        ['type' => 'fix', 'description' => 'Repair the subscription prompt'],
    ]);
});

it('does not cache failed requests as an empty release list', function (): void {
    Http::fake(['api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response(['message' => 'Server error'], 500)]);

    $service = app(ChangelogService::class);

    expect($service->getChangelogResult()->status)->toBe(ChangelogStatus::Unavailable)
        ->and($service->getChangelogResult()->status)->toBe(ChangelogStatus::Unavailable);

    Http::assertSentCount(6);
});

it('retains the last successful release history during an outage', function (): void {
    $outage = false;
    Http::fake(function () use (&$outage) {
        return $outage
            ? Http::response(['message' => 'Server error'], 500)
            : Http::response([changelogServiceReleasePayload()], 200);
    });

    $service = app(ChangelogService::class);
    $service->getChangelogResult();

    Cache::forget('changelog_entries.v3.'.hash('sha256', 'yukazakiri/koakademy').'.limit:20.prereleases:0');
    $outage = true;

    $result = $service->getChangelogResult();

    expect($result->status)->toBe(ChangelogStatus::Stale)
        ->and($result->entries)->toHaveCount(1)
        ->and($result->entries->first()['version'])->toBe('1.18.0')
        ->and($result->failureReason)->toBe(ChangelogFailureReason::Unavailable);
});

it('distinguishes a GitHub repository that cannot be found', function (): void {
    Http::fake(['api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response(['message' => 'Not Found'], 404)]);

    $notFound = app(ChangelogService::class)->getChangelogResult();

    expect($notFound->status)->toBe(ChangelogStatus::Unavailable)
        ->and($notFound->failureReason)->toBe(ChangelogFailureReason::NotFound);
});

it('distinguishes a GitHub rate-limit response', function (): void {
    Http::fake([
        'api.github.com/repos/yukazakiri/koakademy/releases*' => Http::response(
            ['message' => 'API rate limit exceeded'],
            403,
            ['X-RateLimit-Remaining' => '0', 'X-RateLimit-Reset' => (string) now()->addMinutes(5)->timestamp],
        ),
    ]);

    $rateLimited = app(ChangelogService::class)->getChangelogResult();

    expect($rateLimited->status)->toBe(ChangelogStatus::Unavailable)
        ->and($rateLimited->failureReason)->toBe(ChangelogFailureReason::RateLimited);
});

it('retries only transient GitHub failures and handles connection failures', function (): void {
    Http::fake(['api.github.com/repos/yukazakiri/koakademy/releases*' => Http::failedConnection('Offline')]);

    $result = app(ChangelogService::class)->getChangelogResult();

    expect($result->status)->toBe(ChangelogStatus::Unavailable)
        ->and($result->failureReason)->toBe(ChangelogFailureReason::Unavailable);

    Http::assertSentCount(3);
});

it('isolates cached release histories by repository', function (): void {
    Http::fake(function (Request $request) {
        return str_contains($request->url(), 'first-school/koakademy')
            ? Http::response([changelogServiceReleasePayload('v1.0.0')])
            : Http::response([changelogServiceReleasePayload('v2.0.0')]);
    });

    $first = (new ChangelogService('first-school/koakademy'))->getChangelogResult();
    $second = (new ChangelogService('second-school/koakademy'))->getChangelogResult();

    expect($first->entries->first()['version'])->toBe('1.0.0')
        ->and($second->entries->first()['version'])->toBe('2.0.0');
});
