<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Classes;
use App\Services\GeneralSettingsService;
use Throwable;

final class ScheduleDelivery
{
    public const string ONLINE_ASYNCHRONOUS_ALIAS = 'online_asynchronous';

    /** @var list<string> */
    public const array MODES = [
        Classes::DELIVERY_FACE_TO_FACE,
        Classes::DELIVERY_ONLINE,
        Classes::DELIVERY_ONLINE_SYNCHRONOUS,
    ];

    /** @var list<string> */
    public const array ACCEPTED_MODES = [
        Classes::DELIVERY_FACE_TO_FACE,
        Classes::DELIVERY_ONLINE,
        self::ONLINE_ASYNCHRONOUS_ALIAS,
        Classes::DELIVERY_ONLINE_SYNCHRONOUS,
    ];

    /** @var list<string> */
    public const array ONLINE_MODES = [
        Classes::DELIVERY_ONLINE,
        self::ONLINE_ASYNCHRONOUS_ALIAS,
        Classes::DELIVERY_ONLINE_SYNCHRONOUS,
    ];

    /** @var list<string> */
    public const array ASYNCHRONOUS_ONLINE_MODES = [
        Classes::DELIVERY_ONLINE,
        self::ONLINE_ASYNCHRONOUS_ALIAS,
    ];

    /** @var array{
     *     default_online_delivery_mode: string,
     *     check_room_conflicts: bool,
     *     check_faculty_conflicts: bool,
     *     check_student_cohort_conflicts: bool,
     *     check_synchronous_online_conflicts: bool,
     *     check_asynchronous_online_conflicts: bool
     * } */
    public const array DEFAULT_POLICY = [
        'default_online_delivery_mode' => Classes::DELIVERY_ONLINE,
        'check_room_conflicts' => true,
        'check_faculty_conflicts' => true,
        'check_student_cohort_conflicts' => true,
        'check_synchronous_online_conflicts' => true,
        'check_asynchronous_online_conflicts' => false,
    ];

    public static function canonicalize(mixed $mode, string $fallback = Classes::DELIVERY_FACE_TO_FACE): string
    {
        if (! is_string($mode)) {
            return $fallback;
        }

        return match (mb_trim($mode)) {
            Classes::DELIVERY_FACE_TO_FACE => Classes::DELIVERY_FACE_TO_FACE,
            Classes::DELIVERY_ONLINE, self::ONLINE_ASYNCHRONOUS_ALIAS => Classes::DELIVERY_ONLINE,
            Classes::DELIVERY_ONLINE_SYNCHRONOUS => Classes::DELIVERY_ONLINE_SYNCHRONOUS,
            default => $fallback,
        };
    }

    /**
     * Normalize known aliases (e.g. online_asynchronous -> online) while leaving
     * invalid values untouched so request validation can still reject them.
     */
    public static function normalizeInput(mixed $mode): mixed
    {
        if (! is_string($mode)) {
            return $mode;
        }

        $trimmed = mb_trim($mode);

        return $trimmed === self::ONLINE_ASYNCHRONOUS_ALIAS
            ? Classes::DELIVERY_ONLINE
            : $trimmed;
    }

    public static function isValidInput(mixed $mode): bool
    {
        return is_string($mode) && in_array(mb_trim($mode), self::ACCEPTED_MODES, true);
    }

    public static function isOnline(mixed $mode): bool
    {
        return is_string($mode) && in_array(mb_trim($mode), self::ONLINE_MODES, true);
    }

    public static function isAsynchronousOnline(mixed $mode): bool
    {
        return is_string($mode) && in_array(mb_trim($mode), self::ASYNCHRONOUS_ONLINE_MODES, true);
    }

    public static function isSynchronousOnline(mixed $mode): bool
    {
        return is_string($mode) && mb_trim($mode) === Classes::DELIVERY_ONLINE_SYNCHRONOUS;
    }

    public static function isFaceToFace(mixed $mode): bool
    {
        return ! self::isOnline($mode);
    }

    public static function label(mixed $mode): string
    {
        if ($mode === 'mixed') {
            return 'Mixed';
        }

        if (self::isSynchronousOnline($mode)) {
            return 'Online (Synchronous)';
        }

        if (self::isAsynchronousOnline($mode)) {
            return 'Online (Asynchronous)';
        }

        return 'Face to face';
    }

