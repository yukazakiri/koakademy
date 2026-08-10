<?php

declare(strict_types=1);

namespace App\Data\Changelog;

use App\Enums\ChangelogFailureReason;
use App\Enums\ChangelogStatus;
use Illuminate\Support\Collection;

final readonly class ChangelogFetchResult
{
    /**
     * @param  Collection<int, array{title: string, version: string, date: string, published_at: string, type: string, prerelease: bool, source: string, changes: array<int, array{type: string, description: string}>, github_url: string|null}>  $entries
     */
    public function __construct(
        public Collection $entries,
        public ChangelogStatus $status,
        public ?string $lastSyncedAt = null,
        public ?ChangelogFailureReason $failureReason = null,
    ) {}

    public function isAvailable(): bool
    {
        return $this->status !== ChangelogStatus::Unavailable;
    }
}
