<?php

declare(strict_types=1);

namespace Modules\Announcement\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Modules\Announcement\Models\Announcement;

final class AnnouncementDataService
{
    public function paginateForAdministration(int $perPage = 15): LengthAwarePaginator
    {
        return Announcement::query()
            ->with('creator:id,name')
            ->latest()
            ->paginate($perPage)
            ->through(fn (Announcement $announcement): array => $this->mapForAdministration($announcement));
    }

    /**
     * @return array<int, array{id: int, title: string, content: string, type: string, priority: string, date: string, is_read: bool}>
     */
    public function getPortalIndexData(): array
    {
        return $this->applyPortalOrdering($this->visibleQuery())
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'type' => (string) ($announcement->type ?? 'info'),
                'priority' => (string) ($announcement->priority ?? 'medium'),
                'date' => ($announcement->published_at ?? $announcement->created_at)?->format('M d, Y') ?? '',
                'is_read' => false,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, content: string, date: string, type: string}>
     */
    public function getDashboardItems(int $limit = 5): array
    {
        return $this->applyPortalOrdering($this->visibleQuery())
            ->limit($limit)
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => Str::of(strip_tags($announcement->content))->squish()->toString(),
                'date' => ($announcement->published_at ?? $announcement->created_at)?->format('M d, Y') ?? '',
                'type' => (string) ($announcement->type ?? 'info'),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, title: string, content: string, type: string, priority: string, display_mode: string, requires_acknowledgment: bool, link: string|null, is_active: bool, starts_at: string|null, ends_at: string|null}>
     */
    public function getSharedBannerAnnouncements(): array
    {
        return $this->applyBannerOrdering($this->visibleQuery())
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'type' => (string) ($announcement->type ?? 'info'),
                'priority' => (string) ($announcement->priority ?? 'medium'),
                'display_mode' => (string) ($announcement->display_mode ?? 'banner'),
                'requires_acknowledgment' => (bool) $announcement->requires_acknowledgment,
                'link' => $announcement->link,
                'is_active' => $announcement->isActive(),
                'starts_at' => $announcement->starts_at?->toIso8601String(),
                'ends_at' => ($announcement->ends_at ?? $announcement->expires_at)?->toIso8601String(),
            ])
            ->all();
    }

    private function visibleQuery(): Builder
    {
        return Announcement::query()
            ->global()
            ->published()
            ->active();
    }

    private function applyPortalOrdering(Builder $query): Builder
    {
        if (Announcement::schemaHasCachedColumn('published_at')) {
            $query->orderByDesc('published_at');
        }

        return $query->orderByDesc('created_at');
    }

    private function applyBannerOrdering(Builder $query): Builder
    {
        if (Announcement::schemaHasCachedColumn('priority')) {
            $query->orderByDesc('priority');
        }

        return $this->applyPortalOrdering($query);
    }

    /**
     * @return array{
     *     id: int,
     *     title: string,
     *     content: string,
     *     type: string,
     *     priority: string,
     *     display_mode: string,
     *     requires_acknowledgment: bool,
     *     link: string|null,
     *     is_active: bool,
     *     starts_at: string|null,
     *     ends_at: string|null,
     *     creator: array{id: int|null, name: string}|null,
     *     created_at: string|null
     * }
     */
    private function mapForAdministration(Announcement $announcement): array
    {
        return [
            'id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'type' => $announcement->type,
            'priority' => (string) ($announcement->priority ?? 'medium'),
            'display_mode' => (string) ($announcement->display_mode ?? 'banner'),
            'requires_acknowledgment' => (bool) $announcement->requires_acknowledgment,
            'link' => $announcement->link,
            'is_active' => (bool) ($announcement->is_active ?? $announcement->isActive()),
            'starts_at' => ($announcement->starts_at ?? $announcement->published_at)?->toIso8601String(),
            'ends_at' => ($announcement->ends_at ?? $announcement->expires_at)?->toIso8601String(),
            'creator' => $announcement->creator
                ? [
                    'id' => $announcement->creator->id,
                    'name' => $announcement->creator->name,
                ]
                : null,
            'created_at' => $announcement->created_at?->toIso8601String(),
        ];
    }
}
