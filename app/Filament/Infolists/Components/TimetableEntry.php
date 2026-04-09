<?php

declare(strict_types=1);

namespace App\Filament\Infolists\Components;

use App\Services\GeneralSettingsService;
use Filament\Infolists\Components\Entry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

final class TimetableEntry extends Entry
{
    protected string $view = 'filament.infolists.components.timetable-entry';

    private bool $showHeader = true;

    private bool $showLegend = true;

    private bool $allowToggle = true;

    public function showHeader(bool $show = true): static
    {
        $this->showHeader = $show;

        return $this;
    }

    public function showLegend(bool $show = true): static
    {
        $this->showLegend = $show;

        return $this;
    }

    public function allowToggle(bool $allow = true): static
    {
        $this->allowToggle = $allow;

        return $this;
    }

    public function getShowHeader(): bool
    {
        return $this->showHeader;
    }

    public function getShowLegend(): bool
    {
        return $this->showLegend;
    }

    public function getAllowToggle(): bool
    {
        return $this->allowToggle;
    }

    public function getSchedulesData(): Collection
    {
        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'classEnrollments')) {
            return collect();
        }

        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $schoolYearWithSpaces = $currentSchoolYear;
        $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);

        // Get the student's current class IDs
        $studentClassIds = $record->classEnrollments()
            ->whereHas('class', function ($query) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $currentSemester): void {
                $query->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                    ->where('semester', $currentSemester);
            })
            ->pluck('class_id');

        // Query schedules directly for those classes
        return \App\Models\Schedule::query()
            ->whereIn('class_id', $studentClassIds)
            ->with([
                'class.subject',
                'class.subjectByCode',
                'class.subjectByCodeFallback',
                'class.shsSubject',
                'class.faculty',
                'room',
            ])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getSchedulesCount(): int
    {
        $record = $this->getRecord();

        if (! $record || ! method_exists($record, 'classEnrollments')) {
            return 0;
        }

        $generalSettingsService = app(GeneralSettingsService::class);
        $currentSchoolYear = $generalSettingsService->getCurrentSchoolYearString();
        $currentSemester = $generalSettingsService->getCurrentSemester();

        $schoolYearWithSpaces = $currentSchoolYear;
        $schoolYearNoSpaces = str_replace(' ', '', $currentSchoolYear);

        return $record->classEnrollments()
            ->whereHas('class', function ($query) use ($schoolYearWithSpaces, $schoolYearNoSpaces, $currentSemester): void {
                $query->whereIn('school_year', [$schoolYearWithSpaces, $schoolYearNoSpaces])
                    ->where('semester', $currentSemester);
            })
            ->whereHas('class.schedules')
            ->count();
    }

    public function generateTimeSlots(Collection $schedules): array
    {
        if ($schedules->isEmpty()) {
            return [];
        }

        // Collect all relevant time points from schedules within 8 AM - 6 PM range
        $timePoints = [];
        foreach ($schedules as $schedule) {
            $scheduleStart = $schedule->start_time;
            $scheduleEnd = $schedule->end_time;

            // Only consider schedules within 8 AM - 6 PM
            $startHour = (int) $scheduleStart->format('H');
            $endHour = (int) $scheduleEnd->format('H');

            if ($endHour < 8 || $startHour > 18) {
                continue; // Skip schedules completely outside the range
            }

            // Clamp times to 8 AM - 6 PM range
            $clampedStart = $scheduleStart->copy()->max(\Carbon\Carbon::parse('08:00'));
            $clampedEnd = $scheduleEnd->copy()->min(\Carbon\Carbon::parse('18:00'));

            // Add all 30-minute intervals for this schedule
            $current = $clampedStart->copy();
            while ($current <= $clampedEnd) {
                $timePoints[] = $current->format('H:i');
                $current->addMinutes(30);
            }
        }

        if ($timePoints === []) {
            return [];
        }

        // Remove duplicates, sort, and add padding
        $timePoints = array_unique($timePoints);
        sort($timePoints);

        // Add padding: 30 minutes before first and after last
        $firstTime = \Carbon\Carbon::parse($timePoints[0]);
        $lastTime = \Carbon\Carbon::parse($timePoints[count($timePoints) - 1]);

        $paddingStart = $firstTime->copy()->subMinutes(30);
        if ($paddingStart->format('H:i') >= '08:00') {
            array_unshift($timePoints, $paddingStart->format('H:i'));
        }

        $paddingEnd = $lastTime->copy()->addMinutes(30);
        if ($paddingEnd->format('H:i') <= '18:30') {
            $timePoints[] = $paddingEnd->format('H:i');
        }

        return array_values(array_unique($timePoints));
    }

    public function generateClassColor(int|string $classId): string
    {
        // Generate a consistent color based on class ID
        $hash = crc32((string) $classId);

        // Use Filament's semantic colors with better contrast
        $colors = [
            'bg-primary-600', 'bg-primary-500',
            'bg-success-600', 'bg-success-500',
            'bg-warning-600', 'bg-warning-500',
            'bg-danger-600', 'bg-danger-500',
            'bg-info-600', 'bg-info-500',
            'bg-secondary-600', 'bg-secondary-500',
            'bg-gray-600', 'bg-gray-500',
        ];

        return $colors[abs($hash) % count($colors)];
    }

    public function findScheduleForSlot(Collection $schedules, string $day, string $timeSlot): ?Model
    {
        $slotTime = \Carbon\Carbon::parse($timeSlot);

        return $schedules->first(function ($schedule) use ($day, $slotTime): bool {
            // Normalize day comparison to handle inconsistent casing in database
            if (mb_strtolower((string) $schedule->day_of_week) !== mb_strtolower($day)) {
                return false;
            }

            $scheduleStart = $schedule->start_time;
            $scheduleEnd = $schedule->end_time;
            if ($slotTime->between($scheduleStart, $scheduleEnd, false)) {
                return true;
            }

            return $slotTime->eq($scheduleStart);
        });
    }

    public function calculateSlotSpan(Model $schedule): int
    {
        $start = $schedule->start_time;
        $end = $schedule->end_time;

        // Calculate duration in 30-minute increments
        $durationMinutes = $start->diffInMinutes($end);

        return max(1, (int) ($durationMinutes / 30));
    }

    public function getScheduleSpanInfo(Collection $schedules, string $day, string $timeSlot): ?array
    {
        $schedule = $this->findScheduleForSlot($schedules, $day, $timeSlot);

        if (! $schedule instanceof Model) {
            return null;
        }

        $slotTime = \Carbon\Carbon::parse($timeSlot);
        $scheduleStart = $schedule->start_time;

        // Only return span info if this is the starting slot of the schedule
        if ($slotTime->eq($scheduleStart)) {
            return [
                'schedule' => $schedule,
                'span' => $this->calculateSlotSpan($schedule),
                'color' => $this->generateClassColor($schedule->class_id),
            ];
        }

        return null; // This is not the starting slot, so don't render anything
    }
}
