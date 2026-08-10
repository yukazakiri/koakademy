<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Changelog\ChangelogFetchResult;
use App\Enums\ChangelogFailureReason;
use App\Enums\ChangelogStatus;
use Exception;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ChangelogService
{
    private const string CACHE_KEY_PREFIX = 'changelog_entries.v3';

    private const string LEGACY_CACHE_KEY = 'changelog_entries';

    private const string CACHE_KEYS_KEY = 'changelog_entry_cache_keys.v3';

    private const string RATE_LIMIT_CACHE_KEY_PREFIX = 'changelog_github_rate_limited.v3';

    private const int FRESH_CACHE_TTL = 3600; // 1 hour

    private const int STALE_CACHE_TTL = 86400; // 24 hours

    private const int LOCK_TTL = 15;

    public function __construct(
        private ?string $githubRepo = null,
        private ?string $githubToken = null,
    ) {}

    /**
     * Get changelog entries from GitHub releases.
     *
     * @return Collection<int, array{title: string, version: string, date: string, published_at: string, type: string, prerelease: bool, source: string, changes: array<int, array{type: string, description: string}>, github_url: string|null}>
     */
    public function getChangelog(int $limit = 20, bool $includePrereleases = false): Collection
    {
        return $this->getChangelogResult($limit, $includePrereleases)->entries;
    }

    /**
     * Get changelog entries together with their availability state.
     */
    public function getChangelogResult(int $limit = 20, bool $includePrereleases = false): ChangelogFetchResult
    {
        $repo = $this->configuredRepository();

        if ($repo === null) {
            Log::warning('ChangelogService: Missing or invalid GitHub repository configuration');

            return $this->unavailable(ChangelogFailureReason::MissingRepository);
        }

        $limit = min(max($limit, 1), 100);
        $cacheKey = $this->cacheKey($repo, $limit, $includePrereleases);

        if (($payload = $this->cachedPayload($cacheKey)) !== null) {
            return $this->resultFromPayload($payload);
        }

        $result = Cache::lock("{$cacheKey}.refresh", self::LOCK_TTL)->get(function () use ($repo, $limit, $includePrereleases, $cacheKey): ChangelogFetchResult {
            if (($payload = $this->cachedPayload($cacheKey)) !== null) {
                return $this->resultFromPayload($payload);
            }

            $fetch = $this->fetchGitHubReleases($repo, $limit);

            if ($fetch['releases'] !== null) {
                $entries = $this->normalizeReleases($fetch['releases'], $includePrereleases);
                $payload = [
                    'entries' => $entries->all(),
                    'synced_at' => now()->toIso8601String(),
                ];

                Cache::put($cacheKey, $payload, self::FRESH_CACHE_TTL);
                Cache::put($this->staleCacheKey($cacheKey), $payload, self::STALE_CACHE_TTL);
                $this->rememberCacheKeys($cacheKey, $this->staleCacheKey($cacheKey));

                return $this->resultFromPayload($payload);
            }

            return $this->staleOrUnavailable($cacheKey, $fetch['failure_reason']);
        });

        if ($result instanceof ChangelogFetchResult) {
            return $result;
        }

        return $this->staleOrUnavailable($cacheKey, ChangelogFailureReason::Unavailable);
    }

    /**
     * Get changelog entries formatted for the Filament Feature Showcase config.
     *
     * @return array<string, array{title: string, description: string, features: array<int, array{icon: string, title: string, description: string}>}>
     */
    public function getShowcaseChangelog(): array
    {
        return Cache::remember('showcase_changelog', self::FRESH_CACHE_TTL, function (): array {
            $releases = $this->getChangelog(limit: 30, includePrereleases: false);

            if ($releases->isEmpty()) {
                return config('filament-feature-showcase.changelog', []);
            }

            return $releases->mapWithKeys(function (array $release): array {
                $version = $release['version'];
                $date = $release['date'];
                $features = $this->buildShowcaseFeatures($release['changes']);

                if ($features === []) {
                    $features = [[
                        'icon' => 'heroicon-o-arrow-path',
                        'title' => 'Improvements',
                        'description' => 'Various improvements and updates.',
                    ]];
                }

                return [
                    $version => [
                        'title' => "Version {$version}",
                        'description' => "Released on {$date}",
                        'features' => $features,
                    ],
                ];
            })->all();
        });
    }

    /**
     * Get the latest stable version from GitHub releases.
     */
    public function getLatestStableVersion(): ?string
    {
        return Cache::remember('latest_stable_version', self::FRESH_CACHE_TTL, function (): ?string {
            $releases = $this->getChangelog(limit: 1, includePrereleases: false);

            return $releases->first()['version'] ?? null;
        });
    }

    /**
     * Clear all current and legacy changelog caches.
     */
    public function clearCache(): void
    {
        foreach (Cache::get(self::CACHE_KEYS_KEY, []) as $cacheKey) {
            Cache::forget($cacheKey);
        }

        Cache::forget(self::LEGACY_CACHE_KEY);
        Cache::forget('changelog_entry_cache_keys');
        Cache::forget('changelog_github_rate_limited');
        Cache::forget(self::CACHE_KEYS_KEY);
        Cache::forget('showcase_changelog');
        Cache::forget('latest_stable_version');
    }

    /**
     * @param  array<int, array{type: string, description: string}>  $changes
     * @return array<int, array{icon: string, title: string, description: string}>
     */
    private function buildShowcaseFeatures(array $changes): array
    {
        return collect($changes)
            ->filter(fn (array $change): bool => mb_strlen($this->cleanShowcaseText($change['description'])) > 0)
            ->groupBy('type')
            ->flatMap(function (Collection $group, string $type): array {
                $icon = match ($type) {
                    'feature' => 'heroicon-o-sparkles',
                    'fix' => 'heroicon-o-bug-ant',
                    'breaking' => 'heroicon-o-exclamation-triangle',
                    'security' => 'heroicon-o-shield-check',
                    default => 'heroicon-o-arrow-path',
                };

                $typeLabel = match ($type) {
                    'feature' => 'New Features',
                    'fix' => 'Bug Fixes',
                    'breaking' => 'Breaking Changes',
                    'security' => 'Security',
                    default => 'Improvements',
                };

                if ($group->count() === 1) {
                    $cleaned = $this->cleanShowcaseText($group->first()['description']);

                    return [[
                        'icon' => $icon,
                        'title' => $this->summarizeShowcaseTitle($cleaned),
                        'description' => $cleaned,
                    ]];
                }

                $summaries = $group
                    ->map(fn (array $change): string => $this->summarizeShowcaseTitle($this->cleanShowcaseText($change['description'])))
                    ->filter()
                    ->values();

                return [[
                    'icon' => $icon,
                    'title' => $typeLabel,
                    'description' => $summaries->implode("\n"),
                ]];
            })
            ->values()
            ->all();
    }

    private function cleanShowcaseText(string $text): string
    {
        $text = preg_replace('/https?:\/\/\S+/', '', $text);
        $text = preg_replace('/@[\w-]+/', '', (string) $text);
        $text = preg_replace('/\(#\d+\)/', '', (string) $text);
        $text = preg_replace('/\b[0-9a-f]{7,40}\b/i', '', (string) $text);
        $text = preg_replace('/[*_]+/', '', (string) $text);
        $text = preg_replace('/\s+/', ' ', mb_trim((string) $text));

        return mb_rtrim($text, ' ,;-');
    }

    private function summarizeShowcaseTitle(string $cleanedText): string
    {
        if ($cleanedText === '') {
            return '';
        }

        $short = preg_match('/^(.+?)[.:;]/', $cleanedText, $match) ? mb_trim($match[1]) : $cleanedText;

        if (mb_strlen($short) > 60) {
            $short = mb_substr($short, 0, 57).'...';
        }

        return ucfirst($short);
    }

    /**
     * @return array{releases: array<int, array<string, mixed>>|null, failure_reason: ChangelogFailureReason|null}
     */
    private function fetchGitHubReleases(string $repo, int $limit): array
    {
        $rateLimitKey = $this->rateLimitCacheKey($repo);

        if (Cache::get($rateLimitKey) === true) {
            Log::warning('ChangelogService: Skipping GitHub call during rate-limit cooldown', ['repo' => $repo]);

            return ['releases' => null, 'failure_reason' => ChangelogFailureReason::RateLimited];
        }

        $token = $this->configuredToken();
        $response = $this->requestGitHubReleases($repo, $limit, $token);

        if ($token !== null && $response !== null && $this->shouldRetryAnonymously($response)) {
            Log::notice('ChangelogService: Retrying public GitHub releases anonymously after token authentication failure', [
                'repo' => $repo,
                'status' => $response->status(),
            ]);

            $response = $this->requestGitHubReleases($repo, $limit);
        }

        if ($response?->successful()) {
            $data = $response->json();

            if (is_array($data) && array_is_list($data)) {
                return ['releases' => $data, 'failure_reason' => null];
            }

            Log::warning('ChangelogService: GitHub returned an unexpected release payload', ['repo' => $repo]);

            return ['releases' => null, 'failure_reason' => ChangelogFailureReason::Unavailable];
        }

        $failureReason = $this->failureReasonFor($response);

        if ($response !== null && $failureReason === ChangelogFailureReason::RateLimited) {
            $this->rememberRateLimit($rateLimitKey, $response);
        }

        Log::warning('ChangelogService: GitHub release request was unavailable', [
            'repo' => $repo,
            'status' => $response?->status(),
            'reason' => $failureReason->value,
        ]);

        return ['releases' => null, 'failure_reason' => $failureReason];
    }

    private function requestGitHubReleases(string $repo, int $limit, ?string $token = null): ?Response
    {
        try {
            $request = Http::baseUrl('https://api.github.com')
                ->accept('application/vnd.github+json')
                ->withUserAgent(config('app.name', 'Laravel').'/changelog-service')
                ->connectTimeout(3)
                ->timeout(10)
                ->retry(
                    [100, 300],
                    0,
                    function (Throwable $exception): bool {
                        return $exception instanceof ConnectionException
                            || ($exception instanceof RequestException && $exception->response->serverError());
                    },
                    throw: false,
                );

            if ($token !== null) {
                $request->withToken($token);
            }

            return $request->get("/repos/{$repo}/releases", ['per_page' => $limit]);
        } catch (ConnectionException $exception) {
            Log::warning('ChangelogService: GitHub release connection failed', [
                'repo' => $repo,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }

    private function shouldRetryAnonymously(Response $response): bool
    {
        return $response->status() === 401
            || ($response->status() === 403 && ! $this->isRateLimited($response));
    }

    private function failureReasonFor(?Response $response): ChangelogFailureReason
    {
        return match ($response?->status()) {
            401, 403 => $response !== null && $this->isRateLimited($response)
                ? ChangelogFailureReason::RateLimited
                : ChangelogFailureReason::Unauthorized,
            404 => ChangelogFailureReason::NotFound,
            default => ChangelogFailureReason::Unavailable,
        };
    }

    private function isRateLimited(Response $response): bool
    {
        if ($response->header('X-RateLimit-Remaining') === '0') {
            return true;
        }

        return str_contains(mb_strtolower((string) $response->json('message', '')), 'rate limit');
    }

    private function rememberRateLimit(string $cacheKey, Response $response): void
    {
        $resetAt = (int) $response->header('X-RateLimit-Reset');
        $seconds = $resetAt > now()->timestamp
            ? min($resetAt - now()->timestamp, self::STALE_CACHE_TTL)
            : 600;

        Cache::put($cacheKey, true, now()->addSeconds(max($seconds, 60)));
        $this->rememberCacheKeys($cacheKey);
    }

    /**
     * @param  array<int, array<string, mixed>>  $releases
     * @return Collection<int, array{title: string, version: string, date: string, published_at: string, type: string, prerelease: bool, source: string, changes: array<int, array{type: string, description: string}>, github_url: string|null}>
     */
    private function normalizeReleases(array $releases, bool $includePrereleases): Collection
    {
        return collect($releases)
            ->filter(fn (mixed $release): bool => is_array($release))
            ->filter(fn (array $release): bool => $includePrereleases || ! (bool) ($release['prerelease'] ?? false))
            ->map(function (array $release): array {
                $version = mb_ltrim((string) ($release['tag_name'] ?? ''), 'v');
                $publishedAt = (string) ($release['published_at'] ?? $release['created_at'] ?? '');

                return [
                    'title' => is_string($release['name'] ?? null) && $release['name'] !== ''
                        ? $release['name']
                        : "Version {$version}",
                    'version' => $version,
                    'date' => $this->formatDate($publishedAt),
                    'published_at' => $publishedAt,
                    'type' => $this->determineVersionType($version),
                    'prerelease' => (bool) ($release['prerelease'] ?? false),
                    'source' => 'github_release',
                    'changes' => $this->parseGitHubReleaseBody((string) ($release['body'] ?? '')),
                    'github_url' => is_string($release['html_url'] ?? null) ? $release['html_url'] : null,
                ];
            })
            ->values();
    }

    /**
     * @return array<int, array{type: string, description: string}>
     */
    private function parseGitHubReleaseBody(string $body): array
    {
        $changes = [];
        $currentType = 'improvement';

        foreach (explode("\n", $body) as $line) {
            $line = mb_trim($line);

            if ($line === '' || $line === '0') {
                continue;
            }

            if (str_starts_with($line, '#') && preg_match('/(Features?|New|Added)/i', $line)) {
                $currentType = 'feature';

                continue;
            }

            if (str_starts_with($line, '#') && preg_match('/(Bug\s*Fixes?|Fixed|Fixes)/i', $line)) {
                $currentType = 'fix';

                continue;
            }

            if (str_starts_with($line, '#') && preg_match('/(Breaking|Breaking\s*Changes?)/i', $line)) {
                $currentType = 'breaking';

                continue;
            }

            if (str_starts_with($line, '#') && preg_match('/Security/i', $line)) {
                $currentType = 'security';

                continue;
            }

            if (str_starts_with($line, '#') && preg_match('/(Improvements?|Enhanced?|Changed?|Maintenance|Performance)/i', $line)) {
                $currentType = 'improvement';

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $description = $this->cleanChangeDescription(mb_trim($matches[1]));

                if ($description !== '') {
                    $changes[] = [
                        'type' => $this->inferChangeType(mb_trim($matches[1]), $currentType),
                        'description' => $description,
                    ];
                }
            }
        }

        return collect($changes)
            ->unique(fn (array $change): string => $change['type'].'|'.$change['description'])
            ->values()
            ->all();
    }

    private function determineVersionType(string $version): string
    {
        $baseVersion = explode('-', $version, 2)[0];
        $parts = explode('.', $baseVersion);

        if (count($parts) < 3 || ($parts[1] === '0' && $parts[2] === '0')) {
            return 'major';
        }

        return $parts[2] === '0' ? 'minor' : 'patch';
    }

    private function inferChangeType(string $description, string $fallback): string
    {
        $description = preg_replace('/^\*\*(.+?)\*\*/', '$1', $description) ?? $description;

        return match (true) {
            preg_match('/^(breaking|major)(?:\([^)]+\))?!?:/i', $description) === 1 => 'breaking',
            preg_match('/^(security|sec)(?:\([^)]+\))?!?:/i', $description) === 1 => 'security',
            preg_match('/^(feat|feature)(?:\([^)]+\))?!?:/i', $description) === 1 => 'feature',
            preg_match('/^(fix|bugfix)(?:\([^)]+\))?!?:/i', $description) === 1 => 'fix',
            preg_match('/^(perf|refactor|chore|style|docs|test)(?:\([^)]+\))?!?:/i', $description) === 1 => 'improvement',
            default => $fallback,
        };
    }

    private function cleanChangeDescription(string $description): string
    {
        $description = preg_replace('/\s*\(\[#\d+\]\([^)]*\)\)/', '', $description);
        $description = preg_replace('/\s*\(\[[0-9a-f]{7,40}\]\([^)]*\)\)/i', '', (string) $description);
        $description = preg_replace('/\[([^\]]+)\]\([^)]*\)/', '$1', (string) $description);
        $description = preg_replace('/[`*_]+/', '', (string) $description);
        $description = preg_replace(
            '/^(?:breaking|major|security|sec|feat|feature|fix|bugfix|perf|refactor|chore|style|docs|test)(?:\([^)]+\))?!?:\s*/i',
            '',
            (string) $description,
        );
        $description = preg_replace('/^[\w-]+:\s*/u', '', (string) $description);
        $description = preg_replace('/\s+by\s+@[\w-]+$/', '', (string) $description);
        $description = preg_replace('/\s+\(?#\d+\)?/', '', (string) $description);
        $description = preg_replace('/\s+\(?[0-9a-f]{7,40}\)?/i', '', (string) $description);
        $description = preg_replace('/\s+/', ' ', mb_trim((string) $description));

        return ucfirst(mb_rtrim((string) $description, ' ,;-'));
    }

    private function configuredRepository(): ?string
    {
        $repo = mb_trim((string) ($this->githubRepo ?? config('services.github.repo')));

        return preg_match('/\A[\w.-]+\/[\w.-]+\z/', $repo) === 1 ? $repo : null;
    }

    private function configuredToken(): ?string
    {
        $token = mb_trim((string) ($this->githubToken ?? config('services.github.token')));

        return $token === '' ? null : $token;
    }

    private function cacheKey(string $repo, int $limit, bool $includePrereleases): string
    {
        return self::CACHE_KEY_PREFIX.'.'.hash('sha256', mb_strtolower($repo)).".limit:{$limit}.prereleases:".($includePrereleases ? '1' : '0');
    }

    private function staleCacheKey(string $cacheKey): string
    {
        return "{$cacheKey}.stale";
    }

    private function rateLimitCacheKey(string $repo): string
    {
        return self::RATE_LIMIT_CACHE_KEY_PREFIX.'.'.hash('sha256', mb_strtolower($repo));
    }

    /**
     * @return array{entries: array<int, array<string, mixed>>, synced_at: string}|null
     */
    private function cachedPayload(string $cacheKey): ?array
    {
        $payload = Cache::get($cacheKey);

        if (! is_array($payload) || ! is_array($payload['entries'] ?? null) || ! is_string($payload['synced_at'] ?? null)) {
            return null;
        }

        return $payload;
    }

    /**
     * @param  array{entries: array<int, array<string, mixed>>, synced_at: string}  $payload
     */
    private function resultFromPayload(array $payload, bool $stale = false, ?ChangelogFailureReason $failureReason = null): ChangelogFetchResult
    {
        $entries = collect($payload['entries']);
        $status = $entries->isEmpty()
            ? ChangelogStatus::Empty
            : ($stale ? ChangelogStatus::Stale : ChangelogStatus::Live);

        return new ChangelogFetchResult($entries, $status, $payload['synced_at'], $failureReason);
    }

    private function staleOrUnavailable(string $cacheKey, ?ChangelogFailureReason $failureReason): ChangelogFetchResult
    {
        if (($payload = $this->cachedPayload($this->staleCacheKey($cacheKey))) !== null) {
            return $this->resultFromPayload($payload, stale: true, failureReason: $failureReason);
        }

        return $this->unavailable($failureReason ?? ChangelogFailureReason::Unavailable);
    }

    private function unavailable(ChangelogFailureReason $failureReason): ChangelogFetchResult
    {
        return new ChangelogFetchResult(collect(), ChangelogStatus::Unavailable, failureReason: $failureReason);
    }

    private function rememberCacheKeys(string ...$cacheKeys): void
    {
        $keys = array_merge(Cache::get(self::CACHE_KEYS_KEY, []), $cacheKeys);

        Cache::put(self::CACHE_KEYS_KEY, array_values(array_unique($keys)), self::STALE_CACHE_TTL);
    }

    private function formatDate(string $date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->format('F j, Y');
        } catch (Exception) {
            return $date;
        }
    }
}
