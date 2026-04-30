<?php

declare(strict_types=1);

namespace App\Services;

use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final readonly class ChangelogService
{
    private const string CACHE_KEY = 'changelog_entries';

    private const string RATE_LIMIT_CACHE_KEY = 'changelog_github_rate_limited';

    private const int CACHE_TTL = 3600; // 1 hour

    private const int RATE_LIMIT_TTL = 600; // 10 minutes

    public function __construct(
        private ?string $githubRepo = null,
        private ?string $githubToken = null,
    ) {}

    /**
     * Get changelog entries from GitHub releases.
     *
     * @return Collection<int, array{
     *     version: string,
     *     date: string,
     *     type: string,
     *     changes: array<int, array{type: string, description: string}>,
     *     github_url: string|null
     * }>
     */
    public function getChangelog(int $limit = 20, bool $includePrereleases = false): Collection
    {
        $cacheKey = self::CACHE_KEY.".limit:{$limit}.prereleases:".($includePrereleases ? '1' : '0');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($limit, $includePrereleases) {
            Log::info('ChangelogService: getChangelog called', [
                'limit' => $limit,
                'includePrereleases' => $includePrereleases,
            ]);

            $releases = $this->fetchGitHubReleases($limit);

            Log::info('ChangelogService: Fetched releases from fetchGitHubReleases', [
                'count' => count($releases),
            ]);

            $filtered = collect($releases)
                ->filter(function (array $release) use ($includePrereleases): bool {
                    $shouldInclude = $includePrereleases || ! ($release['prerelease'] ?? false);
                    Log::info('ChangelogService: Filtering release', [
                        'tag' => $release['tag_name'] ?? 'unknown',
                        'prerelease' => $release['prerelease'] ?? null,
                        'includePrereleases' => $includePrereleases,
                        'shouldInclude' => $shouldInclude,
                    ]);

                    return $shouldInclude;
                })
                ->values();

            Log::info('ChangelogService: After filtering', [
                'filtered_count' => $filtered->count(),
            ]);

            return $filtered->map(function (array $release): array {
                $version = mb_ltrim($release['tag_name'], 'v');

                return [
                    'version' => $version,
                    'date' => $this->formatDate($release['published_at'] ?? $release['created_at']),
                    'type' => $this->determineVersionType($version),
                    'changes' => $this->parseGitHubReleaseBody($release['body'] ?? ''),
                    'github_url' => $release['html_url'],
                ];
            });
        });
    }

    /**
     * Get changelog entries formatted for the Filament Feature Showcase config.
     *
     * Only includes stable releases (no pre-releases) and maps GitHub release
     * data into the title/description/features structure the showcase modal expects.
     *
     * @return array<string, array{title: string, description: string, features: array<int, array{icon: string, title: string, description: string}>}>
     */
    public function getShowcaseChangelog(): array
    {
        return Cache::remember('showcase_changelog', self::CACHE_TTL, function () {
            $releases = $this->getChangelog(limit: 30, includePrereleases: false);

            if ($releases->isEmpty()) {
                return config('filament-feature-showcase.changelog', []);
            }

            return $releases->mapWithKeys(function (array $release): array {
                $version = $release['version'];
                $date = $release['date'];
                $type = $release['type'];

                $features = $this->buildShowcaseFeatures($release['changes']);

                if ($features === []) {
                    $features = [
                        [
                            'icon' => 'heroicon-o-arrow-path',
                            'title' => 'Improvements',
                            'description' => 'Various improvements and updates.',
                        ],
                    ];
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
        return Cache::remember('latest_stable_version', self::CACHE_TTL, function (): ?string {
            $releases = $this->getChangelog(limit: 1, includePrereleases: false);

            $first = $releases->first();

            return $first['version'] ?? null;
        });
    }

    /**
     * Clear the changelog cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::RATE_LIMIT_CACHE_KEY);
        Cache::forget('showcase_changelog');
        Cache::forget('latest_stable_version');
    }

    /**
     * Build showcase-ready feature entries from parsed GitHub changes.
     *
     * Cleans up raw GitHub content (URLs, @mentions, PR refs) and produces
     * concise title + description pairs that fit the showcase modal.
     *
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
                    $raw = $group->first()['description'];
                    $cleaned = $this->cleanShowcaseText($raw);
                    $short = $this->summarizeShowcaseTitle($cleaned);

                    return [[
                        'icon' => $icon,
                        'title' => $short,
                        'description' => $cleaned,
                    ]];
                }

                $summaries = $group->map(fn (array $change): string => $this->summarizeShowcaseTitle($this->cleanShowcaseText($change['description'])))->filter()->values();

                return [[
                    'icon' => $icon,
                    'title' => $typeLabel,
                    'description' => $summaries->implode("\n"),
                ]];
            })
            ->values()
            ->all();
    }

    /**
     * Clean raw GitHub release text for the showcase modal.
     * Strips URLs, @mentions, PR/commit references, and extra whitespace.
     */
    private function cleanShowcaseText(string $text): string
    {
        // Remove full URLs (http/https)
        $text = preg_replace('/https?:\/\/\S+/', '', $text);
        // Remove @mentions
        $text = preg_replace('/@[\w-]+/', '', (string) $text);
        // Remove PR references like (#123)
        $text = preg_replace('/\(#\d+\)/', '', (string) $text);
        // Remove commit hash references
        $text = preg_replace('/\b[0-9a-f]{7,40}\b/', '', (string) $text);
        // Remove markdown bold/italic
        $text = preg_replace('/[*_]+/', '', (string) $text);
        // Collapse whitespace
        $text = preg_replace('/\s+/', ' ', mb_trim($text));
        // Remove trailing punctuation artifacts
        $text = mb_rtrim($text, ' ,;-');

        return $text;
    }

    /**
     * Create a short title from a cleaned description.
     * Takes the first sentence or truncates to a reasonable length.
     */
    private function summarizeShowcaseTitle(string $cleanedText): string
    {
        if ($cleanedText === '') {
            return '';
        }

        // Take first sentence (up to period, colon, or semicolon)
        $short = preg_match('/^(.+?)[.:;]/', $cleanedText, $match) ? mb_trim($match[1]) : $cleanedText;

        // Cap at 60 chars
        if (mb_strlen($short) > 60) {
            $short = mb_substr($short, 0, 57).'...';
        }

        return ucfirst($short);
    }

    /**
     * Fetch GitHub releases from API.
     */
    private function fetchGitHubReleases(int $limit): array
    {
        $repo = $this->githubRepo ?? config('services.github.repo');
        $token = $this->githubToken ?? config('services.github.token');

        if (! $repo) {
            Log::warning('ChangelogService: Missing GitHub repository configuration');

            return [];
        }

        if (Cache::get(self::RATE_LIMIT_CACHE_KEY) === true) {
            Log::warning('ChangelogService: Skipping GitHub call due to recent rate limit', [
                'retry_after_seconds' => self::RATE_LIMIT_TTL,
            ]);

            return [];
        }

        try {
            Log::info('ChangelogService: Fetching releases from GitHub', [
                'repo' => $repo,
                'limit' => $limit,
                'authenticated' => (bool) $token,
            ]);

            $headers = [
                'Accept' => 'application/vnd.github.v3+json',
                'User-Agent' => config('app.name', 'Laravel').'/changelog-service',
            ];

            if ($token) {
                $headers['Authorization'] = "Bearer {$token}";
            }

            $response = Http::timeout(10)
                ->retry(2, 200)
                ->withHeaders($headers)
                ->get("https://api.github.com/repos/{$repo}/releases", [
                    'per_page' => $limit,
                ]);

            if (! $response->successful()) {
                if ($response->status() === 403 && str_contains(mb_strtolower($response->body()), 'rate limit exceeded')) {
                    Cache::put(self::RATE_LIMIT_CACHE_KEY, true, self::RATE_LIMIT_TTL);

                    Log::warning('ChangelogService: GitHub API rate limit exceeded', [
                        'status' => $response->status(),
                        'has_token' => (bool) $token,
                        'repo' => $repo,
                    ]);

                    return [];
                }

                Log::error('ChangelogService: GitHub API request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return [];
            }

            $data = $response->json();
            Log::info('ChangelogService: Successfully fetched releases', [
                'count' => count($data),
                'first_release' => $data[0]['tag_name'] ?? 'none',
            ]);

            return $data;
        } catch (Exception $e) {
            Log::error('ChangelogService: Exception while fetching releases', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Parse GitHub release body into changes.
     *
     * @return array<int, array{type: string, description: string}>
     */
    private function parseGitHubReleaseBody(string $body): array
    {
        $changes = [];
        $lines = explode("\n", $body);
        $currentType = 'improvement';

        foreach ($lines as $line) {
            $line = mb_trim($line);
            if ($line === '') {
                continue;
            }
            if ($line === '0') {
                continue;
            }

            // Check for section headers
            if (preg_match('/^##?\s*(Features?|New|Added)/i', $line)) {
                $currentType = 'feature';

                continue;
            }
            if (preg_match('/^##?\s*(Bug\s*Fixes?|Fixed|Fixes)/i', $line)) {
                $currentType = 'fix';

                continue;
            }
            if (preg_match('/^##?\s*(Breaking|Breaking\s*Changes?)/i', $line)) {
                $currentType = 'breaking';

                continue;
            }
            if (preg_match('/^##?\s*(Security)/i', $line)) {
                $currentType = 'security';

                continue;
            }
            if (preg_match('/^##?\s*(Improvements?|Enhanced?|Changed?)/i', $line)) {
                $currentType = 'improvement';

                continue;
            }

            // Parse list items
            if (preg_match('/^[-*]\s+(.+)$/', $line, $matches)) {
                $changes[] = [
                    'type' => $currentType,
                    'description' => ucfirst(mb_trim($matches[1])),
                ];
            }
        }

        return $changes;
    }

    /**
     * Determine version type from version string.
     */
    private function determineVersionType(string $version): string
    {
        $parts = explode('.', $version);

        if (count($parts) < 3) {
            return 'major';
        }

        // Check if it's a major version (x.0.0)
        if ($parts[1] === '0' && $parts[2] === '0') {
            return 'major';
        }

        // Check if it's a minor version (x.y.0)
        if ($parts[2] === '0') {
            return 'minor';
        }

        return 'patch';
    }

    /**
     * Format date string to readable format.
     */
    private function formatDate(string $date): string
    {
        try {
            return \Carbon\Carbon::parse($date)->format('F j, Y');
        } catch (Exception) {
            return $date;
        }
    }
}
