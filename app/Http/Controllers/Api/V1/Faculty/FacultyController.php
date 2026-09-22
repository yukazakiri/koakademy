<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Faculty;

use App\Http\Controllers\Controller;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Faculty;
use App\Models\User;
use App\Services\GradeEvaluationService;
use App\Services\GradingSystemService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

final class FacultyController extends Controller
{
    /**
     * GET /api/faculty/profile
     *
     * Returns the authenticated faculty member's profile details.
     */
    public function profile(Request $request): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'data' => [
                'id' => $faculty->id,
                'faculty_id_number' => $faculty->faculty_id_number,
                'first_name' => $faculty->first_name,
                'last_name' => $faculty->last_name,
                'middle_name' => $faculty->middle_name,
                'full_name' => $faculty->full_name,
                'email' => $faculty->email,
                'phone_number' => $faculty->phone_number,
                'department' => $faculty->department,
                'office_hours' => $faculty->office_hours,
                'biography' => $faculty->biography,
                'education' => $faculty->education,
                'photo_url' => $faculty->photo_url,
                'status' => $faculty->status,
                'gender' => $faculty->gender,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role?->value,
                    'avatar_url' => $user->avatar_url,
                ],
            ],
        ]);
    }

    /**
     * PUT /api/faculty/profile
     *
     * Updates the authenticated faculty member's profile.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'phone_number' => ['nullable', 'string', 'max:20'],
            'biography' => ['nullable', 'string', 'max:2000'],
            'office_hours' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $faculty->fill($validator->validated());
        $faculty->save();

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => [
                'id' => $faculty->id,
                'faculty_id_number' => $faculty->faculty_id_number,
                'full_name' => $faculty->full_name,
                'email' => $faculty->email,
                'phone_number' => $faculty->phone_number,
                'biography' => $faculty->biography,
                'office_hours' => $faculty->office_hours,
            ],
        ]);
    }

    /**
     * GET /api/faculty/classes
     *
     * Returns all classes assigned to the authenticated faculty member.
     */
    public function classes(Request $request): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $query = Classes::query()
            ->where('faculty_id', $faculty->id)
            ->with([
                'Subject:id,code,title',
                'Room:id,name,class_code',
                'schedules' => function ($q): void {
                    $q->select('id', 'class_id', 'day_of_week', 'start_time', 'end_time', 'room_id')
                        ->with('room:id,name,class_code')
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
            ])
            ->withCount('class_enrollments');

        // Allow filtering by school_year and semester
        if ($request->filled('school_year')) {
            $query->where('school_year', $request->input('school_year'));
        }

        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->input('semester'));
        }

        $classes = $query->orderBy('school_year', 'desc')->orderBy('semester', 'desc')->get();

        return response()->json([
            'data' => $classes->map(fn (Classes $class): array => [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->Subject?->title ?? $class->subject_code,
                'section' => $class->section,
                'academic_year' => $class->academic_year,
                'school_year' => $class->school_year,
                'semester' => $class->semester,
                'classification' => $class->classification,
                'maximum_slots' => $class->maximum_slots,
                'enrolled_count' => $class->class_enrollments_count,
                'room' => $class->Room ? [
                    'id' => $class->Room->id,
                    'name' => $class->Room->name,
                    'code' => $class->Room->class_code,
                ] : null,
                'schedules' => $class->schedules->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'time_range' => $schedule->start_time && $schedule->end_time
                        ? $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i')
                        : null,
                    'room' => $schedule->room ? [
                        'id' => $schedule->room->id,
                        'name' => $schedule->room->name,
                        'code' => $schedule->room->class_code,
                    ] : null,
                ])->values()->toArray(),
            ])->values()->toArray(),
            'meta' => [
                'faculty_id' => $faculty->id,
                'faculty_name' => $faculty->full_name,
                'total_classes' => $classes->count(),
            ],
        ]);
    }

    /**
     * GET /api/faculty/classes/{classId}
     *
     * Returns detailed information about a specific class (must belong to the authenticated faculty).
     */
    public function classDetails(Request $request, int|string $classId): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->with([
                'Subject:id,code,title',
                'Room:id,name,class_code',
                'schedules.room:id,name,class_code',
                'class_enrollments' => function ($q): void {
                    $q->with('student:id,student_id,first_name,last_name,middle_name,email,course_id')
                        ->with('student.Course:id,code,title');
                },
            ])
            ->withCount('class_enrollments')
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        return response()->json([
            'data' => [
                'id' => $class->id,
                'subject_code' => $class->subject_code,
                'subject_title' => $class->Subject?->title ?? $class->subject_code,
                'section' => $class->section,
                'academic_year' => $class->academic_year,
                'school_year' => $class->school_year,
                'semester' => $class->semester,
                'classification' => $class->classification,
                'maximum_slots' => $class->maximum_slots,
                'enrolled_count' => $class->class_enrollments_count,
                'room' => $class->Room ? [
                    'id' => $class->Room->id,
                    'name' => $class->Room->name,
                    'code' => $class->Room->class_code,
                ] : null,
                'schedules' => $class->schedules->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'time_range' => $schedule->start_time && $schedule->end_time
                        ? $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i')
                        : null,
                    'room' => $schedule->room ? [
                        'id' => $schedule->room->id,
                        'name' => $schedule->room->name,
                        'code' => $schedule->room->class_code,
                    ] : null,
                ])->values()->toArray(),
                'students' => $class->class_enrollments->map(fn (ClassEnrollment $enrollment): array => [
                    'enrollment_id' => $enrollment->id,
                    'student_id' => $enrollment->student?->student_id,
                    'full_name' => $enrollment->student ? mb_trim("{$enrollment->student->last_name}, {$enrollment->student->first_name} {$enrollment->student->middle_name}") : null,
                    'email' => $enrollment->student?->email,
                    'course' => $enrollment->student?->Course ? [
                        'code' => $enrollment->student->Course->code,
                        'title' => $enrollment->student->Course->title,
                    ] : null,
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade,
                        'midterm' => $enrollment->midterm_grade,
                        'finals' => $enrollment->finals_grade,
                        'total_average' => $enrollment->total_average,
                        'is_finalized' => $enrollment->is_grades_finalized,
                    ],
                ])->values()->toArray(),
            ],
        ]);
    }

    /**
     * GET /api/faculty/schedules
     *
     * Returns all schedules grouped by day for the authenticated faculty member.
     */
    public function schedules(Request $request): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $query = Classes::query()
            ->where('faculty_id', $faculty->id)
            ->with([
                'Subject:id,code,title',
                'Room:id,name,class_code',
                'schedules' => function ($q): void {
                    $q->select('id', 'class_id', 'day_of_week', 'start_time', 'end_time', 'room_id')
                        ->with('room:id,name,class_code')
                        ->orderBy('day_of_week')
                        ->orderBy('start_time');
                },
            ]);

        if ($request->filled('school_year')) {
            $query->where('school_year', $request->input('school_year'));
        }

        if ($request->filled('semester')) {
            $query->where('semester', (int) $request->input('semester'));
        }

        $classes = $query->get();

        // Organize by day
        $daysOfWeek = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $organized = [];

        foreach ($daysOfWeek as $day) {
            $dayClasses = [];

            foreach ($classes as $class) {
                $daySchedules = $class->schedules->where('day_of_week', $day);

                foreach ($daySchedules as $schedule) {
                    $dayClasses[] = [
                        'class_id' => $class->id,
                        'section' => $class->section,
                        'subject_code' => $class->subject_code,
                        'subject_title' => $class->Subject?->title ?? $class->subject_code,
                        'school_year' => $class->school_year,
                        'semester' => $class->semester,
                        'schedule' => [
                            'id' => $schedule->id,
                            'start_time' => $schedule->start_time?->format('H:i'),
                            'end_time' => $schedule->end_time?->format('H:i'),
                            'time_range' => $schedule->start_time && $schedule->end_time
                                ? $schedule->start_time->format('H:i').' - '.$schedule->end_time->format('H:i')
                                : null,
                            'room' => $schedule->room ? [
                                'id' => $schedule->room->id,
                                'name' => $schedule->room->name,
                                'code' => $schedule->room->class_code,
                            ] : null,
                        ],
                    ];
                }
            }

            usort($dayClasses, fn (array $a, array $b): int => strcmp($a['schedule']['start_time'] ?? '', $b['schedule']['start_time'] ?? ''));
            $organized[mb_strtolower($day)] = $dayClasses;
        }

        return response()->json([
            'data' => [
                'faculty' => [
                    'id' => $faculty->id,
                    'faculty_id_number' => $faculty->faculty_id_number,
                    'full_name' => $faculty->full_name,
                ],
                'organized_schedules' => $organized,
                'total_classes' => $classes->count(),
                'total_scheduled_classes' => $classes->filter(fn ($c) => $c->schedules->isNotEmpty())->count(),
            ],
        ]);
    }

    /**
     * GET /api/faculty/students
     *
     * Returns all unique students enrolled across all of the faculty member's classes.
     */
    public function students(Request $request): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $classIds = Classes::query()
            ->where('faculty_id', $faculty->id)
            ->pluck('id');

        if ($classIds->isEmpty()) {
            return response()->json([
                'data' => [],
                'meta' => ['total_students' => 0, 'total_classes' => 0],
            ]);
        }

        $enrollments = ClassEnrollment::query()
            ->whereIn('class_id', $classIds)
            ->with([
                'student:id,student_id,first_name,last_name,middle_name,email,course_id',
                'student.Course:id,code,title',
                'class:id,subject_code,section,school_year,semester',
            ])
            ->when($request->filled('filter.search'), function ($q) use ($request): void {
                $search = $request->input('filter.search');
                $q->whereHas('student', function ($sq) use ($search): void {
                    $sq->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%");
                });
            })
            ->get();

        // Group by student, collect their class enrollments
        $studentMap = [];
        foreach ($enrollments as $enrollment) {
            if (! $enrollment->student) {
                continue;
            }
            $sid = $enrollment->student->id;
            if (! isset($studentMap[$sid])) {
                $studentMap[$sid] = [
                    'id' => $enrollment->student->id,
                    'student_id' => $enrollment->student->student_id,
                    'full_name' => mb_trim("{$enrollment->student->last_name}, {$enrollment->student->first_name} {$enrollment->student->middle_name}"),
                    'first_name' => $enrollment->student->first_name,
                    'last_name' => $enrollment->student->last_name,
                    'email' => $enrollment->student->email,
                    'course' => $enrollment->student->Course ? [
                        'code' => $enrollment->student->Course->code,
                        'title' => $enrollment->student->Course->title,
                    ] : null,
                    'enrolled_classes' => [],
                ];
            }
            $studentMap[$sid]['enrolled_classes'][] = [
                'class_id' => $enrollment->class_id,
                'subject_code' => $enrollment->class?->subject_code,
                'section' => $enrollment->class?->section,
                'school_year' => $enrollment->class?->school_year,
                'semester' => $enrollment->class?->semester,
                'grades' => [
                    'prelim' => $enrollment->prelim_grade,
                    'midterm' => $enrollment->midterm_grade,
                    'finals' => $enrollment->finals_grade,
                    'total_average' => $enrollment->total_average,
                    'is_finalized' => $enrollment->is_grades_finalized,
                ],
            ];
        }

        $students = array_values($studentMap);

        return response()->json([
            'data' => $students,
            'meta' => [
                'total_students' => count($students),
                'total_classes' => $classIds->count(),
            ],
        ]);
    }

    /**
     * GET /api/faculty/classes/{classId}/students
     *
     * Returns all students enrolled in a specific class.
     */
    public function classStudents(Request $request, int|string $classId): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $enrollments = ClassEnrollment::query()
            ->where('class_id', $classId)
            ->with([
                'student:id,student_id,first_name,last_name,middle_name,email,course_id,year_level',
                'student.Course:id,code,title',
            ])
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $enrollments->map(fn (ClassEnrollment $enrollment): array => [
                'enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student?->student_id,
                'full_name' => $enrollment->student
                    ? mb_trim("{$enrollment->student->last_name}, {$enrollment->student->first_name} {$enrollment->student->middle_name}")
                    : null,
                'first_name' => $enrollment->student?->first_name,
                'last_name' => $enrollment->student?->last_name,
                'email' => $enrollment->student?->email,
                'year_level' => $enrollment->student?->year_level ?? null,
                'course' => $enrollment->student?->Course ? [
                    'code' => $enrollment->student->Course->code,
                    'title' => $enrollment->student->Course->title,
                ] : null,
                'grades' => [
                    'prelim' => $enrollment->prelim_grade,
                    'midterm' => $enrollment->midterm_grade,
                    'finals' => $enrollment->finals_grade,
                    'total_average' => $enrollment->total_average,
                    'is_finalized' => $enrollment->is_grades_finalized,
                ],
            ])->values()->toArray(),
            'meta' => [
                'class_id' => $class->id,
                'subject_code' => $class->subject_code,
                'section' => $class->section,
                'total_enrolled' => $enrollments->count(),
            ],
        ]);
    }

    /**
     * GET /api/faculty/classes/{classId}/attendance/sessions
     *
     * Returns all attendance sessions for a specific class.
     */
    public function attendanceSessions(Request $request, int|string $classId): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $sessions = ClassAttendanceSession::query()
            ->where('class_id', $classId)
            ->with(['schedule.room'])
            ->orderByDesc('session_date')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => $sessions->map(fn (ClassAttendanceSession $session): array => [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'starts_at' => $session->starts_at?->format('H:i'),
                'ends_at' => $session->ends_at?->format('H:i'),
                'topic' => $session->topic,
                'notes' => $session->notes,
                'is_locked' => $session->is_locked,
                'locked_at' => $session->locked_at?->toIso8601String(),
                'is_no_meeting' => $session->is_no_meeting,
                'no_meeting_reason' => $session->no_meeting_reason,
                'summary' => $session->summary,
                'schedule' => $session->schedule ? [
                    'id' => $session->schedule->id,
                    'day_of_week' => $session->schedule->day_of_week,
                    'start_time' => $session->schedule->start_time?->format('H:i'),
                    'end_time' => $session->schedule->end_time?->format('H:i'),
                    'room' => $session->schedule->room ? [
                        'name' => $session->schedule->room->name,
                        'code' => $session->schedule->room->class_code,
                    ] : null,
                ] : null,
            ])->values()->toArray(),
            'meta' => [
                'class_id' => $class->id,
                'total_sessions' => $sessions->count(),
            ],
        ]);
    }

    /**
     * POST /api/faculty/classes/{classId}/attendance/sessions
     *
     * Creates a new attendance session for a specific class.
     */
    public function storeAttendanceSession(Request $request, int|string $classId): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'session_date' => ['required', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'schedule_id' => ['nullable', 'integer', 'exists:schedule,id'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_no_meeting' => ['boolean'],
            'no_meeting_reason' => ['nullable', 'string', 'max:500', 'required_if:is_no_meeting,true'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $session = ClassAttendanceSession::create([
            'class_id' => $class->id,
            'schedule_id' => $request->input('schedule_id'),
            'session_date' => $request->input('session_date'),
            'starts_at' => $request->input('starts_at'),
            'ends_at' => $request->input('ends_at'),
            'taken_by' => $faculty->id,
            'topic' => $request->input('topic'),
            'notes' => $request->input('notes'),
            'is_no_meeting' => $request->boolean('is_no_meeting', false),
            'no_meeting_reason' => $request->input('no_meeting_reason'),
        ]);

        return response()->json([
            'message' => 'Attendance session created successfully.',
            'data' => [
                'id' => $session->id,
                'class_id' => $session->class_id,
                'session_date' => $session->session_date?->toDateString(),
                'starts_at' => $session->starts_at?->format('H:i'),
                'ends_at' => $session->ends_at?->format('H:i'),
                'topic' => $session->topic,
                'is_no_meeting' => $session->is_no_meeting,
            ],
        ], 201);
    }

    /**
     * PUT /api/faculty/classes/{classId}/attendance/sessions/{sessionId}
     *
     * Updates an existing attendance session.
     */
    public function updateAttendanceSession(Request $request, int|string $classId, int|string $sessionId): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $session = ClassAttendanceSession::query()
            ->where('id', $sessionId)
            ->where('class_id', $classId)
            ->first();

        if (! $session) {
            return response()->json([
                'error' => true,
                'message' => 'Attendance session not found.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        if ($session->is_locked) {
            return response()->json([
                'error' => true,
                'message' => 'This attendance session is locked and cannot be modified.',
                'code' => 'SESSION_LOCKED',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'session_date' => ['sometimes', 'date'],
            'starts_at' => ['nullable', 'date_format:H:i'],
            'ends_at' => ['nullable', 'date_format:H:i'],
            'topic' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_no_meeting' => ['boolean'],
            'no_meeting_reason' => ['nullable', 'string', 'max:500'],
            'is_locked' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $session->fill($validator->validated());
        if ($request->boolean('is_locked') && ! $session->locked_at) {
            $session->locked_at = now();
        }
        $session->save();

        return response()->json([
            'message' => 'Attendance session updated successfully.',
            'data' => [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'starts_at' => $session->starts_at?->format('H:i'),
                'ends_at' => $session->ends_at?->format('H:i'),
                'topic' => $session->topic,
                'notes' => $session->notes,
                'is_locked' => $session->is_locked,
                'is_no_meeting' => $session->is_no_meeting,
            ],
        ]);
    }

    /**
     * POST /api/faculty/classes/{classId}/attendance/sessions/{sessionId}/records
     *
     * Bulk submit attendance records for all students in a session.
     *
     * Expected payload:
     * { "records": [ { "enrollment_id": 1, "status": "present", "remarks": "..." }, ... ] }
     */
    public function updateAttendanceRecords(Request $request, int|string $classId, int|string $sessionId): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $session = ClassAttendanceSession::query()
            ->where('id', $sessionId)
            ->where('class_id', $classId)
            ->first();

        if (! $session) {
            return response()->json([
                'error' => true,
                'message' => 'Attendance session not found.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        if ($session->is_locked) {
            return response()->json([
                'error' => true,
                'message' => 'This attendance session is locked and cannot be modified.',
                'code' => 'SESSION_LOCKED',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'records' => ['required', 'array', 'min:1'],
            'records.*.enrollment_id' => ['required', 'integer', 'exists:class_enrollments,id'],
            'records.*.status' => ['required', 'string', 'in:present,late,absent,excused'],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $savedRecords = [];
        foreach ($request->input('records') as $recordData) {
            $enrollment = ClassEnrollment::find($recordData['enrollment_id']);
            if (! $enrollment) {
                continue;
            }
            if ($enrollment->class_id !== $classId) {
                continue;
            }

            $record = \App\Models\ClassAttendanceRecord::updateOrCreate(
                [
                    'class_attendance_session_id' => $session->id,
                    'class_enrollment_id' => $enrollment->id,
                ],
                [
                    'class_id' => $class->id,
                    'student_id' => $enrollment->student_id,
                    'status' => $recordData['status'],
                    'remarks' => $recordData['remarks'] ?? null,
                    'marked_by' => $faculty->id,
                    'marked_at' => now(),
                ]
            );

            $savedRecords[] = [
                'id' => $record->id,
                'enrollment_id' => $enrollment->id,
                'status' => $record->status instanceof \App\Enums\AttendanceStatus ? $record->status->value : $record->status,
                'remarks' => $record->remarks,
            ];
        }

        return response()->json([
            'message' => 'Attendance records saved successfully.',
            'data' => $savedRecords,
            'meta' => ['saved_count' => count($savedRecords)],
        ]);
    }

    /**
     * PATCH /api/faculty/classes/{classId}/enrollments/{enrollmentId}/grades
     *
     * Update grades for a specific student enrollment in a class.
     */
    public function updateGrades(Request $request, int|string $classId, int|string $enrollmentId, GradingSystemService $gradingSystem, GradeEvaluationService $gradeEvaluation): JsonResponse
    {
        $faculty = $this->getAuthenticatedFaculty($request);

        if (! $faculty instanceof Faculty) {
            return response()->json([
                'error' => true,
                'message' => 'Faculty record not found for this account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $class = Classes::query()
            ->where('id', $classId)
            ->where('faculty_id', $faculty->id)
            ->first();

        if (! $class) {
            return response()->json([
                'error' => true,
                'message' => 'Class not found or does not belong to your account.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $enrollment = ClassEnrollment::query()
            ->where('id', $enrollmentId)
            ->where('class_id', $classId)
            ->first();

        if (! $enrollment) {
            return response()->json([
                'error' => true,
                'message' => 'Enrollment not found for this class.',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        if ($enrollment->is_grades_finalized) {
            return response()->json([
                'error' => true,
                'message' => 'Grades for this enrollment are already finalized and cannot be modified.',
                'code' => 'GRADES_FINALIZED',
            ], 403);
        }

        $policy = $gradingSystem->ensureConfig();
        if ($policy['input_type'] !== 'numeric') {
            return response()->json([
                'error' => true,
                'message' => 'Symbolic grading policies require a final symbol workflow.',
                'code' => 'SYMBOLIC_POLICY',
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'prelim_grade' => ['nullable', 'numeric', 'min:'.$policy['numeric_min'], 'max:'.$policy['numeric_max']],
            'midterm_grade' => ['nullable', 'numeric', 'min:'.$policy['numeric_min'], 'max:'.$policy['numeric_max']],
            'finals_grade' => ['nullable', 'numeric', 'min:'.$policy['numeric_min'], 'max:'.$policy['numeric_max']],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => true,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
                'code' => 'VALIDATION_ERROR',
            ], 422);
        }

        $data = $validator->validated();

        $evaluation = $gradeEvaluation->calculate([
            'prelim' => $data['prelim_grade'] ?? $enrollment->prelim_grade,
            'midterm' => $data['midterm_grade'] ?? $enrollment->midterm_grade,
            'final' => $data['finals_grade'] ?? $enrollment->finals_grade,
        ], $policy);
        $data['total_average'] = $evaluation['numeric_grade'];
        $data['grading_components'] = $evaluation['components'];
        $data['grade_symbol'] = $evaluation['symbol'];
        $data['grade_outcome'] = $evaluation['outcome'];
        $data['grade_quality_points'] = $evaluation['quality_points'];
        $data['grading_policy_version_id'] = $policy['policy_version_id'] ?? null;

        $enrollment->fill($data);
        $enrollment->save();

        return response()->json([
            'message' => 'Grades updated successfully.',
            'data' => [
                'enrollment_id' => $enrollment->id,
                'class_id' => $enrollment->class_id,
                'student_id' => $enrollment->student_id,
                'prelim_grade' => $enrollment->prelim_grade,
                'midterm_grade' => $enrollment->midterm_grade,
                'finals_grade' => $enrollment->finals_grade,
                'total_average' => $enrollment->total_average,
                'is_finalized' => $enrollment->is_grades_finalized,
                'remarks' => $enrollment->remarks,
            ],
        ]);
    }

    /**
     * Get the authenticated faculty member's record.
     */
    private function getAuthenticatedFaculty(Request $request): ?Faculty
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            return null;
        }

        return Faculty::query()
            ->where('email', $user->email)
            ->first();
    }
}
