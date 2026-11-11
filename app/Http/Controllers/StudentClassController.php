<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AttendanceStatus;
use App\Enums\ClassPostType;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassPost;
use App\Models\ClassPostSubmission;
use App\Models\Student;
use App\Services\ClassPostPayloadService;
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

        $payloads = app(ClassPostPayloadService::class);

        $classPosts = ClassPost::where('class_id', $class->id)
            ->where('status', '!=', 'draft') // Assuming students shouldn't see drafts
            ->latest()
            ->get()
            ->filter(fn (ClassPost $post): bool => $this->studentCanAccessPost($post, $enrollment))
            ->map(fn (ClassPost $post): array => $payloads->serialize($post, includeAudienceIds: false))
            ->values();

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

        $submissionsByPost = ClassPostSubmission::query()
            ->where('student_id', $student->id)
            ->whereIn('class_post_id', $classPosts->pluck('id'))
            ->get()
            ->keyBy('class_post_id');

        // Add this student's submission state without querying once per assignment.
        $classPosts = $classPosts->map(function (array $post) use ($submissionsByPost, $class): array {
            if ($post['type'] === 'assignment') {
                $submission = $submissionsByPost->get($post['id']);

                $post['my_submission'] = $submission ? [
                    'id' => $submission->id,
                    'points' => $submission->points,
                    'status' => $submission->status,
                    'submitted_at' => $submission->submitted_at?->toDateTimeString(),
                    'graded_at' => $submission->graded_at?->toDateTimeString(),
                    'attachments' => collect($submission->attachments ?? [])
                        ->map(function ($attachment) use ($class, $post): array {
                            $filename = basename((string) ($attachment['path'] ?? parse_url($attachment['url'] ?? '', PHP_URL_PATH) ?? ''));
                            if ($filename !== '') {
                                $attachment['url'] = route('classes.posts.submissions.attachments.download', [
                                    'class' => $class->id,
                                    'post' => $post['id'],
                                    'file' => $filename,
                                ]);
                            }

                            return $attachment;
                        })
                        ->values()
                        ->all(),
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

        if (! $this->studentCanAccessPost($post, $enrollment)) {
            abort(404);
        }

        if ($post->type !== ClassPostType::Assignment) {
            throw ValidationException::withMessages([
                'post' => 'This post is not an assignment.',
            ]);
        }

        $validated = $request->validate([
            'content' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => [
                'file',
                'max:51200',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,rtf,jpg,jpeg,png,gif,zip,rar',
            ],
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

        $attachments = [];
        foreach ($request->file('files', []) as $file) {
            $path = $file->store('class-post-submissions', 'private');
            $filename = basename($path);
            $attachments[] = [
                'name' => $file->getClientOriginalName() ?: $file->hashName(),
                'path' => $path,
                'url' => route('classes.posts.submissions.attachments.download', [
                    'class' => $class->id,
                    'post' => $post->id,
                    'file' => $filename,
                ]),
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

    private function studentCanAccessPost(ClassPost $post, ClassEnrollment $enrollment): bool
    {
        if ($post->status === 'draft') {
            return false;
        }

        $postType = $post->type instanceof ClassPostType ? $post->type->value : (string) $post->type;

        if ($postType !== ClassPostType::Assignment->value || $post->audience_mode !== 'specific_students') {
            return true;
        }

        $assignedEnrollmentIds = collect($post->assigned_student_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->all();

        return in_array((int) $enrollment->id, $assignedEnrollmentIds, true);
    }
}
