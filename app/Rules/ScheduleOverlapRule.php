<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Classes;
use App\Models\Schedule;
use App\Services\ClassCurriculumPlacementService;
use App\Services\GeneralSettingsService;
use App\Services\Scheduling\ScheduleConflictService;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Translation\PotentiallyTranslatedString;

final class ScheduleOverlapRule implements DataAwareRule, ValidationRule
{
    private ?Schedule $conflictingSchedule = null;

    private string $conflictType = 'room';

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
        if (! is_array($value) || $value === []) {
            return;
        }

        $this->conflictingSchedule = null;
        $this->conflictType = 'room';
        $existingClass = $this->excludedClassId !== null ? Classes::query()->find($this->excludedClassId) : null;
        $class = $existingClass instanceof Classes ? clone $existingClass : new Classes;
        $attributes = Arr::only($this->data, [
            'delivery_mode', 'faculty_id', 'school_year', 'semester', 'classification',
            'section', 'academic_year', 'course_codes', 'shs_strand_id', 'grade_level',
            'subject_id', 'subject_ids',
        ]);

        if (($attributes['classification'] ?? $class->classification) === 'college') {
            // Conflict checks must use the same authoritative year that is persisted.
            $attributes = app(ClassCurriculumPlacementService::class)->normalizeCollegeAttributes($attributes, $existingClass);
        }

        $class->fill($attributes);

        $generalSettingsService = app(GeneralSettingsService::class);
        $schoolYearVariants = $this->resolveSchoolYearVariants($generalSettingsService, $class);
        $semester = $this->resolveSemester($generalSettingsService, $class);
        $facultyId = is_string($class->faculty_id) && mb_trim($class->faculty_id) !== ''
            ? mb_trim($class->faculty_id)
            : null;

        foreach ($value as $schedule) {
            if (! is_array($schedule) || empty($schedule['day_of_week']) || empty($schedule['start_time']) || empty($schedule['end_time'])) {
                continue;
            }

            $scheduleIdForExclusion = $this->resolveScheduleIdForExclusion($schedule['id'] ?? null);

            if (! $class->isOnline() && ! empty($schedule['room_id'])) {
                $roomConflict = $this->buildOverlapBaseQuery($schedule, $schoolYearVariants, $semester, $scheduleIdForExclusion)
                    ->where('room_id', $schedule['room_id'])
                    ->first();

                if ($roomConflict instanceof Schedule) {
                    $this->conflictingSchedule = $roomConflict;
                    $this->conflictType = 'room';
                    break;
                }
            }

            if ($facultyId !== null) {
                $facultyConflict = $this->buildOverlapBaseQuery($schedule, $schoolYearVariants, $semester, $scheduleIdForExclusion)
                    ->whereHas('class', function (Builder $query) use ($facultyId): void {
                        $query->where('faculty_id', $facultyId);
                    })
                    ->first();

                if ($facultyConflict instanceof Schedule) {
                    $this->conflictingSchedule = $facultyConflict;
                    $this->conflictType = 'faculty';
                    break;
                }
            }

            if (filled($class->section) && filled($class->classification)) {
                $cohortConflict = $this->buildOverlapBaseQuery($schedule, $schoolYearVariants, $semester, $scheduleIdForExclusion)
                    ->whereHas('class', fn (Builder $query) => $query
                        ->where('section', $class->section)
                        ->where('classification', $class->classification))
                    ->get()
                    ->first(fn (Schedule $existing): bool => $existing->class instanceof Classes
                        && app(ScheduleConflictService::class)->sharesStudentCohort($class, $existing->class));

                if ($cohortConflict instanceof Schedule) {
                    $this->conflictingSchedule = $cohortConflict;
                    $this->conflictType = 'section';
                    break;
                }
            }
        }

        if ($this->conflictingSchedule instanceof Schedule) {
            $fail($this->message());
        }
    }

    public function message(): string
    {
        if ($this->conflictingSchedule instanceof Schedule) {
            $className = $this->conflictingSchedule->class
                ? $this->conflictingSchedule->class->subject_code
                : 'Unknown Class';

            if ($this->conflictType === 'faculty') {
                return sprintf("The assigned faculty member is already scheduled for '%s' on ", $className).
                    $this->conflictingSchedule->day_of_week.
                    ' from '.
                    $this->conflictingSchedule->start_time->format('h:i A').
                    ' to '.
                    $this->conflictingSchedule->end_time->format('h:i A').
                    '.';
            }

            if ($this->conflictType === 'section') {
                return sprintf("The student cohort is already scheduled for '%s' on %s from %s to %s.",
                    $className,
                    $this->conflictingSchedule->day_of_week,
                    $this->conflictingSchedule->start_time->format('h:i A'),
                    $this->conflictingSchedule->end_time->format('h:i A'),
                );
            }

            $roomName = $this->conflictingSchedule->room?->name ?? 'the selected room';

            return sprintf("The schedule you are trying to add conflicts with another class on '%s' on ", $className).
                $this->conflictingSchedule->day_of_week.
                ' in '.
                $roomName.
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

    /**
     * @param  array<string, mixed>  $schedule
     * @param  array<int, string>  $schoolYearVariants
     */
    private function buildOverlapBaseQuery(
        array $schedule,
        array $schoolYearVariants,
        int|string $semester,
        ?int $scheduleIdForExclusion,
    ): Builder {
        $query = Schedule::with(['class', 'room'])
            ->where('day_of_week', $schedule['day_of_week'])
            ->whereHas('class', function (Builder $query) use ($schoolYearVariants, $semester): void {
                $query->whereIn('school_year', $schoolYearVariants)
                    ->where('semester', $semester);
            })
            ->where(function ($query) use ($schedule): void {
                $query
                    ->where(function ($subQuery) use ($schedule): void {
                        $subQuery
                            ->where('start_time', '<=', $schedule['start_time'])
                            ->where('end_time', '>', $schedule['start_time']);
                    })
                    ->orWhere(function ($subQuery) use ($schedule): void {
                        $subQuery
                            ->where('start_time', '<', $schedule['end_time'])
                            ->where('end_time', '>=', $schedule['end_time']);
                    })
                    ->orWhere(function ($subQuery) use ($schedule): void {
                        $subQuery
                            ->where('start_time', '>=', $schedule['start_time'])
                            ->where('end_time', '<=', $schedule['end_time']);
                    });
            });

        if ($scheduleIdForExclusion !== null) {
            $query->whereKeyNot($scheduleIdForExclusion);
        }

        if ($this->excludedClassId !== null) {
            $query->where('class_id', '!=', $this->excludedClassId);
        }

        return $query;
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
    private function resolveSchoolYearVariants(GeneralSettingsService $generalSettingsService, Classes $class): array
    {
        $schoolYear = $class->school_year ?? $generalSettingsService->getCurrentSchoolYearString();

        if (! is_string($schoolYear) || mb_trim($schoolYear) === '') {
            $schoolYear = $generalSettingsService->getCurrentSchoolYearString();
        }

        $normalizedSchoolYear = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $compactSchoolYear = str_replace(' ', '', $normalizedSchoolYear);

        return array_values(array_unique([$normalizedSchoolYear, $compactSchoolYear]));
    }

    private function resolveSemester(GeneralSettingsService $generalSettingsService, Classes $class): int|string
    {
        $semester = $class->semester ?? $generalSettingsService->getCurrentSemester();

        if (is_int($semester)) {
            return $semester;
        }

        if (is_string($semester) && ctype_digit($semester)) {
            return (int) $semester;
        }

        if ($semester === 'summer') {
            return $semester;
        }

        return $generalSettingsService->getCurrentSemester();
    }
}
