<?php

declare(strict_types=1);

namespace App\Services\Scheduling;

use App\Models\Classes;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ClassScheduleChangeNotificationService;
use App\Support\ScheduleDelivery;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SchedulingService
{
    public function __construct(
        private ClassScheduleChangeNotificationService $notificationService,
    ) {}

    /**
     * Synchronize all schedule blocks for a class in place without delete-all ID churn.
     *
     * @param  array<int, array<string, mixed>>  $incomingSchedules
     * @param  array<int, array<string, mixed>>|null  $oldSnapshot
     * @return Collection<int, Schedule>
     */
    public function syncClassSchedules(
        Classes $class,
        array $incomingSchedules,
        ?User $actor = null,
        ?array $oldSnapshot = null,
    ): Collection {
        $oldSnapshot ??= $this->notificationService->snapshot($class);
        $incomingSchedules = ScheduleDelivery::normalizeSchedules($incomingSchedules, $class);

        $synced = DB::transaction(function () use ($class, $incomingSchedules): Collection {
            $existingSchedules = $class->schedules()->withTrashed()->get();
            $existingById = $existingSchedules->keyBy('id');
            $availableExisting = $existingSchedules->filter(fn (Schedule $s): bool => ! $s->trashed())->values();

            $usedIds = [];
            $result = collect();

            foreach ($incomingSchedules as $scheduleData) {
                $rawId = $scheduleData['id'] ?? null;
                $numericId = is_int($rawId)
                    ? $rawId
                    : (is_string($rawId) && ctype_digit(mb_trim($rawId)) ? (int) mb_trim($rawId) : null);

                $target = null;

                if ($numericId !== null && $existingById->has($numericId) && ! in_array($numericId, $usedIds, true)) {
                    $target = $existingById->get($numericId);
                } else {
                    // Reuse an available active row that isn't explicitly claimed by another incoming numeric ID
                    $claimedNumericIds = collect($incomingSchedules)
                        ->pluck('id')
                        ->map(fn (mixed $id): ?int => is_int($id) ? $id : (is_string($id) && ctype_digit(mb_trim($id)) ? (int) mb_trim($id) : null))
                        ->filter()
                        ->all();

                    $target = $availableExisting->first(
                        fn (Schedule $s): bool => ! in_array($s->id, $usedIds, true)
                            && ! in_array($s->id, $claimedNumericIds, true)
                    );
                }

                $scheduleData = ScheduleDelivery::normalizeBlock($scheduleData, $target?->delivery_mode ?? $class->delivery_mode);
                $attributes = [
                    'day_of_week' => (string) $scheduleData['day_of_week'],
                    'start_time' => $this->normalizeTimeString((string) $scheduleData['start_time']),
                    'end_time' => $this->normalizeTimeString((string) $scheduleData['end_time']),
                    'delivery_mode' => $scheduleData['delivery_mode'],
                    'room_id' => isset($scheduleData['room_id']) && $scheduleData['room_id'] !== ''
                        ? (int) $scheduleData['room_id']
                        : null,
                    'component_type' => isset($scheduleData['component_type']) && is_string($scheduleData['component_type'])
                        ? $scheduleData['component_type']
                        : ($target?->component_type ?? 'lecture'),
                    'pattern_group_id' => $scheduleData['pattern_group_id'] ?? $target?->pattern_group_id,
                    'status' => isset($scheduleData['status']) && is_string($scheduleData['status'])
                        ? $scheduleData['status']
                        : ($target?->status ?? 'published'),
                ];

                if (array_key_exists('subject_id', $scheduleData)) {
                    $attributes['subject_id'] = $scheduleData['subject_id'] ? (int) $scheduleData['subject_id'] : null;
                }

                if ($target instanceof Schedule) {
                    $target->forceFill($attributes);
                    if ($target->trashed()) {
                        $target->restore();
                    }
                    $target->save();
                    $usedIds[] = $target->id;
                    $result->push($target);
                } else {
                    /** @var Schedule $created */
                    $created = $class->schedules()->create($attributes);
                    $usedIds[] = $created->id;
                    $result->push($created);
                }
            }

            if ($usedIds === []) {
                $class->schedules()->delete();
            } else {
                $class->schedules()->whereNotIn('id', $usedIds)->delete();
            }

            return $result;
        });

        $class->unsetRelation('schedules');
        $class->unsetRelation('Schedule');

        $this->notificationService->notifyIfChanged($class, $oldSnapshot, $actor);

        return $synced;
    }

    /**
     * Update a single schedule block (and optionally its linked pattern group).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function updateScheduleBlock(
        Schedule $schedule,
        array $attributes,
        ?User $actor = null,
        bool $applyToPatternGroup = false,
    ): Schedule {
        $class = $schedule->class?->fresh();
        $attributes = ScheduleDelivery::normalizeBlock($attributes, $schedule->delivery_mode);
        $oldSnapshot = $class ? $this->notificationService->snapshot($class) : [];

        DB::transaction(function () use ($schedule, $attributes, $applyToPatternGroup): void {
            $updateData = [
                'delivery_mode' => $attributes['delivery_mode'],
                'day_of_week' => (string) ($attributes['day_of_week'] ?? $schedule->day_of_week),
                'start_time' => $this->normalizeTimeString((string) ($attributes['start_time'] ?? $schedule->start_time?->format('H:i'))),
                'end_time' => $this->normalizeTimeString((string) ($attributes['end_time'] ?? $schedule->end_time?->format('H:i'))),
            ];

            if (array_key_exists('room_id', $attributes)) {
                $updateData['room_id'] = $attributes['room_id'] !== null && $attributes['room_id'] !== ''
                    ? (int) $attributes['room_id']
                    : null;
            }

            if (array_key_exists('component_type', $attributes) && is_string($attributes['component_type'])) {
                $updateData['component_type'] = $attributes['component_type'];
            }

            if (array_key_exists('status', $attributes) && is_string($attributes['status'])) {
                $updateData['status'] = $attributes['status'];
            }

            $schedule->update($updateData);

            if ($applyToPatternGroup && filled($schedule->pattern_group_id)) {
                $sharedUpdates = [
                    'start_time' => $updateData['start_time'],
                    'end_time' => $updateData['end_time'],
                ];

                if (array_key_exists('component_type', $updateData)) {
                    $sharedUpdates['component_type'] = $updateData['component_type'];
                }

                // A recurring time pattern can contain both online and campus meetings.
                // Only the selected meeting changes its delivery mode and location.
                Schedule::query()
                    ->where('class_id', $schedule->class_id)
                    ->where('pattern_group_id', $schedule->pattern_group_id)
                    ->whereKeyNot($schedule->id)
                    ->update($sharedUpdates);
            }
        });

        $schedule->refresh();
        $schedule->load('room');

        if ($class instanceof Classes) {
            $class->unsetRelation('schedules');
            $class->unsetRelation('Schedule');
            $this->notificationService->notifyIfChanged($class, $oldSnapshot, $actor);
        }

        return $schedule;
    }

    /**
     * Place one or more schedule blocks (e.g. MWF / TTh / Single) onto a class.
     *
     * @param  list<string>  $days
     * @return Collection<int, Schedule>
     */
    public function placeSchedulePattern(
        Classes $class,
        array $days,
        string $startTime,
        string $endTime,
        ?int $roomId = null,
        string $componentType = 'lecture',
        string $status = 'published',
        ?User $actor = null,
        ?string $deliveryMode = null,
    ): Collection {
        $oldSnapshot = $this->notificationService->snapshot($class);
        $uniqueDays = array_values(array_unique(array_filter($days)));
        $patternGroupId = count($uniqueDays) > 1 ? (string) Str::uuid() : null;

        $deliveryMode ??= $class->delivery_mode;
        $created = DB::transaction(function () use ($class, $uniqueDays, $startTime, $endTime, $roomId, $componentType, $status, $patternGroupId, $deliveryMode): Collection {
            $blocks = collect();
            $effectiveRoomId = $deliveryMode === Classes::DELIVERY_ONLINE ? null : $roomId;

            foreach ($uniqueDays as $day) {
                /** @var Schedule $schedule */
                $schedule = $class->schedules()->create([
                    'day_of_week' => $day,
                    'start_time' => $this->normalizeTimeString($startTime),
                    'end_time' => $this->normalizeTimeString($endTime),
                    'room_id' => $effectiveRoomId,
                    'delivery_mode' => $deliveryMode,
                    'subject_id' => $class->subject_id ? (int) $class->subject_id : null,
                    'component_type' => $componentType,
                    'pattern_group_id' => $patternGroupId,
                    'status' => $status,
                ]);
                $schedule->load('room');
                $blocks->push($schedule);
            }

            return $blocks;
        });

        $class->unsetRelation('schedules');
        $class->unsetRelation('Schedule');
        $this->notificationService->notifyIfChanged($class, $oldSnapshot, $actor);

        return $created;
    }

    /**
     * Delete a schedule block (or its entire recurring pattern group).
     */
    public function deleteScheduleBlock(
        Schedule $schedule,
        ?User $actor = null,
        bool $deletePatternGroup = false,
    ): void {
        $class = $schedule->class;
        $oldSnapshot = $class ? $this->notificationService->snapshot($class) : [];

        DB::transaction(function () use ($schedule, $deletePatternGroup): void {
            if ($deletePatternGroup && filled($schedule->pattern_group_id)) {
                Schedule::query()
                    ->where('class_id', $schedule->class_id)
                    ->where('pattern_group_id', $schedule->pattern_group_id)
                    ->delete();

                return;
            }

            $schedule->delete();
        });

        if ($class instanceof Classes) {
            $class->unsetRelation('schedules');
            $class->unsetRelation('Schedule');
            $this->notificationService->notifyIfChanged($class, $oldSnapshot, $actor);
        }
    }

    /**
     * Copy all active schedule blocks from a source class to a target class.
     *
     * @return Collection<int, Schedule>
     */
    public function copyClassSchedules(Classes $source, Classes $target): Collection
    {
        $sourceSchedules = $source->schedules()->get();
        if ($sourceSchedules->isEmpty()) {
            return collect();
        }

        $patternGroupMap = [];

        return DB::transaction(function () use ($sourceSchedules, $target, &$patternGroupMap): Collection {
            $copied = collect();

            foreach ($sourceSchedules as $schedule) {
                $newPatternGroupId = null;
                if (filled($schedule->pattern_group_id)) {
                    $oldGroup = (string) $schedule->pattern_group_id;
                    $patternGroupMap[$oldGroup] ??= (string) Str::uuid();
                    $newPatternGroupId = $patternGroupMap[$oldGroup];
                }

                /** @var Schedule $newSchedule */
                $newSchedule = $target->schedules()->create([
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i:s'),
                    'end_time' => $schedule->end_time?->format('H:i:s'),
                    'room_id' => $schedule->room_id,
                    'delivery_mode' => $schedule->delivery_mode,
                    'subject_id' => $schedule->subject_id ?? $target->subject_id,
                    'component_type' => $schedule->component_type ?? 'lecture',
                    'pattern_group_id' => $newPatternGroupId,
                    'status' => $schedule->status ?? 'published',
                ]);

                $copied->push($newSchedule);
            }

            return $copied;
        });
    }

    private function normalizeTimeString(string $time): string
    {
        $trimmed = mb_trim($time);
        if (preg_match('/^\d{2}:\d{2}$/', $trimmed) === 1) {
            return $trimmed.':00';
        }

        return $trimmed;
    }
}
