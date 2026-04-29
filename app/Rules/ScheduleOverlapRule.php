<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Schedule;
use App\Services\GeneralSettingsService;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

// use Illuminate\Translation\PotentiallyTranslatedString;

final class ScheduleOverlapRule implements ValidationRule
{
    private ?Schedule $conflictingSchedule = null;

    public function __construct(
        private readonly ?int $excludedClassId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string):PotentiallyTranslatedString  $fail
     */
    public function validate(
        string $attribute,
        mixed $value,
        Closure $fail
    ): void {
        $this->conflictingSchedule = null;
        $generalSettingsService = app(GeneralSettingsService::class);
        // dd($attribute);
        foreach ($value as $schedule) {
            if (empty($schedule['room_id'])) {
                continue;
            }

            $query = Schedule::with('class')
                ->where('day_of_week', $schedule['day_of_week'])
                ->where('room_id', $schedule['room_id'])
                ->whereHas('class', function ($query) use ($generalSettingsService): void {
                    $query->where('semester', $generalSettingsService->getCurrentSemester());
                })
                ->where(function ($query) use ($schedule): void {
                    $query
                        ->where(function ($subQuery) use ($schedule): void {
                            // New schedule starts during another schedule
                            $subQuery
                                ->where(
                                    'start_time',
                                    '<=',
                                    $schedule['start_time']
                                )
                                ->where(
                                    'end_time',
                                    '>',
                                    $schedule['start_time']
                                );
                        })
                        ->orWhere(function ($subQuery) use ($schedule): void {
                            // New schedule ends during another schedule
                            $subQuery
                                ->where(
                                    'start_time',
                                    '<',
                                    $schedule['end_time']
                                )
                                ->where(
                                    'end_time',
                                    '>=',
                                    $schedule['end_time']
                                );
                        })
                        ->orWhere(function ($subQuery) use ($schedule): void {
                            // New schedule completely overlaps another schedule
                            $subQuery
                                ->where(
                                    'start_time',
                                    '>=',
                                    $schedule['start_time']
                                )
                                ->where(
                                    'end_time',
                                    '<=',
                                    $schedule['end_time']
                                );
                        });
                });

            $scheduleIdForExclusion = $this->resolveScheduleIdForExclusion($schedule['id'] ?? null);

            if ($scheduleIdForExclusion !== null) {
                $query->whereKeyNot($scheduleIdForExclusion);
            }

            if ($this->excludedClassId !== null) {
                $query->where('class_id', '!=', $this->excludedClassId);
            }

            $existingSchedule = $query->first();

            if ($existingSchedule) {
                $this->conflictingSchedule = $existingSchedule;
                break;
            }
        }

        if ($this->conflictingSchedule) {
            $fail($this->message());
            Notification::make()
                ->danger()
                ->title('Schedule Conflict')
                ->body($this->message())
                ->send()
                ->sendToDatabase(auth()->user());
        }
    }

    public function message(): string
    {
        if ($this->conflictingSchedule) {
            $className = $this->conflictingSchedule->class
                ? $this->conflictingSchedule->class->subject_code
                : 'Unknown Class';

            return sprintf("The schedule you are trying to add conflicts with another class on '%s' on ", $className).
                $this->conflictingSchedule->day_of_week.
                ' in '.
                $this->conflictingSchedule->room->name.
                ' from '.
                $this->conflictingSchedule->start_time->format('h:i A').
                ' to '.
                $this->conflictingSchedule->end_time->format('h:i A').
                '.';
        }

        return 'The schedule conflicts with another schedule. ';
    }

    private function resolveScheduleIdForExclusion(mixed $scheduleId): ?int
    {
        if (is_int($scheduleId)) {
            return $scheduleId > 0 ? $scheduleId : null;
        }

        if (! is_string($scheduleId)) {
            return null;
        }

        $trimmedScheduleId = mb_trim($scheduleId);

        if ($trimmedScheduleId === '' || ! ctype_digit($trimmedScheduleId)) {
            return null;
        }

        $resolvedScheduleId = (int) $trimmedScheduleId;

        return $resolvedScheduleId > 0 ? $resolvedScheduleId : null;
    }
}
