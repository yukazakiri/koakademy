<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class StudentClassesController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        // Get the student record linked to this user
        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->with(['Course'])
            ->first();

        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->getFilamentAvatarUrl(),
            'role' => $user->role?->value ?? 'student',
        ];

        if (! $student) {
            return Inertia::render('student/classes/index', [
                'user' => $userData,
                'student_name' => $user->name,
                'course_name' => 'N/A',
                'progress' => ['earned' => 0, 'total' => 0, 'percentage' => 0],
                'curriculum' => [],
                'current_classes' => [],
            ]);
        }

        // --- 1. Fetch Curriculum (Subjects for the student's course) ---
        $curriculumSubjects = Subject::where('course_id', $student->course_id)
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->get();

        // --- 2. Fetch Student's History (All Subject Enrollments) ---
        $enrollments = SubjectEnrollment::where('student_id', $student->id)
            ->with(['subject', 'class.faculty', 'class.schedules.room', 'class.room'])
            ->get();

        // Create a lookup for enrollments by subject_id to map to curriculum
        // We handle multiple enrollments (retakes) by taking the "best" or "latest" one
        // For status checking, we prioritize: Passed > Enrolled > Failed
        $enrollmentMap = [];

        foreach ($enrollments as $enrollment) {
            $subjectId = $enrollment->subject_id;

            // Fix for N/A issues: If subject relation is missing, try to recover info from enrollment fields or class
            $subjectCode = $enrollment->subject?->code
                ?? $enrollment->class?->subject_code
                ?? $enrollment->external_subject_code
                ?? 'Unknown Code';

            $subjectTitle = $enrollment->subject?->title
                ?? $enrollment->class?->subject_title
                ?? $enrollment->external_subject_title
                ?? 'Unknown Subject';

            $subjectUnits = $enrollment->subject?->units
                ?? $enrollment->external_subject_units
                ?? 0;

            // Determine status
            $grade = $enrollment->grade;
            $status = 'ongoing'; // Default if enrolled in current sem

            if ($enrollment->is_credited) {
                $status = 'completed';
            } elseif ($grade !== null) {
                // Adjust passing logic based on your grading system (assuming 3.0 or 75 is passing)
                // If numeric 1.0-5.0: <= 3.0 is pass. If 0-100: >= 75 is pass.
                // Assuming standard PH system: 1.0 (High) - 3.0 (Pass) - 5.0 (Fail)
                // Also check for 0 which might mean no grade yet or withdrawn
                if (($grade <= 3.0 && $grade >= 1.0) || ($grade >= 75)) {
                    $status = 'completed';
                } elseif ($grade === 5.0 || ($grade < 75 && $grade > 0)) {
                    $status = 'failed';
                }
            }

            // Calculate units earned
            // Prevent double counting units for retakes
            // We'll calculate total earned from the curriculum map later to be safe,
            // but for extra subjects we might need this.

            $data = [
                'enrollment_id' => $enrollment->id,
                'grade' => $grade,
                'status' => $status,
                'remarks' => $enrollment->remarks,
                'school_year' => $enrollment->school_year,
                'semester' => $enrollment->semester,
                'code' => $subjectCode,
                'title' => $subjectTitle,
                'units' => $subjectUnits,
            ];

            // Logic to keep the "best" status in the map (e.g., if failed then passed, show passed)
            if (! isset($enrollmentMap[$subjectId]) || $status === 'completed') {
                $enrollmentMap[$subjectId] = $data;
            }
        }

        // --- 3. Build Curriculum Checklist ---
        $curriculum = [];
        $totalUnits = 0;
        $earnedUnits = 0;

        foreach ($curriculumSubjects as $subject) {
            $year = $subject->academic_year;
            $sem = $subject->semester;

            // Initialize group
            if (! isset($curriculum[$year])) {
                $curriculum[$year] = [];
            }
            if (! isset($curriculum[$year][$sem])) {
                $curriculum[$year][$sem] = [];
            }

            $enrolledData = $enrollmentMap[$subject->id] ?? null;
            $status = $enrolledData['status'] ?? 'pending';

            // Check if currently taking (if school year/sem matches current)
            // Note: Use fuzzy matching or precise based on data format
            // If enrollment exists and no grade yet, it's likely ongoing
            if ($enrolledData && $enrolledData['grade'] === null && ! $enrolledData['remarks']) {
                // Double check specific semester if needed, but usually null grade implies ongoing
                $status = 'ongoing';
            }

            $curriculum[$year][$sem][] = [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $subject->units,
                'status' => $status,
                'grade' => $enrolledData['grade'] ?? null,
                'remarks' => $enrolledData['remarks'] ?? null,
            ];

            $totalUnits += $subject->units;
            if ($status === 'completed') {
                $earnedUnits += $subject->units;
            }
        }

        // --- 4. Get Current Classes (Detailed for Schedule Tab) ---
        // We reuse the logic from previous implementation but ensure data robustness
        $currentClasses = [];
        // Filter enrollments for current active classes
        $currentEnrollments = $student->getCurrentClasses();

        foreach ($currentEnrollments as $ce) {
            $class = $ce->class;
            if (! $class) {
                continue;
            }

            $subject = $class->subject
                ?? $class->subjectByCode
                ?? $class->subjectByCodeFallback
                ?? $class->shsSubject;

            // Format schedule
            $schedule = 'TBA';
            $days = $class->days ?? $class->day ?? null;
            $startTime = $class->start_time ?? null;

            if ($days && $startTime) {
                $dayAbbreviations = is_array($days) ? implode('/', $days) : $days;
                $timeFormat = date('g:i A', strtotime((string) $startTime));
                if ($class->end_time) {
                    $timeFormat .= ' - '.date('g:i A', strtotime((string) $class->end_time));
                }
                $schedule = sprintf('%s %s', $dayAbbreviations, $timeFormat);
            }

            // Load schedules relationship if not already loaded
            if (! $class->relationLoaded('schedules')) {
                $class->load('schedules');
            }

            $currentClasses[] = [
                'id' => $class->id,
                'subject_code' => $class->subject_code ?? 'N/A',
                'subject_title' => $subject?->title ?? $class->subject_title ?? 'Unknown Subject',
                'section' => $class->section ?? 'N/A',
                'units' => $subject?->units ?? 0,
                'faculty_name' => $class->faculty?->full_name ?? 'TBA',
                'schedule' => $schedule,
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
                'schedules' => $class->schedules->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time?->format('H:i'),
                    'end_time' => $schedule->end_time?->format('H:i'),
                    'room_id' => $schedule->room_id,
                ]),
                'settings' => $class->settings,
                'accent_color' => $class->settings['accent_color'] ?? null,
                'background_color' => $class->settings['background_color'] ?? null,
            ];
        }

        // Get Rooms for filters
        $rooms = \App\Models\Room::select('id', 'name')->orderBy('name')->get();

        return Inertia::render('student/classes/index', [
            'user' => $userData,
            'student_name' => $student->full_name ?? $user->name,
            'course_name' => $student->Course?->title ?? 'N/A',
            'progress' => [
                'earned' => $earnedUnits,
                'total' => $totalUnits,
                'percentage' => $totalUnits > 0 ? round(($earnedUnits / $totalUnits) * 100) : 0,
            ],
            // Return curriculum as array for easier mapping in frontend if needed,
            // but preserving Year/Sem keys is better. Inertia handles objects fine.
            'curriculum' => $curriculum,
            'faculty_data' => [
                'classes' => $currentClasses,
                'stats' => [], // Add stats if needed
            ],
            'rooms' => $rooms,
        ]);
    }
}
