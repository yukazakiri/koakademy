<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

final class StudentScheduleService
{
    /**
     * @return array{classes: list<array<string, mixed>>, conflicts: list<array<string, mixed>>}
     */
    public function build(Student $student, ?string $schoolYear = null, ?int $semester = null): array
    {
        if (filled($schoolYear) && filled($semester)) {
            $normalized = GeneralSettingsService::normalizeSchoolYear($schoolYear);
            $compact = str_replace(' ', '', $normalized);
            $period = [
                'school_year' => $normalized,
                'semester' => (int) $semester,
                'school_year_variants' => array_unique([$normalized, $compact]),
            ];
        } else {
            $period = $student->getCurrentAcademicPeriod();
        }

        /** @var Collection<int, ClassEnrollment> $enrollments */
        $enrollments = $student->classEnrollments()
            ->where('status', true)
            ->whereHas('class', function (Builder $query) use ($period): void {
                $query->whereIn('school_year', $period['school_year_variants'])
                    ->where('semester', $period['semester']);
            })
            ->with([
                'class' => function ($query): void {
                    $query->with([
                        'subject',
                        'subjectByCode',
                        'subjectByCodeFallback',
                        'shsSubject',
                        'faculty',
                        'room',
                        'schedules.room',
                    ])->withCount('class_enrollments');
                },
            ])
            ->latest('id')
            ->get();

        $classes = $enrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => $enrollment->class instanceof Classes)
            ->unique(fn (ClassEnrollment $enrollment): int => (int) $enrollment->class_id)
            ->map(fn (ClassEnrollment $enrollment): array => $this->mapClass($enrollment->class))
            ->values()
            ->all();

        return [
            'classes' => $classes,
            'conflicts' => $this->buildConflicts($classes),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapClass(Classes $class): array
    {
        $subject = $class->subject
            ?? $class->subjectByCode
            ?? $class->subjectByCodeFallback
            ?? $class->shsSubject;

        $schedules = $class->schedules
            ->map(function ($schedule) use ($class): array {
                $startTime = $schedule->start_time?->format('H:i');
                $endTime = $schedule->end_time?->format('H:i');

                return [
                    'id' => $schedule->id,
                    'day_of_week' => $this->normalizeDay((string) $schedule->day_of_week),
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'room_id' => $schedule->room_id,
                    'room' => $schedule->room?->name ?? $class->room?->name ?? 'Room TBA',
                ];
            })
            ->filter(fn (array $schedule): bool => $schedule['day_of_week'] !== '' && $schedule['start_time'] !== null && $schedule['end_time'] !== null)
            ->unique(fn (array $schedule): string => implode('|', [
                mb_strtolower($schedule['day_of_week']),
                $schedule['start_time'],
                $schedule['end_time'],
                (string) ($schedule['room_id'] ?? 0),
            ]))
            ->values();

        $settings = is_array($class->settings) ? $class->settings : [];

        return [
            'id' => $class->id,
            'subject_code' => $class->subject_code ?? 'N/A',
            'subject_title' => $subject?->title ?? $class->subject_title ?? 'Unknown Subject',
            'section' => $class->section ?? 'N/A',
            'units' => $subject?->units ?? 0,
            'faculty_name' => $class->faculty?->full_name ?? 'TBA',
            'schedule' => 'TBA',
            'room' => $class->room?->name ?? 'TBA',
            'room_id' => $class->room_id,
            'faculty_id' => $class->faculty_id,
            'maximum_slots' => $class->maximum_slots,
            'students_count' => $class->class_enrollments_count ?? 0,
            'classification' => $class->classification,
            'strand_id' => $class->shs_strand_id,
            'subject_id' => $class->subject_id,
            'semester' => $class->semester,
            'school_year' => $class->school_year,
            'schedules' => $schedules->all(),
            'settings' => $settings,
            'accent_color' => $settings['accent_color'] ?? null,
            'background_color' => $settings['background_color'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $classes
     * @return list<array<string, mixed>>
     */
    private function buildConflicts(array $classes): array
    {
        /** @var array<string, list<array<string, mixed>>> $eventsByDay */
        $eventsByDay = [];

        foreach ($classes as $class) {
            foreach ($class['schedules'] as $schedule) {
                $startMinutes = $this->timeToMinutes((string) $schedule['start_time']);
                $endMinutes = $this->timeToMinutes((string) $schedule['end_time']);
                if ($startMinutes === null) {
                    continue;
                }
                if ($endMinutes === null) {
                    continue;
                }
                if ($endMinutes <= $startMinutes) {
                    continue;
                }

                $day = (string) $schedule['day_of_week'];
                $eventsByDay[$day][] = [
                    'schedule_id' => (int) $schedule['id'],
                    'class_id' => (int) $class['id'],
                    'subject_code' => (string) $class['subject_code'],
                    'subject_title' => (string) $class['subject_title'],
                    'section' => (string) $class['section'],
                    'room' => (string) $schedule['room'],
                    'start_minutes' => $startMinutes,
                    'end_minutes' => $endMinutes,
                ];
            }
        }

        $conflicts = [];

        foreach ($eventsByDay as $day => $events) {
            usort($events, fn (array $left, array $right): int => [$left['start_minutes'], $left['end_minutes']] <=> [$right['start_minutes'], $right['end_minutes']]);
            $active = [];

            foreach ($events as $event) {
                $active = array_values(array_filter(
                    $active,
                    fn (array $candidate): bool => $candidate['end_minutes'] > $event['start_minutes']
                ));

                foreach ($active as $candidate) {
                    if ($candidate['class_id'] === $event['class_id']) {
                        continue;
                    }

                    $overlapStart = max($candidate['start_minutes'], $event['start_minutes']);
                    $overlapEnd = min($candidate['end_minutes'], $event['end_minutes']);

                    if ($overlapStart >= $overlapEnd) {
                        continue;
                    }

                    $pair = [$candidate, $event];
                    usort($pair, fn (array $left, array $right): int => $left['schedule_id'] <=> $right['schedule_id']);

                    $id = implode('-', [
                        mb_strtolower($day),
                        $pair[0]['schedule_id'],
                        $pair[1]['schedule_id'],
                        $overlapStart,
                        $overlapEnd,
                    ]);

                    $conflicts[$id] = [
                        'id' => $id,
                        'day' => $day,
                        'overlap_start' => $this->minutesToTime($overlapStart),
                        'overlap_end' => $this->minutesToTime($overlapEnd),
                        'classes' => array_map(fn (array $item): array => [
                            'id' => $item['class_id'],
                            'schedule_id' => $item['schedule_id'],
                            'subject_code' => $item['subject_code'],
                            'subject_title' => $item['subject_title'],
                            'section' => $item['section'],
                            'room' => $item['room'],
                            'start_time' => $this->minutesToTime($item['start_minutes']),
                            'end_time' => $this->minutesToTime($item['end_minutes']),
                        ], $pair),
                    ];
                }

                $active[] = $event;
            }
        }

        return array_values($conflicts);
    }

    private function normalizeDay(string $day): string
    {
        return mb_ucfirst(mb_strtolower(mb_trim($day)));
    }

    private function timeToMinutes(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $time, $matches)) {
            return null;
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            return null;
        }

        return ($hours * 60) + $minutes;
    }

    private function minutesToTime(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }
}