    public static function shortLabel(mixed $mode): string
    {
        if ($mode === 'mixed') {
            return 'Mixed';
        }

        if (self::isSynchronousOnline($mode)) {
            return 'Online (Sync)';
        }

        if (self::isAsynchronousOnline($mode)) {
            return 'Online (Async)';
        }

        return 'Face to face';
    }

    public static function locationLabel(mixed $mode, ?string $roomName = null, string $fallback = 'Room TBA'): string
    {
        if (self::isSynchronousOnline($mode)) {
            return 'Online (Sync)';
        }

        if (self::isAsynchronousOnline($mode)) {
            return 'Online';
        }

        return filled($roomName) ? (string) $roomName : $fallback;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return [
            ['value' => Classes::DELIVERY_FACE_TO_FACE, 'label' => 'Face to face'],
            ['value' => Classes::DELIVERY_ONLINE, 'label' => 'Online (Asynchronous)'],
            ['value' => Classes::DELIVERY_ONLINE_SYNCHRONOUS, 'label' => 'Online (Synchronous)'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        return [
            Classes::DELIVERY_FACE_TO_FACE => 'Face to face',
            Classes::DELIVERY_ONLINE => 'Online (Asynchronous)',
            Classes::DELIVERY_ONLINE_SYNCHRONOUS => 'Online (Synchronous)',
        ];
    }

    /**
     * @param  array<string, mixed>|null  $override
     * @return array{
     *     default_online_delivery_mode: string,
     *     check_room_conflicts: bool,
     *     check_faculty_conflicts: bool,
     *     check_student_cohort_conflicts: bool,
     *     check_synchronous_online_conflicts: bool,
     *     check_asynchronous_online_conflicts: bool
     * }
     */
    public static function policy(?array $override = null): array
    {
        if ($override !== null) {
            return self::sanitizePolicy($override);
        }

        try {
            if (app()->bound(GeneralSettingsService::class) || app()->isBooted()) {
                return app(GeneralSettingsService::class)->getSchedulingConfig();
            }
        } catch (Throwable) {
            // Fall back to safe defaults during early boot or isolated unit runs.
        }

        return self::DEFAULT_POLICY;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{
     *     default_online_delivery_mode: string,
     *     check_room_conflicts: bool,
     *     check_faculty_conflicts: bool,
     *     check_student_cohort_conflicts: bool,
     *     check_synchronous_online_conflicts: bool,
     *     check_asynchronous_online_conflicts: bool
     * }
     */
    public static function sanitizePolicy(array $attributes): array
    {
        $rawOnlineMode = $attributes['default_online_delivery_mode'] ?? self::DEFAULT_POLICY['default_online_delivery_mode'];
        $defaultOnlineMode = self::isSynchronousOnline($rawOnlineMode)
            ? Classes::DELIVERY_ONLINE_SYNCHRONOUS
            : Classes::DELIVERY_ONLINE;

        return [
            'default_online_delivery_mode' => $defaultOnlineMode,
            'check_room_conflicts' => (bool) ($attributes['check_room_conflicts'] ?? self::DEFAULT_POLICY['check_room_conflicts']),
            'check_faculty_conflicts' => (bool) ($attributes['check_faculty_conflicts'] ?? self::DEFAULT_POLICY['check_faculty_conflicts']),
            'check_student_cohort_conflicts' => (bool) ($attributes['check_student_cohort_conflicts'] ?? self::DEFAULT_POLICY['check_student_cohort_conflicts']),
            'check_synchronous_online_conflicts' => (bool) ($attributes['check_synchronous_online_conflicts'] ?? self::DEFAULT_POLICY['check_synchronous_online_conflicts']),
            'check_asynchronous_online_conflicts' => (bool) ($attributes['check_asynchronous_online_conflicts'] ?? self::DEFAULT_POLICY['check_asynchronous_online_conflicts']),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $policy
     */
    public static function defaultOnlineMode(?array $policy = null): string
    {
        return self::policy($policy)['default_online_delivery_mode'];
    }

    /**
     * Online classes never reserve physical rooms and never participate in room conflict checks.
     *
     * @param  array<string, mixed>|null  $policy
     */
    public static function checksRoomConflicts(mixed $mode, ?array $policy = null): bool
    {
        if (self::isOnline($mode)) {
            return false;
        }

        return self::policy($policy)['check_room_conflicts'];
    }

    /**
     * Whether a meeting with the given delivery mode participates in time conflict checks
     * (faculty, student cohort, student timetable, enrollment schedule).
     *
     * @param  array<string, mixed>|null  $policy
     */
    public static function checksTimeConflicts(mixed $mode, ?array $policy = null): bool
    {
        $resolved = self::policy($policy);

        if (self::isSynchronousOnline($mode)) {
            return $resolved['check_synchronous_online_conflicts'];
        }

        if (self::isAsynchronousOnline($mode)) {
            return $resolved['check_asynchronous_online_conflicts'];
        }

        return true;
    }

    /**
     * @param  array<string, mixed>|null  $policy
     */
    public static function checksFacultyConflicts(mixed $mode, ?array $policy = null): bool
    {
        $resolved = self::policy($policy);

        return $resolved['check_faculty_conflicts'] && self::checksTimeConflicts($mode, $resolved);
    }

    /**
     * @param  array<string, mixed>|null  $policy
     */
    public static function checksStudentCohortConflicts(mixed $mode, ?array $policy = null): bool
    {
        $resolved = self::policy($policy);

        return $resolved['check_student_cohort_conflicts'] && self::checksTimeConflicts($mode, $resolved);
    }

    /**
     * Delivery modes that participate in SQL time-conflict queries under the active policy.
     *
     * @param  array<string, mixed>|null  $policy
     * @return list<string>
     */
    public static function timeConflictCheckedModes(?array $policy = null): array
    {
        $resolved = self::policy($policy);
        $modes = [Classes::DELIVERY_FACE_TO_FACE];

        if ($resolved['check_synchronous_online_conflicts']) {
            $modes[] = Classes::DELIVERY_ONLINE_SYNCHRONOUS;
        }

        if ($resolved['check_asynchronous_online_conflicts']) {
            $modes[] = Classes::DELIVERY_ONLINE;
            $modes[] = self::ONLINE_ASYNCHRONOUS_ALIAS;
        }

        return $modes;
    }

    /**
     * @param  array<string, mixed>  $block
     * @return array<string, mixed>
     */
    public static function normalizeBlock(array $block, string $default = Classes::DELIVERY_FACE_TO_FACE): array
    {
        if (! array_key_exists('delivery_mode', $block)) {
            $block['delivery_mode'] = self::canonicalize($default);
        } else {
            $block['delivery_mode'] = self::normalizeInput($block['delivery_mode']);
        }

        if (self::isOnline($block['delivery_mode'])) {
            $block['room_id'] = null;
        }

        return $block;
    }

    public static function normalizeSchedules(mixed $schedules, ?Classes $class = null, ?string $default = null, bool $matchLegacy = true): mixed
    {
        if (! is_array($schedules)) {
            return $schedules;
        }

        $default ??= $class?->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE;
        $existing = $class?->schedules()->withTrashed()->orderBy('id')->get()->keyBy('id');
        $claimedIds = collect($schedules)->filter(fn (mixed $block): bool => is_array($block))
            ->pluck('id')->filter(fn (mixed $id): bool => is_numeric($id))->map(fn (mixed $id): int => (int) $id)->all();
        $usedIds = [];

        return array_map(static function (mixed $block) use ($existing, $default, $claimedIds, $matchLegacy, &$usedIds): mixed {
            if (! is_array($block)) {
                return $block;
            }

            $previous = isset($block['id']) && is_scalar($block['id']) ? $existing?->get($block['id']) : null;
            $available = $existing?->filter(fn ($schedule): bool => ! $schedule->trashed()
                && ! in_array($schedule->id, $claimedIds, true)
                && ! in_array($schedule->id, $usedIds, true));
            // Match legacy rows by meeting day before falling back to stable ID order.
            if ($matchLegacy) {
                $previous ??= $available?->first(fn ($schedule): bool => $schedule->day_of_week === ($block['day_of_week'] ?? null)
                    && $schedule->start_time?->format('H:i') === mb_substr((string) ($block['start_time'] ?? ''), 0, 5)
                    && $schedule->end_time?->format('H:i') === mb_substr((string) ($block['end_time'] ?? ''), 0, 5));
                $previous ??= $available?->first(fn ($schedule): bool => $schedule->day_of_week === ($block['day_of_week'] ?? null));
                $previous ??= $available?->first();
            }
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
