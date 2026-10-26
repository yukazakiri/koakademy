<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Classes;

final class ScheduleDelivery
{
    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public static function normalizeBlock(array $block, string $default = Classes::DELIVERY_FACE_TO_FACE): array
    {
        if (! array_key_exists('delivery_mode', $block)) {
            $block['delivery_mode'] = $default;
        }

        if ($block['delivery_mode'] === Classes::DELIVERY_ONLINE) {
            $block['room_id'] = null;
        }

        return $block;
    }

    public static function normalizeSchedules(mixed $schedules, ?Classes $class = null, ?string $default = null): mixed
    {
        if (! is_array($schedules)) {
            return $schedules;
        }

        $default ??= $class?->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE;
        $existing = $class?->schedules()->withTrashed()->orderBy('id')->get()->keyBy('id');
        $claimedIds = collect($schedules)->filter(fn (mixed $block): bool => is_array($block))
            ->pluck('id')->filter(fn (mixed $id): bool => is_numeric($id))->map(fn (mixed $id): int => (int) $id)->all();
        $usedIds = [];

        return array_map(static function (mixed $block) use ($existing, $default, $claimedIds, &$usedIds): mixed {
            if (! is_array($block)) {
                return $block;
            }

            $previous = isset($block['id']) && is_scalar($block['id']) ? $existing?->get($block['id']) : null;
            $available = $existing?->filter(fn ($schedule): bool => ! $schedule->trashed()
                && ! in_array($schedule->id, $claimedIds, true)
                && ! in_array($schedule->id, $usedIds, true));
            // Match legacy rows by meeting day before falling back to stable ID order.
            $previous ??= $available?->first(fn ($schedule): bool => $schedule->day_of_week === ($block['day_of_week'] ?? null)
                && $schedule->start_time?->format('H:i') === mb_substr((string) ($block['start_time'] ?? ''), 0, 5)
                && $schedule->end_time?->format('H:i') === mb_substr((string) ($block['end_time'] ?? ''), 0, 5));
            $previous ??= $available?->first(fn ($schedule): bool => $schedule->day_of_week === ($block['day_of_week'] ?? null));
            $previous ??= $available?->first();
            if ($previous !== null) {
                $usedIds[] = $previous->id;
                if (! array_key_exists('id', $block)) {
                    $block['id'] = $previous->id;
                }
            }

            return self::normalizeBlock($block, $previous?->delivery_mode ?? $default);
        }, $schedules);
    }
}
