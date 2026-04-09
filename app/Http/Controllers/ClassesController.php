<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ShsStrand;
use App\Services\GeneralSettingsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class ClassesController extends Controller
{
    public function index(GeneralSettingsService $settingsService): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        /** @var \App\Models\User $user */

        // Get global settings
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        // Get all SHS strands for class creation
        $shsStrands = ShsStrand::with('track')
            ->orderBy('strand_name')
            ->get()
            ->map(fn ($strand): array => [
                'id' => (string) $strand->id,
                'strand_name' => $strand->strand_name,
                'description' => $strand->description,
                'track_id' => (int) $strand->track_id, // Add track_id for frontend filtering
                'track_name' => $strand->track?->track_name ?? null,
            ]);

        // Fetch faculty data and classes following the same logic as the original route
        $faculty = null;
        $stats = [];
        $classes = [];

        if ($user && method_exists($user, 'isFaculty') && $user->isFaculty()) {
            $faculty = \App\Models\Faculty::where('email', $user->email)->first();

            if ($faculty) {
                // Ensure faculty name is available in the user object or passed separately
                // The frontend uses 'user.name', which is already set.
                // However, for the 'Assigned Faculty' field in class creation, it might be useful to pass the faculty ID and name explicitly.

                $activeClassesCount = $faculty->classes()
                    ->currentAcademicPeriod()
                    ->count();

                // Total Students Count (Unique students across all classes)
                $totalStudentsCount = \App\Models\ClassEnrollment::whereIn('class_id', $faculty->classes()->currentAcademicPeriod()->pluck('id'))
                    ->distinct('student_id')
                    ->count();

                // All Classes
                $classesData = $faculty->classes()
                    ->currentAcademicPeriod()
                    ->with(['subject', 'SubjectByCodeFallback', 'ShsSubject', 'Room', 'schedules.room'])
                    ->withCount('class_enrollments')
                    ->get()
                    ->map(function ($class): array {
                        // Get the first subject from subjects collection if it exists
                        $firstSubject = $class->subjects->first();

                        // If no subjects collection, use single subject relationship
                        if (! $firstSubject) {
                            $firstSubject = $class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback);
                        }

                        return [
                            'id' => $class->id,
                            'subject_code' => $firstSubject?->code ?? $class->subject_code ?? 'N/A',
                            'subject_title' => $firstSubject?->title ?? 'N/A',
                            'section' => $class->section ?? 'N/A',
                            'school_year' => $class->school_year ?? 'N/A',
                            'semester' => $class->semester ?? 'N/A',
                            'room' => $class->Room?->name ?? 'TBA',
                            'room_id' => $class->room_id,
                            'faculty_id' => $class->faculty_id,
                            'maximum_slots' => $class->maximum_slots,
                            'students_count' => $class->class_enrollments_count ?? 0,
                            'classification' => $class->classification,
                            'strand_id' => $class->shs_strand_id,
                            'subject_id' => $class->subject_id,
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
                    });
                // dd($classesData);

                $stats = [
                    [
                        'label' => 'Total Classes',
                        'value' => $activeClassesCount,
                        'icon' => 'book',
                        'trend' => '+0',
                        'trendDirection' => 'neutral',
                    ],
                    [
                        'label' => 'Total Students',
                        'value' => $totalStudentsCount,
                        'icon' => 'users',
                        'trend' => '+0%',
                        'trendDirection' => 'neutral',
                    ],
                    [
                        'label' => 'Active This Semester',
                        'value' => $activeClassesCount,
                        'icon' => 'clock',
                        'trend' => '0%',
                        'trendDirection' => 'neutral',
                    ],
                    [
                        'label' => 'Avg Students/Class',
                        'value' => $activeClassesCount > 0 ? round($totalStudentsCount / $activeClassesCount) : 0,
                        'icon' => 'activity',
                        'trend' => '+0',
                        'trendDirection' => 'neutral',
                    ],
                ];

                $classes = $classesData;
            }
        }

        return Inertia::render('faculty/classes/index', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->value ?? 'user', // Send role value (e.g. 'instructor') instead of label
            ],
            'current_semester' => (string) $currentSemester,
            'current_school_year' => $currentSchoolYear,
            'current_faculty' => $faculty ? [
                'id' => $faculty->id,
                'name' => $faculty->full_name ?? $user->name,
            ] : null,
            'faculty_data' => [
                'stats' => $stats,
                'classes' => $classes,
            ],
            'shs_strands' => $shsStrands,
            'rooms' => \App\Models\Room::select('id', 'name')->orderBy('name')->get(),
            'flash' => session('flash'),
        ]);
    }

    public function getStrandSubjects(Request $request)
    {
        $strandId = $request->query('strand_id');

        if (! $strandId) {
            return response()->json(['strand_subjects' => []]);
        }

        $subjects = \App\Models\StrandSubject::where('strand_id', $strandId)
            ->orderBy('title')
            ->get()
            ->map(fn ($subject): array => [
                'id' => (string) $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'description' => $subject->description,
                'grade_year' => $subject->grade_year,
                'semester' => $subject->semester,
            ]);

        return response()->json(['strand_subjects' => $subjects]);
    }

    public function store(Request $request, GeneralSettingsService $settingsService)
    {
        $user = Auth::user();

        if (! $user->isFaculty()) {
            return redirect()->back()->with('error', 'Only faculty members can create classes.');
        }

        $faculty = \App\Models\Faculty::where('email', $user->email)->first();

        if (! $faculty) {
            return redirect()->back()->with('error', 'Faculty record not found.');
        }

        // Get General Settings
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        // Validate the request
        $data = $request->validate([
            'classification' => 'required|in:college,shs',
            'course_codes' => 'nullable|array',
            'subject_ids' => 'nullable|array',
            'shs_track_id' => 'nullable|string',
            'shs_strand_id' => 'nullable|string',
            'subject_code' => 'required|string|max:255',
            'academic_year' => 'nullable|string',
            'grade_level' => 'nullable|string',
            'semester' => 'nullable|string',
            'school_year' => 'nullable|string',
            'section' => 'required|string|max:10',
            'room_id' => 'required|string',
            'maximum_slots' => 'required|integer|min:1',
        ]);

        // Auto-assign faculty
        $data['faculty_id'] = (string) $faculty->id;

        // Auto-assign semester and school year if not provided or empty
        if (empty($data['semester'])) {
            $data['semester'] = (string) $currentSemester;
        }

        if (empty($data['school_year'])) {
            $data['school_year'] = $currentSchoolYear;
        }

        // Create the class
        $class = \App\Models\Classes::create($data);

        // Re-assign faculty_id explicitly if it's missing (sometimes fillable or casting issues)
        if (! $class->faculty_id) {
            $class->faculty_id = (string) $faculty->id;
            $class->save();
        }

        return redirect('/classes')->with('success', 'Class created successfully!');
    }

    public function show(string $id)
    {
        $class = \App\Models\Classes::with(['schedules.room', 'faculty', 'room', 'ShsStrand', 'ShsSubject', 'subject', 'SubjectByCodeFallback'])
            ->findOrFail($id);

        // Determine subject title logic
        $firstSubject = $class->subjects->first();
        if (! $firstSubject) {
            $firstSubject = $class->isShs() ? $class->ShsSubject : ($class->subject ?: $class->SubjectByCodeFallback);
        }
        $subjectTitle = $firstSubject?->title ?? 'N/A';

        return response()->json([
            'class' => [
                'id' => $class->id,
                'room_id' => $class->room_id,
                'faculty_id' => $class->faculty_id,
                'maximum_slots' => $class->maximum_slots,
                'semester' => $class->semester,
                'school_year' => $class->school_year,
                'section' => $class->section,
                'classification' => $class->classification,
                'strand_id' => $class->shs_strand_id,
                'subject_code' => $class->subject_code,
                'subject_title' => $subjectTitle,
                'schedules' => $class->schedules->map(fn ($schedule): array => [
                    'id' => $schedule->id,
                    'day_of_week' => $schedule->day_of_week,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                    'room_id' => $schedule->room_id,
                    'room' => $schedule->room,
                ]),
            ],
        ]);
    }

    public function update(Request $request, string $id)
    {
        $class = \App\Models\Classes::findOrFail($id);

        // Basic validation
        $validated = $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'faculty_id' => 'nullable|exists:faculty,id',
            'maximum_slots' => 'required|integer|min:1',
            'section' => 'required|string|max:10',
            'semester' => 'required|string',
            'school_year' => 'required|string',
            'classification' => 'nullable|string',
            'strand_id' => 'nullable|string',
            'subject_code' => 'nullable|string',
            'schedules' => ['array', new \App\Rules\ScheduleOverlapRule],
            'schedules.*.day_of_week' => 'required|string',
            'schedules.*.start_time' => 'required',
            'schedules.*.end_time' => 'required',
            'schedules.*.room_id' => 'required|exists:rooms,id',
        ]);

        // Update basic class info
        $updateData = [
            'room_id' => $validated['room_id'],
            'faculty_id' => $validated['faculty_id'],
            'maximum_slots' => $validated['maximum_slots'],
            'section' => $validated['section'],
            'semester' => $validated['semester'],
            'school_year' => $validated['school_year'],
        ];

        // Add SHS-specific fields if it's an SHS class
        if ($validated['classification'] === 'shs') {
            $updateData['shs_strand_id'] = $validated['strand_id'] ? (int) $validated['strand_id'] : null;
            $updateData['subject_code'] = $validated['subject_code'];
        }

        $class->update($updateData);

        // Update schedules
        if (isset($validated['schedules'])) {
            // Delete existing schedules
            $class->schedules()->delete();

            // Create new schedules
            foreach ($validated['schedules'] as $scheduleData) {
                // Ensure room_id is set for each schedule, defaulting to class room if not provided (though validation requires it)
                $scheduleData['room_id'] ??= $class->room_id;

                // We need to associate the schedule with the class
                $class->schedules()->create($scheduleData);
            }
        }

        return redirect()->back()->with('success', 'Class updated successfully');
    }

    /**
     * Get activity logs for a specific class
     */
    public function activityLog(string $id)
    {
        $class = \App\Models\Classes::findOrFail($id);

        // Verify the current user has access to this class
        $user = Auth::user();
        if (! $user || ! $user->isFaculty()) {
            abort(403);
        }

        $faculty = \App\Models\Faculty::where('email', $user->email)->first();
        if (! $faculty || $class->faculty_id !== $faculty->id) {
            abort(403, 'You do not have access to this class');
        }

        // Query activities for this class and related entities
        $activities = \Spatie\Activitylog\Models\Activity::query()
            ->where(function ($query) use ($id): void {
                // Activities on the class itself
                $query->where(function ($q) use ($id): void {
                    $q->where('subject_type', \App\Models\Classes::class)
                        ->where('subject_id', $id);
                })
                    // Activities on class posts
                    ->orWhere(function ($q) use ($id): void {
                        $q->where('subject_type', \App\Models\ClassPost::class)
                            ->whereIn('subject_id', function ($subQuery) use ($id): void {
                                $subQuery->select('id')
                                    ->from('class_posts')
                                    ->where('class_id', $id);
                            });
                    })
                    // Activities on class enrollments
                    ->orWhere(function ($q) use ($id): void {
                        $q->where('subject_type', \App\Models\ClassEnrollment::class)
                            ->whereIn('subject_id', function ($subQuery) use ($id): void {
                                $subQuery->select('id')
                                    ->from('class_enrollments')
                                    ->where('class_id', $id);
                            });
                    });
            })
            ->latest()
            ->take(50)
            ->get();

        // Format activities for the frontend
        $formattedActivities = $activities->map(fn (\Spatie\Activitylog\Models\Activity $activity): array => [
            'id' => $activity->id,
            'action' => $this->formatActivityAction($activity),
            'details' => $this->formatActivityDetails($activity),
            'time' => $activity->created_at?->shiftTimezone(config('app.timezone'))?->diffForHumans() ?? '',
            'timestamp' => format_timestamp($activity->created_at),
            'event' => $activity->event,
            'type' => class_basename($activity->subject_type),
        ]);

        return response()->json([
            'activities' => $formattedActivities,
            'class_name' => $class->record_title,
        ]);
    }

    /**
     * Format activity action for display
     */
    private function formatActivityAction(\Spatie\Activitylog\Models\Activity $activity): string
    {
        $attributes = $activity->properties->get('attributes', []);
        $oldValues = $activity->properties->get('old', []);

        // Handle ClassEnrollment events specially
        if ($activity->subject_type === \App\Models\ClassEnrollment::class) {
            if ($activity->event === 'created') {
                return 'New student enrolled';
            }
            if ($activity->event === 'deleted') {
                return 'Student removed from class';
            }

            // Grade updates
            $gradeFields = ['prelim_grade', 'midterm_grade', 'finals_grade'];
            foreach ($gradeFields as $field) {
                if (isset($attributes[$field])) {
                    $label = match ($field) {
                        'prelim_grade' => 'Prelim',
                        'midterm_grade' => 'Midterm',
                        'finals_grade' => 'Finals',
                    };
                    $oldVal = $oldValues[$field] ?? 'none';
                    $newVal = $attributes[$field] ?? 'none';

                    return "Updated {$label} grade: {$oldVal} → {$newVal}";
                }
            }

            if (isset($attributes['class_id']) && isset($oldValues['class_id'])) {
                return 'Student moved to different section';
            }

            if (isset($attributes['is_grades_finalized']) && $attributes['is_grades_finalized']) {
                return 'Finalized student grades';
            }

            return 'Updated enrollment';
        }

        // Handle ClassPost events
        if ($activity->subject_type === \App\Models\ClassPost::class) {
            return match ($activity->event) {
                'created' => 'Created new post',
                'updated' => 'Updated post',
                'deleted' => 'Deleted post',
                default => 'Modified post',
            };
        }

        // Handle Class events
        if ($activity->subject_type === \App\Models\Classes::class) {
            if ($activity->event === 'updated' && ! empty($attributes)) {
                $changes = [];
                $fieldLabels = [
                    'section' => 'Section',
                    'maximum_slots' => 'Max slots',
                    'faculty_id' => 'Faculty',
                    'room_id' => 'Room',
                    'semester' => 'Semester',
                    'school_year' => 'School year',
                ];

                foreach ($attributes as $field => $newValue) {
                    if (isset($fieldLabels[$field])) {
                        $oldVal = $oldValues[$field] ?? 'none';
                        $changes[] = "{$fieldLabels[$field]}: {$oldVal} → {$newValue}";
                    }
                }

                if ($changes !== []) {
                    return 'Updated class: '.implode(', ', array_slice($changes, 0, 2));
                }
            }

            return match ($activity->event) {
                'created' => 'Class created',
                'updated' => 'Updated class settings',
                'deleted' => 'Class deleted',
                default => 'Modified class',
            };
        }

        return ucfirst($activity->event ?? 'Modified').' item';
    }

    /**
     * Format activity details for display
     */
    private function formatActivityDetails(\Spatie\Activitylog\Models\Activity $activity): string
    {
        // For enrollments - show student name
        if ($activity->subject_type === \App\Models\ClassEnrollment::class && $activity->subject) {
            $enrollment = $activity->subject;
            $student = $enrollment->student;
            if ($student) {
                return "{$student->first_name} {$student->last_name}";
            }

            return 'Student enrollment';
        }

        // For posts - show post title
        if ($activity->subject_type === \App\Models\ClassPost::class && $activity->subject) {
            $post = $activity->subject;

            return $post->title ?? 'Post';
        }

        // For class changes - use description
        if ($activity->description) {
            return $activity->description;
        }

        return '';
    }
}
