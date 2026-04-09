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

    private const int CACHE_TTL = 3600; // 1 hour

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
        return Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function () use ($limit, $includePrereleases) {
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
     * Clear the changelog cache.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Fetch GitHub releases from API.
     */
    private function fetchGitHubReleases(int $limit): array
    {
        $repo = $this->githubRepo ?? config('services.github.repo');
        $token = $this->githubToken ?? config('services.github.token');

        if (! $repo || ! $token) {
            Log::warning('ChangelogService: Missing GitHub configuration', [
                'repo' => $repo ?: 'not set',
                'token' => $token ? 'set (hidden)' : 'not set',
            ]);

            return [];
        }

        try {
            Log::info('ChangelogService: Fetching releases from GitHub', [
                'repo' => $repo,
                'limit' => $limit,
            ]);

            $response = Http::timeout(10)
                ->withHeaders([
                    'Accept' => 'application/vnd.github.v3+json',
                    'Authorization' => "Bearer {$token}",
                ])
                ->get("https://api.github.com/repos/{$repo}/releases", [
                    'per_page' => $limit,
                ]);

            if (! $response->successful()) {
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
