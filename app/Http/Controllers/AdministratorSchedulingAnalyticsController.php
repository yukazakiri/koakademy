<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\StoreClassRequest;
use App\Http\Requests\UpdateScheduleRequest;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\ShsStrand;
use App\Models\ShsTrack;
use App\Models\Student;
use App\Models\Subject;
use App\Models\User;
use App\Services\GeneralSettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorSchedulingAnalyticsController extends Controller
{
    public function index(Request $request, GeneralSettingsService $generalSettingsService): Response|\Illuminate\Http\RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return redirect('/login');
        }

        // Base query for current academic period classes
        $query = Classes::currentAcademicPeriod()
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room']);

        $classes = $query->withCount('ClassStudents')->get();

        // extract all unique course IDs from classes that have at least one schedule
        $courseIds = $classes->filter(fn ($class) => $class->Schedule->isNotEmpty())
            ->pluck('course_codes')
            ->flatten()
            ->unique()
            ->filter()
            ->toArray();

        // Get available courses for filtering (only those with schedules)
        $availableCourses = Course::query()
            ->whereIn('id', $courseIds)
            ->orderBy('code')
            ->get(['id', 'code', 'title']);

        // Get available rooms (active rooms with schedules in current period)
        $roomIdsWithSchedules = $classes->flatMap(fn ($class) => $class->Schedule->pluck('room_id'))
            ->filter()
            ->unique()
            ->toArray();

        $availableRooms = Room::query()
            ->where('is_active', true)
            ->whereIn('id', $roomIdsWithSchedules)
            ->orderBy('name')
            ->get(['id', 'name', 'class_code']);

        // Get available faculty (faculty with classes in current period)
        $facultyIdsWithClasses = $classes->pluck('faculty_id')
            ->filter()
            ->unique()
            ->toArray();

        $availableFaculty = Faculty::query()
            ->whereIn('id', $facultyIdsWithClasses)
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'department']);

        // Process schedule data with normalized year levels
        $scheduleData = $classes->map(function ($class): array {
            $yearLevel = null;
            if ($class->classification === 'shs') {
                $yearLevel = $class->grade_level;
            } else {
                // Assuming college/default
                $year = (int) $class->academic_year;
                $yearLevel = match ($year) {
                    1 => '1st Year',
                    2 => '2nd Year',
                    3 => '3rd Year',
                    4 => '4th Year',
                    default => $year !== 0 ? "{$year}th Year" : 'N/A',
                };
            }

            return [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject_title,
                'section' => $class->section,
                'grade_level' => $yearLevel,
                'classification' => $class->classification,
                'faculty_id' => $class->faculty_id,
                'faculty_name' => $class->Faculty ? $class->Faculty->first_name.' '.$class->Faculty->last_name : null,
                'room_name' => $class->Room?->name,
                'courses' => $class->formatted_course_codes,
                'course_ids' => $class->course_codes, // Pass IDs for accurate filtering
                'student_count' => $class->class_students_count,
                'schedules' => $class->Schedule->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->formatted_start_time,
                    'end_time' => $schedule->formatted_end_time,
                    'time_range' => $schedule->time_range,
                    'room' => $schedule->room?->name,
                    'room_id' => $schedule->room_id,
                ]),
            ];
        });

        // Get available year levels from the processed data
        $availableYearLevels = $scheduleData->pluck('grade_level')
            ->unique()
            ->filter()
            ->sort(function ($a, $b): int {
                // Custom sort to put College years first, then SHS
                $isYearA = str_contains($a, 'Year');
                $isYearB = str_contains($b, 'Year');
                if ($isYearA && ! $isYearB) {
                    return -1;
                }
                if (! $isYearA && $isYearB) {
                    return 1;
                }

                return strnatcmp($a, $b);
            })
            ->values()
            ->toArray();

        // Get available sections from the processed data
        $availableSections = $scheduleData->pluck('section')
            ->unique()
            ->filter()
            ->sort()
            ->values()
            ->toArray();

        // Get statistics
        $stats = [
            'total_classes' => $classes->count(),
            'total_students' => $classes->sum('class_students_count'),
            'classes_by_year_level' => $scheduleData->groupBy('grade_level')
                ->map(fn ($group): int => $group->count())
                ->toArray(),
            'classes_by_course' => $classes->groupBy('formatted_course_codes')
                ->map(fn ($group): int => $group->count())
                ->toArray(),
            'schedule_conflicts' => $this->detectScheduleConflicts($classes),
        ];

        // Get all options needed for class creation
        $allRooms = Room::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'class_code']);

        $allFaculty = Faculty::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get(['id', 'first_name', 'last_name', 'department']);

        $allCourses = Course::query()
            ->orderBy('code')
            ->get(['id', 'code', 'title', 'curriculum_year']);

        $shsTracks = ShsTrack::query()
            ->orderBy('track_name')
            ->get(['id', 'track_name']);

        $shsStrands = ShsStrand::query()
            ->with('track:id,track_name')
            ->orderBy('strand_name')
            ->get(['id', 'strand_name', 'track_id']);

        return Inertia::render('administrators/scheduling-analytics', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Administrator',
            ],
            'schedule_data' => $scheduleData,
            'stats' => $stats,
            'filters' => [
                'available_courses' => $availableCourses,
                'available_year_levels' => $availableYearLevels,
                'available_sections' => $availableSections,
                'available_rooms' => $availableRooms->map(fn ($room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'class_code' => $room->class_code,
                ]),
                'available_faculty' => $availableFaculty->map(fn ($faculty): array => [
                    'id' => $faculty->id,
                    'name' => $faculty->first_name.' '.$faculty->last_name,
                    'department' => $faculty->department,
                ]),
                'current_filters' => [
                    'course' => $request->input('course'),
                    'year_level' => $request->input('year_level'),
                    'section' => $request->input('section'),
                ],
            ],
            'creation_options' => [
                'rooms' => $allRooms->map(fn ($room): array => [
                    'id' => $room->id,
                    'name' => $room->name,
                    'class_code' => $room->class_code,
                ]),
                'faculty' => $allFaculty->map(fn ($faculty): array => [
                    'id' => $faculty->id,
                    'name' => $faculty->first_name.' '.$faculty->last_name,
                    'department' => $faculty->department,
                ]),
                'courses' => $allCourses->map(fn ($course): array => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'curriculum_year' => $course->curriculum_year,
                ]),
                'shs_tracks' => $shsTracks->map(fn ($track): array => [
                    'id' => $track->id,
                    'track_name' => $track->track_name,
                ]),
                'shs_strands' => $shsStrands->map(fn ($strand): array => [
                    'id' => $strand->id,
                    'strand_name' => $strand->strand_name,
                    'track_id' => $strand->track_id,
                    'track_name' => $strand->track?->track_name,
                ]),
                'sections' => ['A', 'B', 'C', 'D'],
                'semesters' => [
                    ['value' => '1', 'label' => '1st Semester'],
                    ['value' => '2', 'label' => '2nd Semester'],
                    ['value' => 'summer', 'label' => 'Summer'],
                ],
            ],
            'defaults' => [
                'semester' => (string) $generalSettingsService->getCurrentSemester(),
                'school_year' => $generalSettingsService->getCurrentSchoolYearString(),
            ],
        ]);
    }

    /**
     * Search for students and return their schedules
     */
    public function searchStudents(Request $request): JsonResponse
    {
        $request->validate([
            'query' => ['required', 'string', 'min:2'],
        ]);

        $query = $request->input('query');

        $students = Student::query()
            ->where(function ($q) use ($query): void {
                $q->where('first_name', 'ilike', "%{$query}%")
                    ->orWhere('last_name', 'ilike', "%{$query}%")
                    ->orWhere('student_id', 'ilike', "%{$query}%")
                    ->orWhere('lrn', 'ilike', "%{$query}%")
                    ->orWhere('email', 'ilike', "%{$query}%");
            })
            ->limit(20)
            ->get(['id', 'student_id', 'first_name', 'last_name', 'middle_name', 'course_id', 'academic_year']);

        return response()->json([
            'students' => $students->map(fn ($student): array => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'course_id' => $student->course_id,
                'academic_year' => $student->academic_year,
            ]),
        ]);
    }

    /**
     * Get a student's class schedule for the current academic period
     */
    public function getStudentSchedule(Request $request, int $studentId): JsonResponse
    {
        $student = Student::query()
            ->with(['Course'])
            ->find($studentId);

        if (! $student) {
            return response()->json(['error' => 'Student not found'], 404);
        }

        // Get current classes for the student
        $classEnrollments = $student->getCurrentClasses();

        $scheduleData = $classEnrollments->map(function ($enrollment): ?array {
            $class = $enrollment->class;
            if (! $class) {
                return null;
            }

            // Load schedules for the class
            $class->load(['Schedule.room', 'Faculty']);

            return [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->subject_title,
                'section' => $class->section,
                'faculty_name' => $class->Faculty ? $class->Faculty->first_name.' '.$class->Faculty->last_name : null,
                'schedules' => $class->Schedule->map(fn ($schedule): array => [
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->formatted_start_time,
                    'end_time' => $schedule->formatted_end_time,
                    'time_range' => $schedule->time_range,
                    'room' => $schedule->room?->name,
                ]),
            ];
        })->filter();

        return response()->json([
            'student' => [
                'id' => $student->id,
                'student_id' => $student->student_id,
                'name' => $student->full_name,
                'course' => $student->Course?->code,
                'academic_year' => $student->academic_year,
            ],
            'schedule' => $scheduleData->values(),
        ]);
    }

    /**
     * Update a schedule entry's day and/or time (drag-and-drop).
     */
    public function updateSchedule(UpdateScheduleRequest $request, Schedule $schedule): JsonResponse
    {
        $validated = $request->validated();

        $updateData = [
            'day_of_week' => $validated['day_of_week'],
            'start_time' => $validated['start_time'].':00',
            'end_time' => $validated['end_time'].':00',
        ];

        if (array_key_exists('room_id', $validated)) {
            $updateData['room_id'] = $validated['room_id'];
        }

        $schedule->update($updateData);

        $schedule->refresh();
        $schedule->load('room');

        // Re-detect conflicts for the class that owns this schedule
        $class = Classes::currentAcademicPeriod()
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
            ->find($schedule->class_id);

        $conflicts = [];
        if ($class) {
            $allClasses = Classes::currentAcademicPeriod()
                ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
                ->get();

            $conflicts = $this->detectScheduleConflicts($allClasses);
        }

        return response()->json([
            'schedule' => [
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->formatted_start_time,
                'end_time' => $schedule->formatted_end_time,
                'time_range' => $schedule->time_range,
                'room' => $schedule->room?->name,
                'room_id' => $schedule->room_id,
            ],
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Create a new class with schedules from the scheduling analytics page.
     */
    public function storeClass(StoreClassRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $classification = $validated['classification'];

        /** @var array<string, mixed> $settings */
        $settings = (array) ($validated['settings'] ?? []);
        unset($validated['settings']);

        $settings = array_merge(Classes::getDefaultSettings(), $settings);
        $validated['settings'] = $settings;

        if ($classification === 'shs') {
            $validated['subject_code'] = $validated['subject_code_shs'];
            unset($validated['subject_code_shs']);

            $validated['course_codes'] = null;
            $validated['subject_ids'] = null;
            $validated['subject_id'] = null;
            $validated['academic_year'] = null;
        }

        if ($classification === 'college') {
            unset($validated['subject_code_shs'], $validated['shs_track_id'], $validated['shs_strand_id'], $validated['grade_level']);

            $subjectIds = Arr::wrap($validated['subject_ids'] ?? []);

            if ($subjectIds !== []) {
                $validated['subject_id'] = (int) $subjectIds[0];

                $codes = Subject::query()->whereIn('id', $subjectIds)->pluck('code')->filter()->unique()->values();
                $generatedCode = $codes->implode(', ');

                if (! isset($validated['subject_code']) || ! is_string($validated['subject_code']) || mb_trim($validated['subject_code']) === '') {
                    $validated['subject_code'] = $generatedCode;
                }
            }
        }

        $schedules = Arr::wrap($validated['schedules'] ?? []);
        unset($validated['schedules']);

        /** @var Classes $class */
        $class = DB::transaction(function () use ($validated, $schedules): Classes {
            $class = Classes::query()->create($validated);

            foreach ($schedules as $scheduleData) {
                $class->schedules()->create([
                    'day_of_week' => $scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time'],
                    'room_id' => $scheduleData['room_id'],
                ]);
            }

            return $class;
        });

        $class->load(['Subject', 'Faculty', 'Room', 'Schedule.room']);
        $class->loadCount('ClassStudents');

        // Build response in the same shape as schedule_data items
        $yearLevel = null;
        if ($class->classification === 'shs') {
            $yearLevel = $class->grade_level;
        } else {
            $year = (int) $class->academic_year;
            $yearLevel = match ($year) {
                1 => '1st Year',
                2 => '2nd Year',
                3 => '3rd Year',
                4 => '4th Year',
                default => $year !== 0 ? "{$year}th Year" : 'N/A',
            };
        }

        $response = [
            'id' => $class->id,
            'subject_code' => $class->subject_code,
            'subject_title' => $class->subject_title,
            'section' => $class->section,
            'grade_level' => $yearLevel,
            'classification' => $class->classification,
            'faculty_id' => $class->faculty_id,
            'faculty_name' => $class->Faculty ? $class->Faculty->first_name.' '.$class->Faculty->last_name : null,
            'room_name' => $class->Room?->name,
            'courses' => $class->formatted_course_codes,
            'course_ids' => $class->course_codes,
            'student_count' => $class->class_students_count,
            'schedules' => $class->Schedule->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->formatted_start_time,
                'end_time' => $schedule->formatted_end_time,
                'time_range' => $schedule->time_range,
                'room' => $schedule->room?->name,
                'room_id' => $schedule->room_id,
            ]),
        ];

        // Re-detect conflicts
        $allClasses = Classes::currentAcademicPeriod()
            ->with(['Subject', 'Faculty', 'Room', 'Schedule.room'])
            ->get();

        $conflicts = $this->detectScheduleConflicts($allClasses);

        return response()->json([
            'class' => $response,
            'conflicts' => $conflicts,
        ]);
    }

    /**
     * Detect scheduling conflicts (room or faculty double-booked)
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Classes>  $classes
     * @return array<int, array<string, mixed>>
     */
    private function detectScheduleConflicts($classes): array
    {
        $conflicts = [];
        $allSchedules = [];

        // Flatten all schedules to easily compare pairs
        foreach ($classes as $class) {
            foreach ($class->Schedule as $schedule) {
                if (! $schedule->start_time) {
                    continue;
                }
                if (! $schedule->end_time) {
                    continue;
                }
                $allSchedules[] = [
                    'class' => $class,
                    'schedule' => $schedule,
                    'day' => $schedule->day_of_week,
                    'start_min' => (int) $schedule->start_time->format('H') * 60 + (int) $schedule->start_time->format('i'),
                    'end_min' => (int) $schedule->end_time->format('H') * 60 + (int) $schedule->end_time->format('i'),
                ];
            }
        }

        $count = count($allSchedules);
        for ($i = 0; $i < $count; $i++) {
            for ($j = $i + 1; $j < $count; $j++) {
                $a = $allSchedules[$i];
                $b = $allSchedules[$j];

                // Must be on the same day
                if ($a['day'] !== $b['day']) {
                    continue;
                }

                // Skip checking against itself
                if ($a['schedule']->id === $b['schedule']->id) {
                    continue;
                }

                // Check for overlapping times (StartA < EndB and EndA > StartB)
                if ($a['start_min'] < $b['end_min'] && $a['end_min'] > $b['start_min']) {
                    $roomConflict = $a['schedule']->room_id && $b['schedule']->room_id && $a['schedule']->room_id === $b['schedule']->room_id;
                    $facultyConflict = $a['class']->faculty_id && $b['class']->faculty_id && $a['class']->faculty_id === $b['class']->faculty_id;

                    if ($roomConflict || $facultyConflict) {
                        $timeA = $a['schedule']->time_range;
                        $timeB = $b['schedule']->time_range;
                        $timeStr = $timeA === $timeB ? $timeA : "{$timeA} vs {$timeB}";

                        $conflicts[] = [
                            'day' => $a['day'],
                            'time' => $timeStr,
                            'class_1' => [
                                'subject_code' => $a['class']->subject_code,
                                'section' => $a['class']->section,
                                'room' => $a['schedule']->room?->name,
                                'faculty' => $a['class']->Faculty ? $a['class']->Faculty->first_name.' '.$a['class']->Faculty->last_name : null,
                            ],
                            'class_2' => [
                                'subject_code' => $b['class']->subject_code,
                                'section' => $b['class']->section,
                                'room' => $b['schedule']->room?->name,
                                'faculty' => $b['class']->Faculty ? $b['class']->Faculty->first_name.' '.$b['class']->Faculty->last_name : null,
                            ],
                            'conflict_type' => $roomConflict ? 'room' : 'faculty',
                        ];
                    }
                }
            }
        }

        return $conflicts;
    }
}
