<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ClassPostType;
use App\Http\Requests\StoreAttendanceSessionRequest;
use App\Http\Requests\StoreClassPostRequest;
use App\Http\Requests\UpdateAttendanceRecordsRequest;
use App\Http\Requests\UpdateAttendanceSessionRequest;
use App\Http\Requests\UpdateClassSchedulesRequest;
use App\Jobs\GenerateAttendancePdfJob;
use App\Jobs\GenerateStudentListPdfJob;
use App\Models\ClassAttendanceRecord;
use App\Models\ClassAttendanceSession;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\Faculty;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Filament\Actions\Action;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class FacultyClassController extends Controller
{
    public function show(Classes $class): Response
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $faculty = $this->assertFacultyOwnsClass($class);

        // Optimized eager loading with nested relationships
        $class->loadMissing([
            'Room',
            'schedules.room',
            'class_enrollments.student',
        ]);

        // Resolve subject efficiently - check classification first
        $primarySubject = null;
        if ($class->classification === 'shs') {
            $primarySubject = $class->ShsSubject;
        } else {
            $primarySubject = $class->subject ?: $class->SubjectByCodeFallback;
        }

        // Try subjects attribute only if no primary subject found
        if (! $primarySubject) {
            $primarySubject = $class->subjects->first();
        }

        $schedule = $class->schedules
            ->sortBy(fn ($schedule) => $schedule->start_time?->format('H:i') ?? '00:00')
            ->map(function ($schedule) use ($class): array {
                $start = $schedule->formatted_start_time ?? $schedule->start_time?->format('g:i A');
                $end = $schedule->formatted_end_time ?? $schedule->end_time?->format('g:i A');

                return [
                    'id' => $schedule->id,
                    'day' => ucfirst(mb_strtolower((string) $schedule->day_of_week ?? '')),
                    'room' => $schedule->room?->name ?? $class->Room?->name ?? 'TBA',
                    'start' => $start,
                    'end' => $end,
                    'start_24h' => $schedule->start_time?->format('H:i'),
                    'end_24h' => $schedule->end_time?->format('H:i'),
                    'notes' => $schedule->notes ?? null,
                ];
            })
            ->values()
            ->all();

        $students = $class->class_enrollments
            ->map(function ($enrollment): array {
                $student = $enrollment->student;
                $nameSegments = collect([
                    $student?->first_name,
                    $student?->middle_name,
                    $student?->last_name,
                ])
                    ->filter()
                    ->implode(' ');

                $displayName = $student?->full_name
                    ?? $nameSegments
                    ?? $student?->name
                    ?? 'Student #'.$enrollment->student_id;

                return [
                    'id' => $enrollment->id,
                    'student_db_id' => $student?->id ?? null,
                    'student_id' => $student?->student_type === \App\Enums\StudentType::SeniorHighSchool
                        ? ($student?->lrn ?? 'N/A')
                        : ($student?->student_id !== null ? (string) $student->student_id : 'N/A'),
                    'name' => $displayName,
                    'email' => $student?->email,
                    'status' => $enrollment->status ? 'Active' : 'Inactive',
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade,
                        'midterm' => $enrollment->midterm_grade,
                        'final' => $enrollment->finals_grade,
                        'average' => $enrollment->total_average,
                    ],
                ];
            })
            ->values()
            ->all();

        $classData = [
            'id' => $class->id,
            'subject_code' => $primarySubject?->code ?? $class->subject_code ?? 'N/A',
            'subject_title' => $primarySubject?->title ?? 'N/A',
            'section' => $class->section ?? 'N/A',
            'school_year' => $class->school_year ?? 'N/A',
            'semester' => $class->semester ?? 'N/A',
            'start_date' => $class->start_date?->toDateString(),
            'room' => $class->Room?->name ?? 'TBA',
            'classification' => $class->classification ?? 'college',
            'course_codes' => $class->formatted_course_codes ?? null,
            'maximum_slots' => $class->maximum_slots,
            'students_count' => $class->class_enrollments->count(),
            'schedules' => $class->schedules->map(fn ($schedule): array => [
                'id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i'),
                'end_time' => $schedule->end_time?->format('H:i'),
                'room_id' => $schedule->room_id,
            ]),
            'settings' => $class->settings ?? Classes::getDefaultSettings(),
        ];

        $metrics = [
            [
                'label' => 'Enrolled Students',
                'value' => $classData['students_count'],
                'meta' => $classData['maximum_slots'] ? sprintf('of %s slots', $classData['maximum_slots']) : null,
                'icon' => 'users',
            ],
            [
                'label' => 'Sessions / Week',
                'value' => count($schedule),
                'meta' => 'Scheduled meetings',
                'icon' => 'calendar',
            ],
            [
                'label' => 'Class Type',
                'value' => ucfirst((string) $classData['classification']),
                'meta' => $class->isShs() ? 'Senior High' : 'College',
                'icon' => 'bookmark',
            ],
            [
                'label' => 'Room Assignment',
                'value' => $classData['room'],
                'meta' => 'Primary room',
                'icon' => 'map',
            ],
        ];

        $quickActions = [
            [
                'label' => 'Edit Class Details',
                'description' => 'Update subject info, section, or schedules.',
                'icon' => 'edit',
                'href' => '#',
            ],
            [
                'label' => 'Manage Students',
                'description' => 'Add or remove enrolled students.',
                'icon' => 'users',
                'href' => '#',
            ],
            [
                'label' => 'Class Settings',
                'description' => 'Attendance rules, grading policies, and more.',
                'icon' => 'settings',
                'href' => '#',
            ],
            [
                'label' => 'Post Announcement',
                'description' => 'Share reminders or upload resources.',
                'icon' => 'megaphone',
                'href' => '#',
            ],
        ];

        $teacher = [
            'id' => $faculty->id,
            'name' => $faculty->full_name ?? 'TBA',
            'email' => $faculty->email,
            'department' => $faculty->department,
            'photo_url' => $faculty->getFilamentAvatarUrl(),
        ];

        $classPosts = ClassPost::where('class_id', $class->id)
            ->latest()
            ->get()
            ->map(function (ClassPost $post): array {
                $attachments = collect($post->attachments ?? [])
                    ->map(fn ($attachment): array => [
                        'name' => $attachment['name'] ?? basename((string) ($attachment['url'] ?? 'Attachment')),
                        'url' => $attachment['url'] ?? '',
                        'kind' => $attachment['kind'] ?? 'link',
                    ])
                    ->values();

                return [
                    'id' => $post->id,
                    'title' => $post->title,
                    'content' => $post->content,
                    'type' => $post->type instanceof ClassPostType ? $post->type->value : (string) $post->type,
                    'status' => $post->status ?? 'backlog',
                    'priority' => $post->priority ?? 'medium',
                    'start_date' => $post->start_date?->toDateString(),
                    'due_date' => $post->due_date?->toDateString(),
                    'progress_percent' => $post->progress_percent ?? 0,
                    'total_points' => $post->total_points,
                    'assigned_faculty_id' => $post->assigned_faculty_id,
                    'attachments' => $attachments,
                    'created_at' => format_timestamp($post->created_at),
                ];
            });

        $attendance = $this->buildAttendancePayload($class);

        // Cache rooms list since it rarely changes
        $rooms = cache()->remember('rooms_list', 300, fn () => Room::query()
            ->select(['id', 'name'])
            ->orderBy('name')
            ->get()
            ->map(fn ($room): array => [
                'id' => $room->id,
                'name' => $room->name,
            ]));

        return Inertia::render('faculty/classes/show', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'User',
            ],
            'current_faculty' => [
                'id' => $faculty->id,
                'name' => $faculty->full_name ?? $faculty->name ?? 'Faculty',
                'email' => $faculty->email,
            ],
            'classData' => $classData,
            'metrics' => $metrics,
            'schedule' => $schedule,
            'students' => $students,
            'teacher' => $teacher,
            'quick_actions' => $quickActions,
            'posts' => $classPosts,
            'auto_average' => (bool) config('class_grading.auto_calculate_average', true),
            'attendance' => $attendance,
            'rooms' => $rooms,
            'flash' => session('flash'),
        ]);
    }

    public function getQuickActionData(Classes $class): \Illuminate\Http\JsonResponse
    {
        $user = Auth::user();

        if (! $user) {
            abort(403);
        }

        $this->assertFacultyOwnsClass($class);

        $class->loadMissing([
            'class_enrollments.student',
            'attendanceSessions',
            'schedules.room',
        ]);

        // Prepare Students Data
        $students = $class->class_enrollments
            ->map(function ($enrollment): array {
                $student = $enrollment->student;
                $nameSegments = collect([
                    $student?->first_name,
                    $student?->middle_name,
                    $student?->last_name,
                ])
                    ->filter()
                    ->implode(' ');

                $displayName = $student?->full_name
                    ?? $nameSegments
                    ?? $student?->name
                    ?? 'Student #'.$enrollment->student_id;

                return [
                    'id' => $enrollment->id,
                    'student_db_id' => $student?->id ?? null,
                    'student_id' => $student?->student_type === \App\Enums\StudentType::SeniorHighSchool
                        ? ($student?->lrn ?? 'N/A')
                        : ($student?->student_id !== null ? (string) $student->student_id : 'N/A'),
                    'name' => $displayName,
                    'grades' => [
                        'prelim' => $enrollment->prelim_grade,
                        'midterm' => $enrollment->midterm_grade,
                        'final' => $enrollment->finals_grade,
                        'average' => $enrollment->total_average,
                    ],
                ];
            })
            ->values()
            ->all();

        // Prepare Attendance Data
        $attendance = $this->buildAttendancePayload($class);

        // Prepare Schedule Data
        $schedule = $class->schedules->map(fn ($schedule): array => [
            'id' => $schedule->id,
            'day_of_week' => $schedule->day_of_week,
            'start_time' => $schedule->start_time?->format('H:i'),
            'end_time' => $schedule->end_time?->format('H:i'),
            'room' => $schedule->room?->name ?? 'TBA',
        ]);

        return response()->json([
            'students' => $students,
            'attendance' => $attendance,
            'schedule' => $schedule,
            'auto_average' => (bool) config('class_grading.auto_calculate_average', true),
        ]);
    }

    public function storePost(StoreClassPostRequest $request, Classes $class): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isFaculty()) {
            abort(403);
        }

        /** @var Faculty|null $faculty */
        $faculty = Faculty::where('email', $user->email)->first();

        if (! $faculty || $class->faculty_id !== $faculty->id) {
            abort(403);
        }

        try {
            $data = $request->validated();

            $linkAttachments = collect($data['attachments'] ?? [])
                ->map(fn (array $attachment): array => [
                    'name' => $attachment['name'],
                    'url' => $attachment['url'],
                    'kind' => 'link',
                ]);

            /** @var FilesystemAdapter $storageDisk */
            $storageDisk = Storage::disk();

            $fileAttachments = [];

            foreach ($request->file('files', []) as $file) {
                $path = $file->store('class-post-attachments');
                $fileAttachments[] = [
                    'name' => $file->getClientOriginalName() ?: $file->hashName(),
                    'url' => $storageDisk->url($path),
                    'kind' => 'file',
                ];
            }

            $status = $data['status'] ?? 'backlog';
            $priority = $data['priority'] ?? 'medium';
            $progressPercent = (int) ($data['progress_percent'] ?? 0);
            $assignedFacultyId = array_key_exists('assigned_faculty_id', $data)
                ? $data['assigned_faculty_id']
                : $faculty->id;

            if ($status === 'done') {
                $progressPercent = 100;
            }

            ClassPost::create([
                'class_id' => $class->id,
                'title' => $data['title'],
                'content' => $data['content'] ?? null,
                'type' => $data['type'],
                'status' => $status,
                'priority' => $priority,
                'start_date' => $data['start_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'progress_percent' => $progressPercent,
                'total_points' => $data['total_points'] ?? null,
                'assigned_faculty_id' => $assignedFacultyId,
                'attachments' => array_values($linkAttachments->merge($fileAttachments)->all()),
            ]);

            return redirect()
                ->back()
                ->with('flash', [
                    'success' => 'Post shared with the class.',
                ]);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to create class post', [
                'class_id' => $class->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'Unable to create post. Please try again later.']);
        }
    }

    public function updateGrades(Request $request, Classes $class): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        $validated = $request->validate([
            'grades' => ['required', 'array'],
            'grades.*.enrollment_id' => ['required', 'exists:class_enrollments,id'],
            'grades.*.prelim' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.midterm' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.final' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'grades.*.average' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        foreach ($validated['grades'] as $gradeData) {
            $enrollment = ClassEnrollment::find($gradeData['enrollment_id']);
            if ($enrollment && $enrollment->class_id === $class->id) {
                $enrollment->update([
                    'prelim_grade' => $gradeData['prelim'],
                    'midterm_grade' => $gradeData['midterm'],
                    'finals_grade' => $gradeData['final'],
                    'total_average' => $gradeData['average'],
                ]);
            }
        }

        return redirect()->back()->with('flash', [
            'success' => 'Grades updated successfully.',
        ]);
    }

    public function submitGrades(Request $request, Classes $class): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        $validated = $request->validate([
            'term' => ['required', 'in:prelim,midterm,finals'],
        ]);

        $term = $validated['term'];

        // Logic to mark grades as submitted could go here.
        // For now, we'll just redirect with success message as placeholder.
        // You might want to update a column like is_prelim_submitted, etc.

        return redirect()->back()->with('flash', [
            'success' => ucfirst((string) $term).' grades submitted successfully.',
        ]);
    }

    public function updatePost(StoreClassPostRequest $request, Classes $class, ClassPost $post): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        if ($post->class_id !== $class->id) {
            abort(404);
        }

        try {
            $data = $request->validated();

            $incomingLinks = collect($data['attachments'] ?? [])
                ->map(fn (array $attachment): array => [
                    'name' => $attachment['name'],
                    'url' => $attachment['url'],
                    'kind' => $attachment['kind'] ?? 'link',
                ]);

            /** @var FilesystemAdapter $storageDisk */
            $storageDisk = Storage::disk();

            $fileAttachments = [];
            foreach ($request->file('files', []) as $file) {
                $path = $file->store('class-post-attachments');
                $fileAttachments[] = [
                    'name' => $file->getClientOriginalName() ?: $file->hashName(),
                    'url' => $storageDisk->url($path),
                    'kind' => 'file',
                ];
            }

            // Clean up removed file attachments from storage
            $incomingUrls = $incomingLinks
                ->filter(fn (array $attachment): bool => $attachment['kind'] === 'file')
                ->pluck('url')
                ->all();

            foreach ($post->attachments ?? [] as $attachment) {
                if (($attachment['kind'] ?? '') === 'file' && isset($attachment['url']) && ! in_array($attachment['url'], $incomingUrls, true)) {
                    $this->deleteAttachmentFromStorage($attachment['url']);
                }
            }

            $status = $data['status'] ?? $post->status ?? 'backlog';
            $priority = $data['priority'] ?? $post->priority ?? 'medium';
            $progressPercent = array_key_exists('progress_percent', $data)
                ? (int) $data['progress_percent']
                : (int) ($post->progress_percent ?? 0);
            $assignedFacultyId = array_key_exists('assigned_faculty_id', $data)
                ? $data['assigned_faculty_id']
                : $post->assigned_faculty_id;

            if ($status === 'done') {
                $progressPercent = 100;
            }

            $post->update([
                'title' => $data['title'],
                'content' => $data['content'] ?? null,
                'type' => $data['type'],
                'status' => $status,
                'priority' => $priority,
                'start_date' => $data['start_date'] ?? $post->start_date,
                'due_date' => $data['due_date'] ?? $post->due_date,
                'progress_percent' => $progressPercent,
                'total_points' => $data['total_points'] ?? null,
                'assigned_faculty_id' => $assignedFacultyId,
                'attachments' => array_values($incomingLinks->merge($fileAttachments)->all()),
            ]);

            return redirect()
                ->back()
                ->with('flash', [
                    'success' => 'Post updated.',
                ]);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to update class post', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'Unable to update post. Please try again later.']);
        }
    }

    public function destroyPost(Classes $class, ClassPost $post): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        if ($post->class_id !== $class->id) {
            abort(404);
        }

        try {
            foreach ($post->attachments ?? [] as $attachment) {
                if (($attachment['kind'] ?? '') === 'file' && isset($attachment['url'])) {
                    $this->deleteAttachmentFromStorage($attachment['url']);
                }
            }

            $post->delete();

            return redirect()
                ->back()
                ->with('flash', [
                    'success' => 'Post deleted.',
                ]);
        } catch (Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to delete class post', [
                'post_id' => $post->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'Unable to delete post.']);
        }
    }

    public function storeAttendanceSession(StoreAttendanceSessionRequest $request, Classes $class): RedirectResponse
    {
        $faculty = $this->assertFacultyOwnsClass($class);

        $data = $request->validated();

        $scheduleModel = $class->schedules()->whereKey($data['schedule_id'])->first();

        if (! $scheduleModel) {
            throw ValidationException::withMessages([
                'schedule_id' => 'The selected schedule is not part of this class.',
            ]);
        }

        $sessionDate = Carbon::parse($data['session_date']);
        $expectedDay = ucfirst(mb_strtolower((string) $scheduleModel->day_of_week ?? ''));
        $selectedDay = $sessionDate->format('l');

        if ($expectedDay !== $selectedDay) {
            throw ValidationException::withMessages([
                'session_date' => sprintf('Selected date must fall on %s to match the schedule.', $expectedDay),
            ]);
        }

        if ($class->start_date && $sessionDate->lt($class->start_date)) {
            throw ValidationException::withMessages([
                'session_date' => 'Date must be on or after the class start date.',
            ]);
        }

        $session = $class->attendanceSessions()->create([
            'schedule_id' => $scheduleModel->id,
            'session_date' => $sessionDate->toDateString(),
            'starts_at' => $scheduleModel->start_time,
            'ends_at' => $scheduleModel->end_time,
            'topic' => $data['topic'] ?? null,
            'notes' => $data['notes'] ?? null,
            'taken_by' => $faculty->id,
            'is_no_meeting' => (bool) ($data['is_no_meeting'] ?? false),
            'no_meeting_reason' => $data['no_meeting_reason'] ?? null,
            'summary' => $this->emptyStatusSummary(),
        ]);

        $defaultStatus = AttendanceStatus::tryFrom($data['default_status'] ?? '') ?? AttendanceStatus::Present;
        $shouldPrefill = array_key_exists('mark_all', $data) ? (bool) $data['mark_all'] : true;

        if ($session->is_no_meeting) {
            return redirect()
                ->back()
                ->with('flash', [
                    'success' => 'Marked as no meeting for this schedule.',
                ]);
        }

        $records = $class->class_enrollments()
            ->with('student')
            ->get()
            ->map(function (ClassEnrollment $enrollment) use ($class, $faculty, $session, $defaultStatus, $shouldPrefill): array {
                $initialStatus = $shouldPrefill ? $defaultStatus : AttendanceStatus::Absent;

                return [
                    'class_attendance_session_id' => $session->id,
                    'class_enrollment_id' => $enrollment->id,
                    'class_id' => $class->id,
                    'student_id' => $enrollment->student?->id,
                    'status' => $initialStatus->value,
                    'marked_by' => $faculty->id,
                    'marked_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })
            ->all();

        if ($records !== []) {
            ClassAttendanceRecord::insert($records);
        }

        $this->updateSessionSummary($session->fresh(['records']));

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Attendance session created.',
            ]);
    }

    public function updateAttendanceSession(UpdateAttendanceSessionRequest $request, Classes $class, ClassAttendanceSession $session): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);
        $this->assertSessionBelongsToClass($class, $session);

        $data = $request->validated();

        $session->fill([
            'topic' => $data['topic'] ?? $session->topic,
            'notes' => $data['notes'] ?? $session->notes,
            'starts_at' => $data['starts_at'] ?? $session->starts_at,
            'ends_at' => $data['ends_at'] ?? $session->ends_at,
        ]);

        if (array_key_exists('lock_session', $data)) {
            $session->is_locked = (bool) $data['lock_session'];
            $session->locked_at = $session->is_locked ? now() : null;
        }

        $session->save();

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Attendance session updated.',
            ]);
    }

    public function updateAttendanceRecords(UpdateAttendanceRecordsRequest $request, Classes $class, ClassAttendanceSession $session): RedirectResponse
    {
        $faculty = $this->assertFacultyOwnsClass($class);
        $this->assertSessionBelongsToClass($class, $session);

        if ($session->is_locked) {
            return redirect()
                ->back()
                ->with('flash', [
                    'error' => 'Attendance session is locked.',
                ]);
        }

        $payload = collect($request->validated()['records'])
            ->keyBy('class_enrollment_id');

        $records = $session->records()
            ->whereIn('class_enrollment_id', $payload->keys())
            ->get();

        foreach ($records as $record) {
            $attributes = $payload->get($record->class_enrollment_id);

            $record->fill([
                'status' => $attributes['status'],
                'remarks' => $attributes['remarks'] ?? $record->remarks,
                'marked_at' => now(),
                'marked_by' => $faculty->id,
            ])->save();
        }

        $this->updateSessionSummary($session->fresh(['records']));

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Attendance updated.',
            ]);
    }

    public function destroyAttendanceSession(Classes $class, ClassAttendanceSession $session): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);
        $this->assertSessionBelongsToClass($class, $session);

        $session->records()->delete();
        $session->delete();

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Attendance session removed.',
            ]);
    }

    public function updateSettings(Request $request, Classes $class): RedirectResponse
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isFaculty()) {
            abort(403);
        }

        /** @var Faculty|null $faculty */
        $faculty = Faculty::where('email', $user->email)->first();

        if (! $faculty || $class->faculty_id !== $faculty->id) {
            abort(403);
        }

        $data = $request->validate([
            'accent_color' => ['required', 'string', 'max:50'],
            'background_color' => ['required', 'string', 'max:50'],
            'banner_image' => ['nullable', 'string', 'max:500'],
            'enable_announcements' => ['required', 'boolean'],
            'enable_grade_visibility' => ['required', 'boolean'],
            'enable_attendance_tracking' => ['required', 'boolean'],
            'allow_late_submissions' => ['required', 'boolean'],
            'enable_discussion_board' => ['required', 'boolean'],
            'start_date' => ['nullable', 'date'],
        ]);

        $class->settings = array_merge(
            $class->settings ?? Classes::getDefaultSettings(),
            $data,
        );

        if ($request->filled('start_date')) {
            $class->start_date = Carbon::parse($request->input('start_date'))->toDateString();
        }

        $class->save();

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Class settings updated.',
                'class_settings' => $class->settings,
            ]);
    }

    public function updateSchedules(UpdateClassSchedulesRequest $request, Classes $class): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        $validated = $request->validated();

        /** @var array<int, array<string, mixed>> $incoming */
        $incoming = $validated['schedules'] ?? [];

        $existingSchedules = $class->schedules()->withTrashed()->get()->keyBy('id');
        $keptIds = [];

        foreach ($incoming as $scheduleData) {
            $scheduleId = $scheduleData['id'] ?? null;

            if ($scheduleId !== null) {
                $schedule = $existingSchedules->get((int) $scheduleId);

                if (! $schedule) {
                    throw ValidationException::withMessages([
                        'schedules' => 'One or more schedules could not be found for this class.',
                    ]);
                }

                $schedule->forceFill([
                    'day_of_week' => $scheduleData['day_of_week'],
                    'start_time' => $scheduleData['start_time'],
                    'end_time' => $scheduleData['end_time'],
                    'room_id' => (int) $scheduleData['room_id'],
                ]);

                if (method_exists($schedule, 'restore') && method_exists($schedule, 'trashed') && $schedule->trashed()) {
                    $schedule->restore();
                }

                $schedule->save();
                $keptIds[] = $schedule->id;

                continue;
            }

            $newSchedule = $class->schedules()->create([
                'day_of_week' => $scheduleData['day_of_week'],
                'start_time' => $scheduleData['start_time'],
                'end_time' => $scheduleData['end_time'],
                'room_id' => (int) $scheduleData['room_id'],
            ]);

            $keptIds[] = $newSchedule->id;
        }

        $class->schedules()
            ->whereNotIn('id', $keptIds)
            ->delete();

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Schedules updated.',
            ]);
    }

    public function searchStudents(Request $request, Classes $class): \Illuminate\Http\JsonResponse
    {
        $this->assertFacultyOwnsClass($class);

        $query = $request->input('query');
        if (! $query || mb_strlen((string) $query) < 2) {
            return response()->json(['students' => []]);
        }

        // Search students by name or ID
        // Filter by student type to match class classification
        $classificationMap = [
            'college' => \App\Enums\StudentType::College,
            'shs' => \App\Enums\StudentType::SeniorHighSchool, // Assuming 'shs' maps to SeniorHighSchool
        ];

        $targetType = $classificationMap[$class->classification] ?? null;

        $students = \App\Models\Student::query()
            ->where(function ($q) use ($query): void {
                $q->where('first_name', 'like', "%{$query}%")
                    ->orWhere('last_name', 'like', "%{$query}%")
                    ->orWhere('lrn', 'like', "%{$query}%")
                    ->orWhere('student_id', 'like', "%{$query}%")
                    ->orWhereRaw("CONCAT(first_name, ' ', last_name) LIKE ?", ["%{$query}%"]);
            })
            ->when($targetType, function ($q) use ($targetType): void {
                $q->where('student_type', $targetType->value);
            })
            ->limit(10)
            ->get();

        // Get current academic period settings
        $settings = app(\App\Services\GeneralSettingsService::class);
        $schoolYear = $settings->getCurrentSchoolYearString();
        $semester = $settings->getCurrentSemester();

        $results = $students->map(function ($student) use ($class, $schoolYear, $semester): array {
            // Check if already in this class
            $inThisClass = $class->class_enrollments()
                ->where('student_id', $student->id)
                ->exists();

            // Check if in another section of the same subject
            $otherSection = ClassEnrollment::query()
                ->where('student_id', $student->id)
                ->where('class_id', '!=', $class->id)
                ->whereHas('class', function ($q) use ($class, $schoolYear, $semester): void {
                    $q->where('subject_code', $class->subject_code)
                        ->where('school_year', $schoolYear)
                        ->where('semester', $semester);
                })
                ->with('class')
                ->first();

            // Check if has subject enrollment
            $hasSubjectEnrollment = \App\Models\SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->where(function ($q) use ($class): void {
                    $q->whereHas('subject', function ($sq) use ($class): void {
                        $sq->where('code', $class->subject_code);
                    })
                        ->orWhere('external_subject_code', $class->subject_code);
                })
                ->exists();

            // Get current enrolled subjects
            $currentSubjects = \App\Models\SubjectEnrollment::query()
                ->where('student_id', $student->id)
                ->where('school_year', $schoolYear)
                ->where('semester', $semester)
                ->with('subject')
                ->get()
                ->map(fn ($se): array => [
                    'code' => $se->subject ? $se->subject->code : $se->external_subject_code,
                    'title' => $se->subject ? $se->subject->title : $se->external_subject_title,
                ]);

            return [
                'id' => $student->id,
                'name' => $student->full_name,
                'student_id' => $student->student_id,
                'email' => $student->email,
                'avatar' => $student->profile_url,
                'status' => [
                    'in_this_class' => $inThisClass,
                    'in_other_section' => $otherSection ? $otherSection->class->section : null,
                    'has_subject_enrollment' => $hasSubjectEnrollment,
                ],
                'current_subjects' => $currentSubjects,
            ];
        });

        return response()->json(['students' => $results]);
    }

    public function storeStudent(Request $request, Classes $class): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        $validated = $request->validate([
            'student_id' => ['required', 'exists:students,id'],
        ]);

        $student = \App\Models\Student::findOrFail($validated['student_id']);

        // Prevent duplicates in THIS class
        $exists = $class->class_enrollments()
            ->where('student_id', $student->id)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('flash', [
                'error' => 'Student is already enrolled in this class.',
            ]);
        }

        // Create enrollment
        $class->class_enrollments()->create([
            'student_id' => $student->id,
            'status' => true,
        ]);

        return redirect()->back()->with('flash', [
            'success' => "Student {$student->full_name} added to the class successfully.",
        ]);
    }

    /**
     * Get available SHS strands for dropdown selection.
     */
    public function getShsStrands(Classes $class): \Illuminate\Http\JsonResponse
    {
        $this->assertFacultyOwnsClass($class);

        $strands = \App\Models\ShsStrand::query()
            ->with('track')
            ->get()
            ->map(fn ($strand): array => [
                'id' => $strand->id,
                'name' => $strand->strand_name,
                'track' => $strand->track?->track_name ?? 'N/A',
            ]);

        return response()->json(['strands' => $strands]);
    }

    /**
     * Create a new SHS student and optionally enroll in the class.
     */
    public function storeSHSStudent(Request $request, Classes $class): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        // Validate this is an SHS class
        if ($class->classification !== 'shs') {
            return redirect()->back()->with('flash', [
                'error' => 'This class is not an SHS class.',
            ]);
        }

        $validated = $request->validate([
            'lrn' => ['required', 'string', 'max:20', 'unique:shs_students,student_lrn'],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'birth_date' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', 'in:male,female'],
            'contact' => ['nullable', 'string', 'max:20'],
            'strand_id' => ['required', 'exists:shs_strands,id'],
            'grade_level' => ['required', 'in:11,12'],
            'enroll_in_class' => ['boolean'],
        ]);

        DB::beginTransaction();

        try {
            // Get the strand to find its track
            $strand = \App\Models\ShsStrand::findOrFail($validated['strand_id']);

            // Create the SHS student record
            $shsStudent = \App\Models\ShsStudent::create([
                'student_lrn' => $validated['lrn'],
                'fullname' => mb_trim("{$validated['last_name']}, {$validated['first_name']} ".($validated['middle_name'] ?? '')),
                'student_contact' => $validated['contact'] ?? null,
                'strand_id' => $validated['strand_id'],
                'track_id' => $strand->track_id,
                'grade_level' => $validated['grade_level'],
                'gender' => 'Unknown', // Default value, can be updated later
                'civil_status' => 'Single', // Default value
                'nationality' => 'Filipino', // Default value
            ]);

            // Also create a record in the main students table for class enrollment compatibility
            $studentId = \App\Models\Student::generateNextId(\App\Enums\StudentType::SeniorHighSchool);

            $student = \App\Models\Student::create([
                'id' => $studentId,
                'student_id' => $studentId,
                'lrn' => $validated['lrn'],
                'student_type' => \App\Enums\StudentType::SeniorHighSchool->value,
                'first_name' => $validated['first_name'],
                'last_name' => $validated['last_name'],
                'middle_name' => $validated['middle_name'] ?? null,
                'phone' => $validated['contact'] ?? null,
                'shs_strand_id' => $validated['strand_id'],
                'shs_track_id' => $strand->track_id,
                'academic_year' => $validated['grade_level'],
                'status' => \App\Enums\StudentStatus::Enrolled->value,
                'gender' => 'Unknown',
                'civil_status' => 'Single',
                'nationality' => 'Filipino',
            ]);

            // Optionally enroll in the class
            if ($validated['enroll_in_class'] ?? true) {
                $enrollment = ClassEnrollment::withTrashed()->updateOrCreate([
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                ], [
                    'status' => true,
                ]);

                if ($enrollment->trashed()) {
                    $enrollment->restore();

                    $enrollment->forceFill([
                        'status' => true,
                    ])->save();
                }
            }

            DB::commit();

            return redirect()->back()->with('flash', [
                'success' => "SHS student {$student->full_name} created and enrolled successfully.",
            ]);
        } catch (Exception $e) {
            DB::rollBack();

            return redirect()->back()->with('flash', [
                'error' => 'Failed to create student: '.$e->getMessage(),
            ]);
        }
    }

    public function removeStudent(Classes $class, \App\Models\Student $student): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);

        $enrollment = $class->class_enrollments()
            ->where('student_id', $student->id)
            ->first();

        if ($enrollment) {
            $enrollment->delete();

            return redirect()->back()->with('flash', [
                'success' => "Student {$student->full_name} removed from the class.",
            ]);
        }

        return redirect()->back()->with('flash', [
            'error' => 'Student is not enrolled in this class.',
        ]);
    }

    public function requestMove(Request $request, Classes $class, \App\Models\Student $student): RedirectResponse
    {
        $this->assertFacultyOwnsClass($class);
        // Logic to request move (Notifications)
        // For now, we'll implementing finding the target class based on section?
        // User said: "request the faculty who handles Section B to move that student from A to B"
        // So we need to know WHICH class is "Section B".
        // I'll assume we pass a target_class_id or we find it.
        // Let's validate target_class_id from request.

        $validated = $request->validate([
            'target_class_id' => ['required', 'exists:classes,id'],
        ]);

        $targetClass = Classes::findOrFail($validated['target_class_id']);

        if ($targetClass->subject_code !== $class->subject_code) {
            return redirect()->back()->with('flash', ['error' => 'Target class must be the same subject.']);
        }

        // Send Notification to Target Faculty
        // Using Filament Notifications
        \Filament\Notifications\Notification::make()
            ->title('Student Transfer Request')
            ->body("Faculty {$class->faculty->full_name} requests to transfer student {$student->full_name} from {$class->section} to your section {$targetClass->section}.")
            ->actions([
                Action::make('accept')
                    ->button()
                    ->url(route('classes.students.move.accept', [
                        'class' => $class->id, // Source
                        'student' => $student->id,
                        'target_class' => $targetClass->id,
                    ]), shouldOpenInNewTab: false)
                    ->markAsRead(),
                Action::make('decline')
                    ->color('danger')
                    ->close(),
            ])
            ->sendToDatabase($targetClass->faculty);

        return redirect()->back()->with('flash', [
            'success' => 'Transfer request sent to '.$targetClass->faculty->full_name,
        ]);
    }

    public function acceptMove(Classes $class, \App\Models\Student $student, Classes $targetClass): RedirectResponse
    {
        // Verified Faculty Logic: Ensure the CURRENT user is the TARGET faculty
        $currentFaculty = $this->resolveFacultyOrAbort();
        if ($targetClass->faculty_id !== $currentFaculty->id) {
            abort(403, 'You are not authorized to accept this transfer.');
        }

        // Move the student
        // 1. Find source enrollment
        $sourceEnrollment = ClassEnrollment::where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->first();

        if (! $sourceEnrollment) {
            return redirect()->route('classes.show', $targetClass)->with('flash', ['error' => 'Student is no longer in the source class.']);
        }

        // 2. Update to new class
        $sourceEnrollment->update([
            'class_id' => $targetClass->id,
        ]);

        // 3. Notify Source Faculty
        \Filament\Notifications\Notification::make()
            ->title('Transfer Accepted')
            ->body("Your request to transfer {$student->full_name} to {$targetClass->section} has been accepted.")
            ->success()
            ->sendToDatabase($class->faculty);

        return redirect()->route('classes.show', $targetClass)->with('flash', [
            'success' => "Student {$student->full_name} transferred successfully.",
        ]);
    }

    public function getRelatedSections(Classes $class)
    {
        $query = Classes::query()
            ->where('id', '!=', $class->id)
            ->where('school_year', $class->school_year)
            ->where('semester', $class->semester);

        if ($class->subject_id) {
            $query->where('subject_id', $class->subject_id);
        } else {
            $query->where('subject_code', $class->subject_code);
        }

        $sections = $query->with(['Faculty', 'Schedule.Room'])
            ->get()
            ->map(function ($section): array {
                $schedule = $section->Schedule->map(function ($s): string {
                    $room = $s->Room ? $s->Room->name : 'TBA';

                    return "{$s->day_of_week} {$s->start_time}-{$s->end_time} ($room)";
                })->join(', ');

                return [
                    'id' => $section->id,
                    'section' => $section->section,
                    'faculty_name' => $section->Faculty ? $section->Faculty->full_name : 'N/A',
                    'schedule' => $schedule ?: 'TBA',
                ];
            });

        return response()->json([
            'sections' => $sections,
        ]);
    }

    /**
     * Export attendance data as Excel (CSV) or PDF.
     */
    public function exportAttendance(Request $request, Classes $class): \Symfony\Component\HttpFoundation\Response
    {
        $this->assertFacultyOwnsClass($class);

        $format = $request->query('format', 'excel');

        // Load class with attendance data
        $class->loadMissing([
            'attendanceSessions.records.student',
            'class_enrollments.student',
            'subject',
            'SubjectByCodeFallback',
            'ShsSubject',
        ]);

        $primarySubject = $class->subjects->first();
        if (! $primarySubject) {
            $primarySubject = $class->isShs()
                ? $class->ShsSubject
                : ($class->subject ?: $class->SubjectByCodeFallback);
        }

        $sessions = $class->attendanceSessions->sortBy('session_date');
        $enrollments = $class->class_enrollments;

        // Build student attendance matrix
        $studentData = [];
        foreach ($enrollments as $enrollment) {
            $student = $enrollment->student;
            $studentName = $student?->full_name ?? $student?->name ?? 'Student #'.$enrollment->student_id;
            $studentId = $student?->student_id ?? 'N/A';

            $studentData[$enrollment->id] = [
                'name' => $studentName,
                'student_id' => $studentId,
                'attendance' => [],
                'summary' => ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0],
            ];
        }

        // Fill attendance data
        foreach ($sessions as $session) {
            foreach ($session->records as $record) {
                if (isset($studentData[$record->class_enrollment_id])) {
                    $status = $record->status?->value ?? $record->status ?? 'absent';
                    $studentData[$record->class_enrollment_id]['attendance'][$session->id] = $status;
                    $studentData[$record->class_enrollment_id]['summary'][$status] =
                        ($studentData[$record->class_enrollment_id]['summary'][$status] ?? 0) + 1;
                }
            }
        }

        $subjectCode = $primarySubject?->code ?? $class->subject_code ?? 'N/A';
        $section = $class->section ?? 'N/A';
        $fileName = sprintf('attendance-%s-%s-%s', Str::slug($subjectCode), Str::slug($section), now()->format('Y-m-d'));

        if ($format === 'pdf') {
            GenerateAttendancePdfJob::dispatch($class->id, (int) Auth::id());

            $message = 'Attendance PDF export started. You will receive a notification once the file is ready.';

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 202);
            }

            return redirect()->back()->with('success', $message);
        }

        // Default: Excel/CSV format
        return $this->generateAttendanceCsv($sessions, $studentData, $fileName);
    }

    /**
     * Export student list as Excel (CSV) or PDF.
     */
    public function exportStudentList(Request $request, Classes $class)
    {
        $this->assertFacultyOwnsClass($class);

        $format = $request->query('format', 'excel');

        if ($format === 'pdf') {
            GenerateStudentListPdfJob::dispatch($class, (int) Auth::id());

            return response()->json([
                'message' => 'PDF export started',
            ], 202);
        }

        // Default: Excel/CSV format
        $students = $class->class_enrollments()
            ->with(['student.course'])
            ->where('status', true)
            ->get()
            ->sortBy(fn ($enrollment): string => $enrollment->student->last_name.' '.$enrollment->student->first_name);

        $fileName = sprintf(
            'student-list-%s-%s-%s.csv',
            Str::slug($class->subject_code ?? 'class'),
            Str::slug($class->section ?? 'section'),
            now()->format('Y-m-d')
        );

        $headers = ['Student Name', 'Student ID', 'Email', 'Course', 'Year Level', 'Status'];

        $rows = [];
        $rows[] = $headers;

        foreach ($students as $enrollment) {
            $student = $enrollment->student;
            $rows[] = [
                $student->full_name,
                $student->student_type === \App\Enums\StudentType::SeniorHighSchool
                    ? ($student->lrn ?? 'N/A')
                    : ($student->student_id ?? 'N/A'),
                $student->email,
                $student->course?->code ?? 'N/A',
                $student->year_level ?? 'N/A',
                $enrollment->status ? 'Active' : 'Dropped',
            ];
        }

        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row, escape: '\\');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'"',
        ]);
    }

    /**
     * Generate and download student list PDF immediately (no queue).
     */
    public function downloadStudentListPdf(Request $request, Classes $class): \Symfony\Component\HttpFoundation\Response
    {
        $this->assertFacultyOwnsClass($class);

        GenerateStudentListPdfJob::dispatch($class, (int) Auth::id());

        $message = 'Student list PDF export started. You will receive a notification once the file is ready.';

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
            ], 202);
        }

        return redirect()->back()->with('success', $message);
    }

    /**
     * @return array{
     *     sessions: array<int, array<string, mixed>>,
     *     summary: array<string, mixed>,
     *     calendar_events: array<int, array<string, mixed>>
     * }
     */
    private function buildAttendancePayload(Classes $class): array
    {
        $defaultSummary = $this->emptyStatusSummary();

        // 1. Fetch Actual Sessions
        $sessions = $class->attendanceSessions()
            ->with([
                'records.enrollment.student',
                'faculty',
            ])
            ->latestFirst()
            // ->limit(8) // Removed limit to ensure we see all for calendar, or handle pagination if list is too long. For now, we need all for calendar mapping?
            // Actually, for the list view we might want a limit, but for calendar we need all.
            // Let's keep the query separate if optimization is needed, but for now fetching all is safer for correct calendar mapping of "past" sessions.
            // If performance becomes an issue, we should fetch "sessions within range" for calendar vs "recent sessions" for list.
            // For this implementation, I'll fetch all to ensure the calendar works correctly.
            ->get();

        $formattedSessions = $sessions->map(function (ClassAttendanceSession $session) use ($defaultSummary): array {
            $records = $session->records
                ->map(function (ClassAttendanceRecord $record): array {
                    $student = $record->enrollment?->student;
                    $nameSegments = collect([
                        $student?->first_name,
                        $student?->middle_name,
                        $student?->last_name,
                    ])
                        ->filter()
                        ->implode(' ');

                    $displayName = $student?->full_name
                        ?? ($nameSegments !== '' ? $nameSegments : null)
                        ?? $student?->name
                        ?? 'Student';

                    return [
                        'id' => $record->id,
                        'class_enrollment_id' => $record->class_enrollment_id,
                        'student_id' => $record->student_id,
                        'status' => $record->status instanceof AttendanceStatus ? $record->status->value : (string) $record->status,
                        'remarks' => $record->remarks,
                        'student' => [
                            'id' => $student?->id,
                            'name' => $displayName,
                            'student_number' => $student?->student_id ?? 'N/A',
                            'email' => $student?->email,
                        ],
                    ];
                })
                ->values();

            $counts = $records
                ->groupBy('status')
                ->map(fn ($group): int => $group->count())
                ->all();

            return [
                'id' => $session->id,
                'session_date' => $session->session_date?->toDateString(),
                'starts_at' => $session->starts_at?->format('H:i'),
                'ends_at' => $session->ends_at?->format('H:i'),
                'schedule_id' => $session->schedule_id,
                'topic' => $session->topic,
                'notes' => $session->notes,
                'taken_by' => $session->faculty?->full_name,
                'taken_at' => format_timestamp($session->created_at),
                'is_locked' => $session->is_locked,
                'locked_at' => format_timestamp($session->locked_at),
                'is_no_meeting' => $session->is_no_meeting,
                'no_meeting_reason' => $session->no_meeting_reason,
                'status_counts' => array_merge($defaultSummary, $session->summary ?? $counts),
                'records' => $records,
            ];
        })->values();

        $overallCounts = ClassAttendanceRecord::forClass($class)
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $lastSession = $sessions->first();

        // 2. Generate Calendar Events (Expected vs Actual)
        $calendarEvents = [];

        // Determine Date Range
        // Start: Class start date OR (if null) 1 month ago (as a fallback safety)
        $startDate = $class->start_date
            ? $class->start_date->copy()
            : Carbon::now()->subMonth();

        $endDate = Carbon::now(); // Up to today

        // Get Schedule Days (lower case day names)
        $scheduleDays = $class->schedules->pluck('day_of_week')
            ->map(fn ($day) => mb_strtolower((string) $day))
            ->unique()
            ->all();

        // Map Sessions by Date for easy lookup
        $sessionsByDate = $sessions->keyBy(fn ($s) => $s->session_date->toDateString());

        // Iterator
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateString = $currentDate->toDateString();
            $dayOfWeek = mb_strtolower((string) $currentDate->format('l'));

            // Check if this date SHOULD have a class (based on schedule)
            if (in_array($dayOfWeek, $scheduleDays)) {
                // Check if a session exists
                if ($sessionsByDate->has($dateString)) {
                    /** @var ClassAttendanceSession $session */
                    $session = $sessionsByDate->get($dateString);

                    if ($session->is_no_meeting) {
                        // Orange: No Meeting
                        $calendarEvents[] = [
                            'date' => $dateString,
                            'type' => 'no-meeting',
                            'reason' => $session->no_meeting_reason ?? 'Reason not specified',
                            'session_id' => $session->id,
                        ];
                    } else {
                        // Green: Recorded
                        $counts = $session->records->groupBy('status')->map->count();
                        $present = $counts['present'] ?? 0;
                        $late = $counts['late'] ?? 0;
                        $absent = $counts['absent'] ?? 0;

                        $calendarEvents[] = [
                            'date' => $dateString,
                            'type' => 'recorded',
                            'stats' => [
                                'present' => $present,
                                'late' => $late,
                                'absent' => $absent,
                            ],
                            'session_id' => $session->id,
                        ];
                    }
                } else {
                    // Red: Missing (No session recorded for a scheduled day)
                    // Only if it's in the past (not today, unless today's class time is over? -> keep simple: include today)
                    $calendarEvents[] = [
                        'date' => $dateString,
                        'type' => 'missing',
                    ];
                }
            }

            $currentDate->addDay();
        }

        // Add "Future" events? User didn't strictly ask for it, but "birds eye view" implies checking schedule.
        // For now, let's stick to the requested: Missing (Red), Recorded (Green), No Meeting (Orange).

        return [
            'sessions' => $formattedSessions->all(), // Return all sessions to be safe, or limit if payload is too heavy
            'summary' => [
                'by_status' => array_merge($defaultSummary, $overallCounts),
                'total_sessions' => $class->attendanceSessions()->count(),
                'last_taken_at' => $lastSession?->session_date?->toDateString(),
            ],
            'calendar_events' => $calendarEvents,
        ];
    }

    private function updateSessionSummary(ClassAttendanceSession $session): void
    {
        $summary = $session->records()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->all();

        $session->forceFill([
            'summary' => array_merge($this->emptyStatusSummary(), $summary),
        ])->saveQuietly();
    }

    /**
     * @return array<string, int>
     */
    private function emptyStatusSummary(): array
    {
        return collect(AttendanceStatus::cases())
            ->mapWithKeys(static fn (AttendanceStatus $status): array => [$status->value => 0])
            ->all();
    }

    private function assertSessionBelongsToClass(Classes $class, ClassAttendanceSession $session): void
    {
        if ($session->class_id !== $class->id) {
            abort(404);
        }
    }

    private function resolveFacultyOrAbort(): Faculty
    {
        $user = Auth::user();

        if (! $user instanceof User || ! $user->isFaculty()) {
            abort(403);
        }

        // Cache faculty lookup per request to avoid duplicate queries
        $cacheKey = "faculty_resolved_{$user->email}";

        /** @var Faculty|null $faculty */
        $faculty = cache()->remember($cacheKey, 60, fn () => Faculty::where('email', $user->email)->first());

        if (! $faculty) {
            abort(403);
        }

        return $faculty;
    }

    private function assertFacultyOwnsClass(Classes $class): Faculty
    {
        $faculty = $this->resolveFacultyOrAbort();

        if ($class->faculty_id !== $faculty->id) {
            abort(403);
        }

        return $faculty;
    }

    private function deleteAttachmentFromStorage(string $url): void
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $path = Str::after($path, '/storage/');

        if ($path !== '') {
            Storage::delete($path);
        }
    }

    /**
     * Generate CSV for attendance export.
     */
    private function generateAttendanceCsv($sessions, array $studentData, string $fileName): \Symfony\Component\HttpFoundation\Response
    {
        $headers = ['Student Name', 'Student ID'];
        foreach ($sessions as $session) {
            $headers[] = $session->session_date?->format('M d') ?? 'N/A';
        }
        $headers[] = 'Present';
        $headers[] = 'Late';
        $headers[] = 'Absent';
        $headers[] = 'Excused';

        $rows = [];
        $rows[] = $headers;

        foreach ($studentData as $data) {
            $row = [$data['name'], $data['student_id']];
            foreach ($sessions as $session) {
                $status = $data['attendance'][$session->id] ?? '-';
                $row[] = mb_strtoupper(mb_substr($status, 0, 1)); // P, L, A, E or -
            }
            $row[] = $data['summary']['present'];
            $row[] = $data['summary']['late'];
            $row[] = $data['summary']['absent'];
            $row[] = $data['summary']['excused'];
            $rows[] = $row;
        }

        $output = fopen('php://temp', 'r+');
        foreach ($rows as $row) {
            fputcsv($output, $row, escape: '\\');
        }
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$fileName.'.csv"',
        ]);
    }
}
