<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\ClassPostSubmission;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class StudentClassController extends Controller
{
    public function show(Classes $class): Response
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        // Find the student record associated with the user
        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->firstOrFail();

        // Check if student is enrolled in this class
        $enrollment = ClassEnrollment::where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        $class->loadMissing([
            'subject',
            'SubjectByCodeFallback',
            'ShsSubject',
            'Room',
            'schedules.room',
            'Faculty',
            'class_enrollments.student', // For people tab (classmates)
        ]);

        $primarySubject = $class->subjects->first();
        if (! $primarySubject) {
            $primarySubject = $class->isShs()
                ? $class->ShsSubject
                : ($class->subject ?: $class->SubjectByCodeFallback);
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
                    'notes' => $schedule->notes ?? null,
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
            'room' => $class->Room?->name ?? 'TBA',
            'classification' => $class->classification ?? 'college',
            'settings' => $class->settings ?? Classes::getDefaultSettings(),
        ];

        $teacher = [
            'id' => $class->Faculty?->id,
            'name' => $class->Faculty?->full_name ?? 'TBA',
            'email' => $class->Faculty?->email,
            'department' => $class->Faculty?->department,
            'photo_url' => $class->Faculty?->getFilamentAvatarUrl(),
        ];

        $classPosts = ClassPost::where('class_id', $class->id)
            ->where('status', '!=', 'draft') // Assuming students shouldn't see drafts
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
                    'type' => $post->type instanceof \App\Enums\ClassPostType ? $post->type->value : (string) $post->type,
                    'status' => $post->status,
                    'priority' => $post->priority,
                    'start_date' => $post->start_date?->toDateString(),
                    'due_date' => $post->due_date?->toDateString(),
                    'attachments' => $attachments,
                    'created_at' => format_timestamp($post->created_at),
                    'assigned_faculty_id' => $post->assigned_faculty_id,
                ];
            });

        // Student's own grades
        $grades = [
            'prelim' => $enrollment->prelim_grade,
            'midterm' => $enrollment->midterm_grade,
            'final' => $enrollment->finals_grade,
            'average' => $enrollment->total_average,
        ];

        // Student's own attendance
        // We need to fetch attendance records for this student in this class
        $attendanceRecords = \App\Models\ClassAttendanceRecord::where('class_enrollment_id', $enrollment->id)
            ->with('session')
            ->get();

        $attendanceStats = [
            'present' => $attendanceRecords->where('status', 'present')->count(),
            'late' => $attendanceRecords->where('status', 'late')->count(),
            'absent' => $attendanceRecords->where('status', 'absent')->count(),
            'excused' => $attendanceRecords->where('status', 'excused')->count(),
        ];

        $attendanceHistory = $attendanceRecords->map(fn ($record): array => [
            'id' => $record->id,
            'date' => $record->session->session_date->toDateString(),
            'status' => $record->status instanceof AttendanceStatus ? $record->status->value : (string) $record->status,
            'remarks' => $record->remarks,
            'topic' => $record->session->topic,
        ])->sortByDesc('date')->values();

        // Classmates (People tab) - minimal info
        $classmates = $class->class_enrollments
            ->map(function ($enr): array {
                $s = $enr->student;

                return [
                    'id' => $s->id,
                    'name' => $s->full_name,
                    'avatar' => $s->profile_url, // Assuming accessing url works or is null
                ];
            })
            ->sortBy('name')
            ->values();

        // Add submission status to posts
        $classPosts = $classPosts->map(function (array $post) use ($student): array {
            if ($post['type'] === 'assignment') {
                $submission = ClassPostSubmission::where('class_post_id', $post['id'])
                    ->where('student_id', $student->id)
                    ->first();

                $post['my_submission'] = $submission ? [
                    'id' => $submission->id,
                    'points' => $submission->points,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                    'graded_at' => $submission->graded_at?->toDateTimeString(),
                ] : null;
            }

            return $post;
        });

        return Inertia::render('student/classes/show', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => $user->avatar_url ?? null,
                'role' => $user->role?->getLabel() ?? 'Student',
            ],
            'classData' => $classData,
            'teacher' => $teacher,
            'posts' => $classPosts,
            'schedule' => $schedule,
            'my_grades' => $grades,
            'my_attendance' => [
                'stats' => $attendanceStats,
                'history' => $attendanceHistory,
            ],
            'classmates' => $classmates,
            'flash' => session('flash'),
        ]);
    }

    public function storeSubmission(Request $request, Classes $class, ClassPost $post): RedirectResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->firstOrFail();

        $enrollment = ClassEnrollment::where('class_id', $class->id)
            ->where('student_id', $student->id)
            ->firstOrFail();

        if ($post->class_id !== $class->id) {
            abort(404);
        }

        if ($post->type !== \App\Enums\ClassPostType::Assignment) {
            throw ValidationException::withMessages([
                'post' => 'This post is not an assignment.',
            ]);
        }

        $validated = $request->validate([
            'content' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:51200'],
        ]);

        // Check if already submitted
        $existingSubmission = ClassPostSubmission::where('class_post_id', $post->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existingSubmission) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'You have already submitted this assignment.']);
        }

        /** @var \Illuminate\Filesystem\FilesystemAdapter $storageDisk */
        $storageDisk = \Illuminate\Support\Facades\Storage::disk();

        $attachments = [];
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('class-post-submissions');
            $attachments[] = [
                'name' => $file->getClientOriginalName() ?: $file->hashName(),
                'url' => $storageDisk->url($path),
                'kind' => 'file',
            ];
        }

        ClassPostSubmission::create([
            'class_post_id' => $post->id,
            'student_id' => $student->id,
            'content' => $validated['content'] ?? null,
            'attachments' => $attachments,
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return redirect()
            ->back()
            ->with('flash', [
                'success' => 'Assignment submitted successfully.',
            ]);
    }
}
