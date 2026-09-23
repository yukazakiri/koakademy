<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Classes;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Services\GeneralSettingsService;
use App\Services\StudentScheduleService;
use Illuminate\Database\Eloquent\Builder;

final class ComprehensiveScheduleService
{
    public function __construct(
        private readonly GeneralSettingsService $settings,
        private readonly StudentScheduleService $studentScheduleService,
    ) {}

    /**
     * Unified query across room, student, faculty, and class schedules.
     *
     * @return array<string, mixed>
     */
    public function query(
        string $targetType = 'auto',
        ?string $identifier = null,
        ?string $dayOfWeek = null,
        ?string $schoolYear = null,
        ?int $semester = null,
        bool $checkAvailability = false,
        int $limit = 20,
    ): array {
        $schoolYear = $schoolYear ?: $this->settings->getCurrentSchoolYearString();
        $semester = $semester ?: $this->settings->getCurrentSemester();
        $dayOfWeek = $dayOfWeek ? mb_convert_case(mb_trim($dayOfWeek), MB_CASE_TITLE) : null;
        $identifier = $identifier ? mb_trim($identifier) : null;

        $target = mb_strtolower($targetType);
        if ($target === 'auto' || empty($target)) {
            $target = $this->detectTargetType($identifier);
        }

        return match ($target) {
            'room' => $this->queryRoomSchedule($identifier, $dayOfWeek, $schoolYear, $semester, $checkAvailability, $limit),
            'student' => $this->queryStudentSchedule($identifier, $schoolYear, $semester),
            'faculty', 'teacher', 'instructor' => $this->queryFacultySchedule($identifier, $dayOfWeek, $schoolYear, $semester, $limit),
            'class', 'subject' => $this->queryClassSchedule($identifier, $dayOfWeek, $schoolYear, $semester, $limit),
            default => $this->queryMultiTargetSchedule($identifier, $dayOfWeek, $schoolYear, $semester, $limit),
        };
    }

    /**
     * Smartly detect whether query refers to room, student, faculty, or class.
     */
    private function detectTargetType(?string $identifier): string
    {
        if (blank($identifier)) {
            return 'room';
        }

        $clean = mb_strtolower($identifier);

        if (str_contains($clean, 'room') || str_contains($clean, 'lab') || str_contains($clean, 'bldg') || str_contains($clean, 'avr') || str_contains($clean, 'hall')) {
            return 'room';
        }

        if (str_contains($clean, 'prof') || str_contains($clean, 'teacher') || str_contains($clean, 'instructor') || str_contains($clean, 'engr') || str_contains($clean, 'dr.')) {
            return 'faculty';
        }

        if (Room::query()->where('name', 'like', "%{$identifier}%")->exists()) {
            return 'room';
        }

        if (Faculty::query()->where(function ($q) use ($identifier) {
            $q->where('faculty_id_number', $identifier)
                ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$identifier}%"]);
        })->exists()) {
            return 'faculty';
        }

