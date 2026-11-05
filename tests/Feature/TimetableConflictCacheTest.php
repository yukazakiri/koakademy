<?php

declare(strict_types=1);

use App\Services\TimetableConflictService;
use Illuminate\Support\Facades\Cache;

/**
 * DCC-34 / PERF-003 — `Cache::flush()` must never be reachable from the
 * timetable conflict cache path.
 *
 * Clearing conflict state must be scoped to conflict keys only, so other
 * tenants' cached state and distributed locks survive.
 */
it('never calls Cache::flush in the conflict service', function (): void {
    $source = file_get_contents(base_path('app/Services/TimetableConflictService.php'));

    // Comments may mention flush as a prohibition; no executable call may exist.
    $withoutComments = preg_replace('#/\*.*?\*/|//[^\n]*#s', '', $source);

    expect(preg_match('/Cache::flush\s*\(/', (string) $withoutComments))->toBe(0);
});

it('leaves unrelated cache entries and locks intact when clearing all conflicts', function (): void {
    $service = app(TimetableConflictService::class);

    Cache::put('unrelated:key', 'keep-me', 300);
    Cache::put(TimetableConflictService::VERSION_KEY, 1, 300);

    $lock = Cache::lock('assessment-export:create:1:1', 10);
    expect($lock->acquire())->toBeTrue();

    $service->clearConflictCache(null);

    expect(Cache::get('unrelated:key'))->toBe('keep-me')
        ->and((int) Cache::get(TimetableConflictService::VERSION_KEY))->toBeGreaterThan(1)
        ->and($lock->owner())->not->toBeNull('Distributed lock must survive conflict-cache clear');

    // The held lock still blocks a competing acquire (not wiped).
    expect(Cache::lock('assessment-export:create:1:1', 10)->acquire())->toBeFalse();

    $lock->release();
});

it('treats empty-string and zero keys as a full scoped clear, not a flush', function (): void {
    $service = app(TimetableConflictService::class);

    foreach ([null, '', '0'] as $emptyKey) {
        Cache::put('unrelated:key', 'keep-me', 300);
        $before = TimetableConflictService::currentVersion();

        $service->clearConflictCache($emptyKey);

        expect(Cache::get('unrelated:key'))->toBe('keep-me')
            ->and(TimetableConflictService::currentVersion())->toBeGreaterThan($before);
    }
});

it('only forgets the requested conflict key when one is given', function (): void {
    $service = app(TimetableConflictService::class);

    Cache::put(TimetableConflictService::scopedKey('timetable_conflicts:room:1'), 'stale', 300);
    Cache::put(TimetableConflictService::scopedKey('timetable_conflicts:room:2'), 'fresh', 300);
    Cache::put('unrelated:key', 'keep-me', 300);

    $service->clearConflictCache('timetable_conflicts:room:1');

    expect(Cache::has(TimetableConflictService::scopedKey('timetable_conflicts:room:1')))->toBeFalse()
        ->and(Cache::get(TimetableConflictService::scopedKey('timetable_conflicts:room:2')))->toBe('fresh')
        ->and(Cache::get('unrelated:key'))->toBe('keep-me');
});

it('bumps the version epoch so versioned keys become unreachable on a full clear', function (): void {
    $service = app(TimetableConflictService::class);

    Cache::forget(TimetableConflictService::VERSION_KEY);
    expect(TimetableConflictService::currentVersion())->toBe(1);

    $oldKey = TimetableConflictService::scopedKey('timetable_conflicts:room:99');
    Cache::put($oldKey, 'stale-value', 300);
    expect(TimetableConflictService::scopedKey('timetable_conflicts:room:99'))->toBe($oldKey);

    $service->clearConflictCache(null);

    $newKey = TimetableConflictService::scopedKey('timetable_conflicts:room:99');

    expect($newKey)->not->toBe($oldKey)
        ->and(str_starts_with($newKey, 'timetable_conflicts:v2:'))->toBeTrue()
        ->and(Cache::get($newKey))->toBeNull('Bumped-epoch key must not return the stale value');
});

it('stores cached conflict lookups under the versioned key', function (): void {
    $service = app(TimetableConflictService::class);
    $rawKey = 'timetable_conflicts:room:1';

    $first = $service->getCachedConflicts($rawKey, collect());
    $scoped = TimetableConflictService::scopedKey($rawKey);

    expect($scoped)->toStartWith('timetable_conflicts:v')
        ->and(Cache::get($scoped))->toBe($first)
        ->and(Cache::get($rawKey))->toBeNull();

    $service->clearConflictCache(null);

    $newScoped = TimetableConflictService::scopedKey($rawKey);
    expect($newScoped)->not->toBe($scoped)
        ->and(Cache::get($newScoped))->toBeNull();

    $second = $service->getCachedConflicts($rawKey, collect());

    expect($second)->toBe($first)
        ->and(Cache::get($newScoped))->toBe($second)
        ->and(Cache::get($rawKey))->toBeNull();
});

it('lets a page-triggered clear remove both legacy and versioned entries', function (): void {
    $service = app(TimetableConflictService::class);
    $legacyKey = 'timetable_conflicts:room:legacy';
    $scoped = TimetableConflictService::scopedKey($legacyKey);

    Cache::put($legacyKey, 'legacy-value', 300);
    Cache::put($scoped, 'versioned-value', 300);

    $service->clearConflictCache($legacyKey);

    expect(Cache::get($legacyKey))->toBeNull()
        ->and(Cache::get($scoped))->toBeNull();
});

it('versions cached conflict lookups and reuses them until cleared', function (): void {
    $service = app(TimetableConflictService::class);

    // Seed the versioned entry directly (as Cache::remember would) and prove
    // the keyed clear removes exactly that entry.
    $key = TimetableConflictService::scopedKey('timetable_conflicts:room:1');

    expect($key)->toStartWith('timetable_conflicts:v');

    Cache::put($key, ['time_room_conflicts' => [], 'faculty_conflicts' => [], 'student_conflicts' => []], 300);
    expect(Cache::has($key))->toBeTrue();

    $service->clearConflictCache('timetable_conflicts:room:1');

    expect(Cache::has($key))->toBeFalse();
});

it('keeps an already-versioned key stable across the Timetable page helper', function (): void {
    $scoped = TimetableConflictService::scopedKey('timetable_conflicts:0:room:5');

    expect(TimetableConflictService::scopedKey($scoped))->toBe($scoped)
        ->and($scoped)->toStartWith('timetable_conflicts:v');
});
