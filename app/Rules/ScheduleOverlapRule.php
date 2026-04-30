<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Schedule;
use App\Services\GeneralSettingsService;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Translation\PotentiallyTranslatedString;

// use Illuminate\Translation\PotentiallyTranslatedString;

final class ScheduleOverlapRule implements DataAwareRule, ValidationRule
{
    private ?Schedule $conflictingSchedule = null;

    /**
     * @var array<string, mixed>
     */
    private array $data = [];

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
        $schoolYearVariants = $this->resolveSchoolYearVariants($generalSettingsService);
        $semester = $this->resolveSemester($generalSettingsService);

        // dd($attribute);
        foreach ($value as $schedule) {
            if (empty($schedule['room_id'])) {
                continue;
            }

            $query = Schedule::with('class')
                ->where('day_of_week', $schedule['day_of_week'])
                ->where('room_id', $schedule['room_id'])
                ->whereHas('class', function (Builder $query) use ($schoolYearVariants, $semester): void {
                    $query->whereIn('school_year', $schoolYearVariants)
                        ->where('semester', $semester);
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
            $notification = Notification::make()
                ->danger()
                ->title('Schedule Conflict')
                ->body($this->message());
            $authenticatedUser = Auth::guard()->user();

            $notification->send();

            if ($authenticatedUser !== null) {
                $notification->sendToDatabase($authenticatedUser);
            }
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

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
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

    /**
     * @return array<int, string>
     */
    private function resolveSchoolYearVariants(GeneralSettingsService $generalSettingsService): array
    {
        $schoolYear = $this->data['school_year'] ?? $generalSettingsService->getCurrentSchoolYearString();

        if (! is_string($schoolYear) || mb_trim($schoolYear) === '') {
            $schoolYear = $generalSettingsService->getCurrentSchoolYearString();
        }

        $normalizedSchoolYear = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $compactSchoolYear = str_replace(' ', '', $normalizedSchoolYear);

        return array_values(array_unique([$normalizedSchoolYear, $compactSchoolYear]));
    }

    private function resolveSemester(GeneralSettingsService $generalSettingsService): int
    {
        $semester = $this->data['semester'] ?? $generalSettingsService->getCurrentSemester();

        if (is_int($semester)) {
            return $semester;
        }

        if (is_string($semester) && ctype_digit($semester)) {
            return (int) $semester;
        }

        return $generalSettingsService->getCurrentSemester();
    }
}