        if (Student::query()->where(function ($q) use ($identifier) {
            $q->where('student_id', $identifier)
                ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$identifier}%"]);
        })->exists()) {
            return 'student';
        }

        if (Classes::query()->where('subject_code', 'like', "%{$identifier}%")->orWhere('section', 'like', "%{$identifier}%")->exists()) {
            return 'class';
        }

        return 'room';
    }

    /**
     * Query Room schedules and optionally calculate open/available slots.
     */
    private function queryRoomSchedule(
        ?string $identifier,
        ?string $dayOfWeek,
        string $schoolYear,
        int $semester,
        bool $checkAvailability,
        int $limit
    ): array {
        $roomsQuery = Room::query();

        if (filled($identifier)) {
            $cleanIdentifier = str_ireplace(['room', 'rm', 'rm.', 'room:'], '', $identifier);
            $cleanIdentifier = mb_trim($cleanIdentifier);

            $roomsQuery->where(function ($q) use ($identifier, $cleanIdentifier) {
                $q->where('name', 'like', "%{$identifier}%");
                if (filled($cleanIdentifier) && $cleanIdentifier !== $identifier) {
                    $q->orWhere('name', 'like', "%{$cleanIdentifier}%");
                }
                if (is_numeric($identifier)) {
                    $q->orWhere('id', (int) $identifier);
                }
            });
        }

        $rooms = $roomsQuery->limit($limit)->get();

        if ($rooms->isEmpty()) {
            return [
                'type' => 'room',
                'query' => $identifier,
                'found' => false,
                'message' => "No classroom matching '{$identifier}' was found.",
            ];
        }

        $results = [];

        foreach ($rooms as $room) {
            $schedulesQuery = Schedule::query()
                ->where('room_id', $room->id)
                ->whereHas('class', function (Builder $q) use ($schoolYear, $semester) {
                    $q->forAcademicPeriod($schoolYear, $semester);
                })
                ->with(['class.faculty', 'class.subject', 'class.class_enrollments']);

            if (filled($dayOfWeek)) {
                $schedulesQuery->where('day_of_week', $dayOfWeek);
            }

            $schedules = $schedulesQuery->get()->sortBy(function (Schedule $s) {
                $order = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
                $dayOrder = $order[$s->day_of_week] ?? 8;

                return $dayOrder * 10000 + (int) str_replace(':', '', (string) ($s->start_time?->format('Hi') ?? '0000'));
            })->values();

            $bookedSlots = $schedules->map(fn (Schedule $s): array => [
                'schedule_id' => $s->id,
                'day_of_week' => $s->day_of_week,
                'time_range' => "{$s->formatted_start_time} - {$s->formatted_end_time}",
                'start_time' => $s->formatted_start_time,
                'end_time' => $s->formatted_end_time,
                'subject_code' => $s->class?->subject_code ?? 'TBA',
                'subject_title' => $s->class?->subject?->title ?? $s->class?->subject_title ?? 'Class Section',
                'section' => $s->class?->section ?? 'N/A',
                'faculty' => $s->class?->faculty?->full_name ?? 'TBA',
                'enrolled_students' => $s->class?->class_enrollments?->count() ?? 0,
                'capacity' => $s->class?->maximum_slots ?? $room->capacity ?? 40,
            ])->all();

            $roomData = [
                'room_id' => $room->id,
                'name' => $room->name,
                'is_active' => (bool) $room->is_active,
                'booked_slots_count' => count($bookedSlots),
                'schedules' => $bookedSlots,
            ];

            if ($checkAvailability) {
                $roomData['available_windows'] = $this->calculateFreeWindows($schedules, $dayOfWeek);
            }

            $results[] = $roomData;
        }

        return [
            'type' => 'room',
            'academic_period' => "SY {$schoolYear} - Semester {$semester}",
            'day_filter' => $dayOfWeek ?? 'All Days',
            'count' => count($results),
            'rooms' => $results,
        ];
    }

    /**
     * Query Student schedule including timetable conflicts.
     */
    private function queryStudentSchedule(?string $identifier, string $schoolYear, int $semester): array
    {
        if (blank($identifier)) {
            return [
                'type' => 'student',
                'found' => false,
                'message' => 'Please provide a student name, student number, or student ID.',
            ];
        }

        $studentQuery = Student::query();

        if (is_numeric($identifier)) {
            $studentQuery->where('id', (int) $identifier)
                ->orWhere('student_id', $identifier);
        } else {
            $term = '%'.addcslashes($identifier, '%_\\').'%';
            $studentQuery->where('student_id', 'like', $term)
                ->orWhere('email', 'like', $term)
                ->orWhere('first_name', 'like', $term)
                ->orWhere('last_name', 'like', $term)
                ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, middle_name, last_name, suffix)) LIKE ?", [$term]);
        }

        $student = $studentQuery->first();

        if (! $student instanceof Student) {
            return [
                'type' => 'student',
                'query' => $identifier,
                'found' => false,
                'message' => "Student '{$identifier}' was not found in records.",
            ];
        }

        $scheduleData = $this->studentScheduleService->build($student, $schoolYear, $semester);

        return [
            'type' => 'student',
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'email' => $student->email,
                'program' => $student->Course?->code ?? $student->student_type,
                'year_level' => $student->academic_year,
                'status' => $student->status,
            ],
            'academic_period' => "SY {$schoolYear} - Semester {$semester}",
            'enrolled_classes_count' => count($scheduleData['classes']),
            'has_conflicts' => ! empty($scheduleData['conflicts']),
            'conflicts_count' => count($scheduleData['conflicts']),
            'conflicts' => $scheduleData['conflicts'],
            'classes' => $scheduleData['classes'],
        ];
    }

    /**
     * Query Faculty teacher schedules.
     */
    private function queryFacultySchedule(
        ?string $identifier,
        ?string $dayOfWeek,
        string $schoolYear,
        int $semester,
        int $limit
    ): array {
        $facultyQuery = Faculty::query()->with([
            'classes' => fn ($q) => $q->forAcademicPeriod($schoolYear, $semester)->with(['schedules.room', 'room', 'subject', 'class_enrollments']),
        ]);

        if (filled($identifier)) {
            $cleanName = str_ireplace(['prof.', 'prof', 'dr.', 'dr', 'teacher', 'instructor', 'engr.', 'engr'], '', $identifier);
            $cleanName = mb_trim($cleanName);

            $facultyQuery->where(function ($q) use ($identifier, $cleanName) {
                $q->where('faculty_id_number', 'like', "%{$identifier}%")
                    ->orWhere('email', 'like', "%{$identifier}%")
                    ->orWhere('first_name', 'like', "%{$identifier}%")
                    ->orWhere('last_name', 'like', "%{$identifier}%")
                    ->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$identifier}%"]);

                if (filled($cleanName) && $cleanName !== $identifier) {
                    $q->orWhereRaw("TRIM(CONCAT_WS(' ', first_name, last_name)) LIKE ?", ["%{$cleanName}%"]);
                }
            });
        }

        $facultyMembers = $facultyQuery->limit($limit)->get();

        if ($facultyMembers->isEmpty()) {
            return [
                'type' => 'faculty',
                'query' => $identifier,
                'found' => false,
                'message' => "No faculty or instructor matching '{$identifier}' was found.",
            ];
        }

        $results = [];

        foreach ($facultyMembers as $faculty) {
            $classes = $faculty->classes;
            $allSchedules = [];

            foreach ($classes as $class) {
                foreach ($class->schedules as $sched) {
                    if (filled($dayOfWeek) && mb_strtolower((string) $sched->day_of_week) !== mb_strtolower($dayOfWeek)) {
                        continue;
                    }

                    $allSchedules[] = [
                        'schedule_id' => $sched->id,
                        'day_of_week' => $sched->day_of_week,
                        'time_range' => "{$sched->formatted_start_time} - {$sched->formatted_end_time}",
                        'start_time' => $sched->formatted_start_time,
                        'end_time' => $sched->formatted_end_time,
                        'subject_code' => $class->subject_code,
                        'subject_title' => $class->subject?->title ?? $class->subject_title ?? 'Class',
                        'section' => $class->section,
                        'room' => $sched->room?->name ?? $class->room?->name ?? 'TBA',
                        'enrolled_students' => $class->class_enrollments?->count() ?? 0,
                    ];
                }
            }

            usort($allSchedules, function ($a, $b) {
                $order = ['Monday' => 1, 'Tuesday' => 2, 'Wednesday' => 3, 'Thursday' => 4, 'Friday' => 5, 'Saturday' => 6, 'Sunday' => 7];
                $dayA = $order[$a['day_of_week']] ?? 8;
                $dayB = $order[$b['day_of_week']] ?? 8;

                return $dayA <=> $dayB ?: strcmp((string) $a['start_time'], (string) $b['start_time']);
            });

            $results[] = [
                'faculty_id' => $faculty->id,
                'faculty_id_number' => $faculty->faculty_id_number,
                'name' => $faculty->full_name,
                'email' => $faculty->email,
                'department' => $faculty->department,
                'total_assigned_classes' => $classes->count(),
                'weekly_schedules_count' => count($allSchedules),
                'schedules' => $allSchedules,
            ];
        }

        return [
            'type' => 'faculty',
            'academic_period' => "SY {$schoolYear} - Semester {$semester}",
            'day_filter' => $dayOfWeek ?? 'All Days',
            'count' => count($results),
            'faculty' => $results,
        ];
    }

    /**
     * Query Classes and sections by code or section.
     */
    private function queryClassSchedule(
        ?string $identifier,
        ?string $dayOfWeek,
        string $schoolYear,
        int $semester,
        int $limit
    ): array {
        $classesQuery = Classes::query()
            ->forAcademicPeriod($schoolYear, $semester)
            ->with(['faculty', 'room', 'schedules.room', 'class_enrollments', 'subject']);

        if (filled($identifier)) {
            $classesQuery->where(function ($q) use ($identifier) {
                $q->where('subject_code', 'like', "%{$identifier}%")
                    ->orWhere('section', 'like', "%{$identifier}%")
                    ->orWhereHas('subject', function ($sq) use ($identifier) {
                        $sq->where('title', 'like', "%{$identifier}%")
                            ->orWhere('code', 'like', "%{$identifier}%");
                    });
            });
        }

        $classes = $classesQuery->limit($limit)->get()->map(function (Classes $class) use ($dayOfWeek): array {
            $schedules = $class->schedules->when(filled($dayOfWeek), function ($collection) use ($dayOfWeek) {
                return $collection->filter(fn ($s) => mb_strtolower((string) $s->day_of_week) === mb_strtolower((string) $dayOfWeek));
            })->map(fn (Schedule $s): array => [
                'day_of_week' => $s->day_of_week,
                'time_range' => "{$s->formatted_start_time} - {$s->formatted_end_time}",
                'room' => $s->room?->name ?? $class->room?->name ?? 'TBA',
            ])->values()->all();

            return [
                'class_id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject?->title ?? $class->subject_title ?? 'Class',
                'section' => $class->section,
                'faculty' => $class->faculty?->full_name ?? 'TBA',
                'room' => $class->room?->name ?? 'TBA',
                'enrolled' => $class->class_enrollments->count(),
                'max_slots' => $class->maximum_slots,
                'schedules' => $schedules,
            ];
        })->values()->all();

        return [
            'type' => 'class',
            'academic_period' => "SY {$schoolYear} - Semester {$semester}",
            'count' => count($classes),
            'classes' => $classes,
        ];
    }

    private function queryMultiTargetSchedule(
        ?string $identifier,
        ?string $dayOfWeek,
        string $schoolYear,
        int $semester,
        int $limit
    ): array {
        return [
            'type' => 'multi',
            'rooms' => $this->queryRoomSchedule($identifier, $dayOfWeek, $schoolYear, $semester, false, 5),
            'faculty' => $this->queryFacultySchedule($identifier, $dayOfWeek, $schoolYear, $semester, 5),
            'classes' => $this->queryClassSchedule($identifier, $dayOfWeek, $schoolYear, $semester, 5),
        ];
    }

    /**
     * Compute open windows for classroom schedules.
     *
     * @param  \Illuminate\Support\Collection<int, Schedule>  $schedules
     * @return array<string, list<string>>
     */
    private function calculateFreeWindows($schedules, ?string $dayOfWeek): array
    {
        $days = $dayOfWeek ? [$dayOfWeek] : ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $freeWindows = [];

        foreach ($days as $day) {
            $dayBookings = $schedules->filter(fn ($s) => mb_strtolower((string) $s->day_of_week) === mb_strtolower($day));

            if ($dayBookings->isEmpty()) {
                $freeWindows[$day] = ['07:00 AM - 08:00 PM (Entire day open)'];

                continue;
            }

            $freeWindows[$day] = ['Open outside booked class intervals (07:00 AM - 08:00 PM)'];
        }

        return $freeWindows;
    }
}
