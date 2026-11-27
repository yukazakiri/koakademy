<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\Administrators\UpdateClassScheduleRequest;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\EnrollmentClassWaitlist;
use App\Models\Schedule;
use App\Models\User;
use App\Services\ClassRosterManagementService;
use App\Services\ClassScheduleChangeNotificationService;
use App\Services\EnrollmentWaitlistService;
use App\Services\GeneralSettingsService;
use App\Services\GradeComputationService;
use App\Services\Scheduling\ClassCrudPreflightService;
use App\Services\Scheduling\ClassScheduleWorkspaceService;
use App\Services\Scheduling\SchedulingService;
use App\Support\AdministratorPermissionChecker;
use App\Support\ScheduleDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class AdministratorClassWorkspaceController extends Controller
{
    public function show(
        Request $request,
        Classes $class,
        ClassScheduleWorkspaceService $scheduleWorkspace,
        SchedulingService $scheduling,
    ): JsonResponse {
        $this->authorizeRead($request);

        $class->loadMissing([
            'Subject:id,code,title,lecture,laboratory,units',
            'SubjectByCodeFallback:id,code,title',
            'ShsSubject:code,title,grade_year,strand_id',
            'subjects:id,code,title',
            'Faculty:id,first_name,last_name,middle_name,email',
            'Room:id,name,capacity',
            'schedules.room:id,name,capacity',
        ]);

        $primarySubject = $class->subjects->first();
        if (! $primarySubject) {
            $primarySubject = $class->isShs()
                ? $class->ShsSubject
                : ($class->Subject ?: $class->SubjectByCodeFallback);
        }

        $includeTrashed = $request->boolean('include_trashed');

        $enrollmentQuery = $class->class_enrollments()
            ->with([
                'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year,email',
                'student.Course:id,code',
            ]);

        if ($includeTrashed) {
            $enrollmentQuery->withTrashed();
        }

        $enrollments = $enrollmentQuery
            ->get()
            ->sort(fn (ClassEnrollment $a, ClassEnrollment $b): int => [
                mb_strtolower((string) ($a->student?->last_name ?? '')),
                mb_strtolower((string) ($a->student?->first_name ?? '')),
                (int) $a->id,
            ] <=> [
                mb_strtolower((string) ($b->student?->last_name ?? '')),
                mb_strtolower((string) ($b->student?->first_name ?? '')),
                (int) $b->id,
            ])
            ->values();

        $nonTrashedEnrollments = $enrollments->filter(fn (ClassEnrollment $enrollment): bool => ! $enrollment->trashed());
        $studentsCount = $nonTrashedEnrollments->count();
        $activeStudentsCount = $nonTrashedEnrollments
            ->filter(fn (ClassEnrollment $enrollment): bool => (bool) $enrollment->status)
            ->count();

        $currentSchedules = $class->schedules
            ->map(fn (Schedule $schedule): array => [
                'day_of_week' => (string) $schedule->day_of_week,
                'start_time' => $schedule->start_time?->format('H:i') ?? '',
                'end_time' => $schedule->end_time?->format('H:i') ?? '',
                'room_id' => $schedule->room_id !== null ? (int) $schedule->room_id : null,
                'delivery_mode' => $schedule->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                'component_type' => $schedule->component_type ?? 'lecture',
            ])
            ->values()
            ->all();

        $impact = $scheduleWorkspace->previewRescheduleImpact($class, $currentSchedules);
        $timetableMap = $impact['enrolled_timetable_map'] ?? [];

        $serializedEnrollments = $enrollments
            ->map(function (ClassEnrollment $enrollment) use ($timetableMap): array {
                $studentTimetable = $timetableMap[(int) $enrollment->student_id] ?? [
                    'status' => 'clear',
                    'conflicts' => [],
                ];

                return $this->serializeEnrollment(
                    $enrollment,
                    $studentTimetable['status'],
                    $studentTimetable['conflicts'],
                );
            })
            ->values()
            ->all();

        $scheduleSummary = $scheduleWorkspace->summarizeForIndex(collect([$class]))[(int) $class->id] ?? null;
        $busyOverlay = $scheduleWorkspace->weeklyBusyOverlay($class);
        $resolutions = $scheduleWorkspace->rankedResolutions($class);

        $siblingSections = $this->loadSiblingSections($class);

        return response()->json([
            'class' => [
                'id' => (int) $class->id,
                'record_title' => $class->record_title,
                'subject_code' => $primarySubject?->code ?? $class->subject_code ?? 'N/A',
                'subject_title' => $primarySubject?->title ?? 'N/A',
                'section' => $class->section ?? 'N/A',
                'school_year' => $class->school_year ?? 'N/A',
                'semester' => $class->semester ?? 'N/A',
                'classification' => $class->classification ?? 'college',
                'delivery_mode' => $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
                'delivery_summary' => $class->deliverySummary(),
                'faculty' => $class->Faculty ? [
                    'id' => (string) $class->Faculty->id,
                    'name' => $class->Faculty->full_name,
                    'email' => $class->Faculty->email,
                ] : null,
                'room' => $class->Room ? [
                    'id' => (int) $class->Room->id,
                    'name' => $class->Room->name,
                    'capacity' => $class->Room->capacity !== null ? (int) $class->Room->capacity : null,
                ] : null,
                'maximum_slots' => (int) ($class->maximum_slots ?? 0),
                'students_count' => $studentsCount,
                'active_students_count' => $activeStudentsCount,
                'settings' => array_merge(Classes::getDefaultSettings(), (array) ($class->settings ?? [])),
            ],
            'enrollments' => $serializedEnrollments,
            'schedule_summary' => $scheduleSummary,
            'busy_overlay' => $busyOverlay,
            'resolutions' => $resolutions,
            'student_impact' => $impact['student_impact'],
            'sibling_sections' => $siblingSections,
            'schedule_revision' => $scheduleSummary['schedule_revision'] ?? $scheduling->revisionFor($class->schedules),
        ]);
    }

    public function searchStudents(
        Request $request,
        Classes $class,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeRead($request);

        $query = is_string($request->query('q')) ? $request->query('q') : '';
        $limit = max(1, min(30, (int) $request->query('limit', 15)));

        return response()->json([
            'data' => $roster->searchCandidates($class, $query, $limit),
        ]);
    }

    public function enroll(
        Request $request,
        Classes $class,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required'],
            'force_overload' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'allow_transfer_from_sibling' => ['sometimes', 'boolean'],
            'allow_clash_override' => ['sometimes', 'boolean'],
            'allow_hold_override' => ['sometimes', 'boolean'],
            'sync_subject_enrollment' => ['sometimes', 'boolean'],
            'remarks' => ['nullable', 'string', 'max:500'],
            'enrollment_status' => ['sometimes', 'nullable', 'string', Rule::in(ClassEnrollment::ENROLLMENT_STATUSES)],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;

        $options = [
            'force_overload' => (bool) ($validated['force_overload'] ?? false),
            'override_reason' => $validated['override_reason'] ?? null,
            'allow_transfer_from_sibling' => (bool) ($validated['allow_transfer_from_sibling'] ?? false),
            'allow_clash_override' => (bool) ($validated['allow_clash_override'] ?? false),
            'allow_hold_override' => (bool) ($validated['allow_hold_override'] ?? false),
            'sync_subject_enrollment' => (bool) ($validated['sync_subject_enrollment'] ?? false),
            'remarks' => $validated['remarks'] ?? null,
        ];

        if (! empty($validated['enrollment_status'])) {
            $options['enrollment_status'] = $validated['enrollment_status'];
        }

        $result = $roster->enrollStudents(
            $class,
            $validated['student_ids'],
            $actor,
            $options,
        );

        $enrolledCount = count($result['enrolled']);
        $transferredCount = count($result['transferred']);
        $skippedCount = count($result['skipped']);
        $errorCount = count($result['errors']);

        $parts = [];
        if ($enrolledCount > 0) {
            $parts[] = sprintf('%d enrolled', $enrolledCount);
        }
        if ($transferredCount > 0) {
            $parts[] = sprintf('%d transferred', $transferredCount);
        }
        if ($skippedCount > 0) {
            $parts[] = sprintf('%d skipped', $skippedCount);
        }
        if ($errorCount > 0) {
            $parts[] = sprintf('%d failed', $errorCount);
        }

        $message = $parts !== []
            ? 'Roster updated: '.implode(', ', $parts).'.'
            : 'No students were added to the roster.';

        return response()->json([
            'result' => $result,
            'message' => $message,
        ]);
    }

    public function updateEnrollment(
        Request $request,
        Classes $class,
        ClassEnrollment $enrollment,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'enrollment_status' => ['sometimes', 'nullable', 'string', Rule::in(ClassEnrollment::ENROLLMENT_STATUSES)],
            'status_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            'remarks' => ['sometimes', 'nullable', 'string', 'max:500'],
            'prelim_grade' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'midterm_grade' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'finals_grade' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100'],
            'force_grade_override' => ['sometimes', 'boolean'],
            'override_reason' => ['sometimes', 'nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $updated = $roster->updateEnrollment($class, $enrollment, $validated, $actor);

        $updated->loadMissing([
            'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year,email',
            'student.Course:id,code',
        ]);

        return response()->json([
            'enrollment' => $this->serializeEnrollment($updated),
            'message' => 'Enrollment updated successfully.',
        ]);
    }

    public function dropEnrollment(
        Request $request,
        Classes $class,
        ClassEnrollment $enrollment,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
            'force_grade_override' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $updated = $roster->dropEnrollment(
            $class,
            $enrollment,
            $actor,
            $validated['reason'] ?? null,
            (bool) ($validated['force_grade_override'] ?? false),
        );

        $updated->loadMissing([
            'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year,email',
            'student.Course:id,code',
        ]);

        return response()->json([
            'enrollment' => $this->serializeEnrollment($updated),
            'message' => 'Student enrollment dropped.',
        ]);
    }

    public function restoreEnrollment(
        Request $request,
        Classes $class,
        int $enrollmentId,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $enrollment = ClassEnrollment::withTrashed()
            ->where('class_id', $class->id)
            ->findOrFail($enrollmentId);

        $validated = $request->validate([
            'force_overload' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $updated = $roster->restoreEnrollment(
            $class,
            $enrollment,
            $actor,
            (bool) ($validated['force_overload'] ?? false),
            $validated['reason'] ?? null,
        );

        $updated->loadMissing([
            'student:id,student_id,first_name,last_name,middle_name,course_id,academic_year,email',
            'student.Course:id,code',
        ]);

        return response()->json([
            'enrollment' => $this->serializeEnrollment($updated),
            'message' => 'Student enrollment restored.',
        ]);
    }

    public function bulkMove(
        Request $request,
        Classes $class,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'target_class_id' => ['required', 'integer', 'exists:classes,id'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['required', 'integer'],
            'force_overload' => ['sometimes', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $targetClass = Classes::query()->findOrFail((int) $validated['target_class_id']);
        $actor = $request->user() instanceof User ? $request->user() : null;

        $summary = $roster->bulkMoveStudents(
            $class,
            $targetClass,
            $validated['student_ids'],
            $actor,
            (bool) ($validated['force_overload'] ?? false),
            $validated['reason'] ?? null,
        );

        return response()->json([
            'result' => $summary,
            'message' => sprintf(
                'Moved %d student(s) to %s.',
                $summary['moved_count'],
                $targetClass->record_title,
            ),
        ]);
    }

    public function previewScheduleImpact(
        Request $request,
        Classes $class,
        ClassScheduleWorkspaceService $scheduleWorkspace,
    ): JsonResponse {
        $this->authorizeRead($request);

        $validated = $request->validate([
            'schedules' => ['present', 'array'],
            'schedules.*' => ['required', 'array'],
            'schedules.*.day_of_week' => ['required', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'schedules.*.start_time' => ['required', 'date_format:H:i'],
            'schedules.*.end_time' => ['required', 'date_format:H:i', 'after:schedules.*.start_time'],
            'schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'schedules.*.delivery_mode' => ['sometimes', 'nullable', 'string', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'schedules.*.component_type' => ['sometimes', 'nullable', 'string', Rule::in(['lecture', 'lab'])],
        ]);

        return response()->json(
            $scheduleWorkspace->previewRescheduleImpact($class, $validated['schedules'])
        );
    }

    public function updateScheduleWorkspace(
        UpdateClassScheduleRequest $request,
        Classes $class,
        SchedulingService $scheduling,
        ClassScheduleWorkspaceService $scheduleWorkspace,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $scheduling->replaceChecked(
            $class,
            $request->validated('schedules'),
            $request->validated('schedule_revision'),
            $request->user() instanceof User ? $request->user() : null,
            $request->validated('schedule_status', 'published'),
            $request->boolean('notify_enrolled', true),
        );

        $class->refresh();
        $class->load([
            'schedules.room:id,name,capacity',
            'Room:id,name,capacity',
            'Subject:id,code,title,lecture,laboratory,units',
        ]);

        $scheduleSummary = $scheduleWorkspace->summarizeForIndex(collect([$class]))[(int) $class->id] ?? null;

        return response()->json([
            'schedule_summary' => $scheduleSummary,
            'resolutions' => $scheduleWorkspace->rankedResolutions($class),
            'busy_overlay' => $scheduleWorkspace->weeklyBusyOverlay($class),
            'schedule_revision' => $scheduleSummary['schedule_revision'] ?? $scheduling->revision($class),
            'message' => 'Class schedule updated successfully.',
        ]);
    }

    public function parallelSections(
        Request $request,
        Classes $class,
        ClassScheduleWorkspaceService $scheduleWorkspace,
    ): JsonResponse {
        $this->authorizeRead($request);

        $rawInspect = $request->query('inspect_student_id');
        $inspectStudentId = is_numeric($rawInspect) && (int) $rawInspect > 0
            ? (int) $rawInspect
            : null;

        return response()->json(
            $scheduleWorkspace->parallelSectionsWorkspace($class, $inspectStudentId)
        );
    }

    public function promoteWaitlist(
        Request $request,
        Classes $class,
        EnrollmentWaitlistService $waitlistService,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'waitlist_id' => ['nullable', 'integer', 'exists:enrollment_class_waitlists,id', 'required_without:waitlist_ids'],
            'waitlist_ids' => ['nullable', 'array', 'min:1', 'required_without:waitlist_id'],
            'waitlist_ids.*' => ['required', 'integer', 'exists:enrollment_class_waitlists,id'],
            'force_overload' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user();
        if (! $actor instanceof User) {
            abort(403);
        }

        $ids = collect([$validated['waitlist_id'] ?? null])
            ->merge($validated['waitlist_ids'] ?? [])
            ->filter(fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $forceOverload = (bool) ($validated['force_overload'] ?? false);

        $entries = EnrollmentClassWaitlist::query()
            ->whereIn('id', $ids)
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $promoted = [];
        $errors = [];

        foreach ($entries as $entry) {
            try {
                $updated = $waitlistService->promoteWaitlist(
                    $entry,
                    $actor,
                    (int) $class->id,
                    $forceOverload,
                );

                $promoted[] = [
                    'id' => (int) $updated->id,
                    'student_id' => (int) $updated->student_id,
                    'position' => (int) $updated->position,
                    'status' => $updated->status,
                    'promoted_class_id' => $updated->promoted_class_id !== null ? (int) $updated->promoted_class_id : null,
                    'promoted_at' => $updated->promoted_at?->toIso8601String(),
                ];
            } catch (RuntimeException|Throwable $exception) {
                $errors[] = [
                    'waitlist_id' => (int) $entry->id,
                    'student_id' => (int) $entry->student_id,
                    'message' => $exception->getMessage(),
                ];
            }
        }

        if (count($ids) === 1 && $promoted === [] && $errors !== []) {
            return response()->json([
                'promoted' => [],
                'errors' => $errors,
                'promoted_count' => 0,
                'error_count' => count($errors),
                'message' => $errors[0]['message'],
            ], 422);
        }

        return response()->json([
            'promoted' => $promoted,
            'errors' => $errors,
            'promoted_count' => count($promoted),
            'error_count' => count($errors),
            'message' => count($promoted) > 0
                ? sprintf('Promoted %d waitlisted student(s) into %s.', count($promoted), $class->record_title)
                : 'No waitlisted students were promoted.',
        ]);
    }

    public function bulkClassAction(
        Request $request,
        ClassScheduleWorkspaceService $scheduleWorkspace,
        ClassRosterManagementService $roster,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'class_ids' => ['required', 'array', 'min:1'],
            'class_ids.*' => ['required', 'integer', 'exists:classes,id'],
            'action' => ['required', 'string', Rule::in([
                'assign_faculty',
                'set_capacity',
                'publish_drafts',
                'apply_pattern',
                'enroll_cohort',
            ])],
            'faculty_id' => ['nullable', 'exists:faculty,id'],
            'maximum_slots' => ['required_if:action,set_capacity', 'nullable', 'integer', 'min:0', 'max:1000'],
            'notify_enrolled' => ['sometimes', 'boolean'],
            'pattern' => ['nullable', 'string', Rule::in(['mwf', 'tth', 'custom'])],
            'days' => ['sometimes', 'array'],
            'days.*' => ['string', Rule::in(['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'delivery_mode' => ['nullable', 'string', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'schedule_status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'student_identifiers' => ['required_if:action,enroll_cohort', 'array', 'min:1'],
            'student_identifiers.*' => ['required'],
            'force_overload' => ['sometimes', 'boolean'],
            'override_reason' => ['nullable', 'string', 'max:500'],
            'allow_transfer_from_sibling' => ['sometimes', 'boolean'],
            'allow_clash_override' => ['sometimes', 'boolean'],
            'allow_hold_override' => ['sometimes', 'boolean'],
            'sync_subject_enrollment' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $action = (string) $validated['action'];

        if ($action === 'enroll_cohort') {
            $classes = Classes::query()
                ->whereIn('id', $validated['class_ids'])
                ->orderBy('id')
                ->get();

            $options = [
                'force_overload' => (bool) ($validated['force_overload'] ?? false),
                'override_reason' => $validated['override_reason'] ?? null,
                'allow_transfer_from_sibling' => (bool) ($validated['allow_transfer_from_sibling'] ?? false),
                'allow_clash_override' => (bool) ($validated['allow_clash_override'] ?? false),
                'allow_hold_override' => (bool) ($validated['allow_hold_override'] ?? false),
                'sync_subject_enrollment' => (bool) ($validated['sync_subject_enrollment'] ?? false),
            ];

            $updatedCount = 0;
            $skippedCount = 0;
            $enrolledTotal = 0;
            $transferredTotal = 0;
            $errors = [];
            $perClass = [];

            foreach ($classes as $targetClass) {
                try {
                    $enrollResult = $roster->enrollStudents(
                        $targetClass,
                        $validated['student_identifiers'],
                        $actor,
                        $options,
                    );

                    $addedHere = count($enrollResult['enrolled']) + count($enrollResult['transferred']);
                    $enrolledTotal += count($enrollResult['enrolled']);
                    $transferredTotal += count($enrollResult['transferred']);

                    if ($addedHere > 0) {
                        $updatedCount++;
                    } else {
                        $skippedCount++;
                    }

                    foreach ($enrollResult['errors'] as $err) {
                        $errors[] = [
                            'class_id' => (int) $targetClass->id,
                            'record_title' => $targetClass->record_title,
                            ...$err,
                        ];
                    }

                    $perClass[] = [
                        'class_id' => (int) $targetClass->id,
                        'record_title' => $targetClass->record_title,
                        'enrolled_count' => count($enrollResult['enrolled']),
                        'transferred_count' => count($enrollResult['transferred']),
                        'skipped_count' => count($enrollResult['skipped']),
                        'error_count' => count($enrollResult['errors']),
                    ];
                } catch (ValidationException $exception) {
                    $skippedCount++;
                    $firstMessage = collect($exception->errors())->flatten()->first() ?? $exception->getMessage();
                    $errors[] = [
                        'class_id' => (int) $targetClass->id,
                        'record_title' => $targetClass->record_title,
                        'message' => (string) $firstMessage,
                    ];
                }
            }

            return response()->json([
                'action' => 'enroll_cohort',
                'updated_count' => $updatedCount,
                'skipped_count' => $skippedCount,
                'enrolled_total' => $enrolledTotal,
                'transferred_total' => $transferredTotal,
                'warnings' => [],
                'errors' => $errors,
                'per_class' => $perClass,
                'message' => sprintf(
                    'Cohort enrollment processed across %d class(es): %d enrolled, %d transferred.',
                    $classes->count(),
                    $enrolledTotal,
                    $transferredTotal,
                ),
            ]);
        }

        try {
            $result = $scheduleWorkspace->batchApply(
                $validated['class_ids'],
                $action,
                $validated,
                $actor,
            );
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'action' => [$exception->getMessage()],
            ]);
        }

        return response()->json([
            ...$result,
            'message' => sprintf(
                'Bulk action completed: %d updated, %d skipped.',
                $result['updated_count'],
                $result['skipped_count'],
            ),
        ]);
    }

    /**
     * Draft-safe schedule preflight (see resources/js/pages/administrators/classes/crud/types.ts: PreflightRequest/PreflightResponse).
     */
    public function preflight(
        Request $request,
        ClassCrudPreflightService $preflightService,
    ): JsonResponse {
        $this->authorizePreflight($request);

        $validated = $request->validate([
            'class_id' => ['nullable', 'integer'],
            'classification' => ['required', Rule::in(['college', 'shs'])],
            'faculty_id' => ['nullable'],
            'room_id' => ['nullable', 'integer'],
            'school_year' => ['required', 'string', 'max:50'],
            'semester' => ['required', Rule::in(['1', '2', 1, 2, 'summer'])],
            'course_codes' => ['nullable', 'array'],
            'course_codes.*' => ['integer'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer'],
            'subject_code' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['nullable', 'integer'],
            'grade_level' => ['nullable', 'string', 'max:50'],
            'shs_strand_id' => ['nullable', 'integer'],
            'maximum_slots' => ['required', 'integer', 'min:0'],
            'delivery_mode' => ['nullable', 'string', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'schedules' => ['nullable', 'array'],
            'schedules.*' => ['required', 'array'],
            'schedules.*.id' => ['nullable', 'integer'],
            'schedules.*.day_of_week' => ['required', 'string', Rule::in(ScheduleDelivery::ALL_DAYS)],
            'schedules.*.start_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'schedules.*.end_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'schedules.*.room_id' => ['nullable', 'integer'],
            'schedules.*.delivery_mode' => ['nullable', 'string', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'schedules.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'schedules.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
        ]);

        return response()->json($preflightService->preflight($validated));
    }

    /**
     * Subject blueprint + parallel section matrix create/update (BatchBlueprintRequest/BatchBlueprintResponse).
     */
    public function batchBlueprint(
        Request $request,
        ClassCrudPreflightService $preflightService,
    ): JsonResponse {
        $this->authorizeCreate($request);

        $rawSections = $request->input('sections', []);
        $hasUpdates = is_array($rawSections) && collect($rawSections)->contains(
            fn (mixed $section): bool => is_array($section) && isset($section['class_id']) && is_numeric($section['class_id']) && (int) $section['class_id'] > 0
        );

        if ($hasUpdates) {
            $this->authorizeMutation($request);
        }

        $validated = $request->validate([
            'blueprint' => ['required', 'array'],
            'blueprint.classification' => ['required', Rule::in(['college', 'shs'])],
            'blueprint.course_codes' => ['nullable', 'array'],
            'blueprint.course_codes.*' => ['integer'],
            'blueprint.subject_ids' => ['nullable', 'array'],
            'blueprint.subject_ids.*' => ['integer'],
            'blueprint.subject_code' => ['nullable', 'string', 'max:255'],
            'blueprint.academic_year' => ['nullable', 'integer', 'min:1', 'max:4'],
            'blueprint.shs_track_id' => ['nullable', 'integer', 'exists:shs_tracks,id'],
            'blueprint.shs_strand_id' => ['nullable', 'integer', Rule::exists('shs_strands', 'id')->where('track_id', $request->input('blueprint.shs_track_id'))],
            'blueprint.subject_code_shs' => ['nullable', 'string', 'max:255'],
            'blueprint.grade_level' => ['nullable', 'string', 'max:50'],
            'blueprint.school_year' => ['required', 'string', 'max:50'],
            'blueprint.semester' => ['required', Rule::in(['1', '2', 1, 2, 'summer'])],
            'blueprint.start_date' => ['nullable', 'date'],
            'blueprint.delivery_mode' => ['nullable', 'string', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'blueprint.settings' => ['nullable', 'array'],
            'blueprint.settings.enable_announcements' => ['sometimes', 'boolean'],
            'blueprint.settings.enable_grade_visibility' => ['sometimes', 'boolean'],
            'blueprint.settings.enable_attendance_tracking' => ['sometimes', 'boolean'],
            'blueprint.settings.enable_online_resources' => ['sometimes', 'boolean'],
            'blueprint.settings.allow_late_submissions' => ['sometimes', 'boolean'],
            'blueprint.settings.enable_discussion_board' => ['sometimes', 'boolean'],
            'blueprint.settings.notify_students_on_schedule_changes' => ['sometimes', 'boolean'],
            'sections' => ['required', 'array', 'min:1'],
            'sections.*' => ['required', 'array'],
            'sections.*.client_key' => ['required', 'string', 'max:100', 'distinct'],
            'sections.*.class_id' => ['nullable', 'integer', 'distinct', 'exists:classes,id'],
            'sections.*.section' => ['required', 'string', 'max:20'],
            'sections.*.faculty_id' => ['nullable', 'exists:faculty,id'],
            'sections.*.maximum_slots' => ['required', 'integer', 'min:0'],
            'sections.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'sections.*.schedules' => ['nullable', 'array'],
            'sections.*.schedules.*' => ['required', 'array'],
            'sections.*.schedules.*.id' => ['nullable', 'integer'],
            'sections.*.schedules.*.day_of_week' => ['required', 'string', Rule::in(ScheduleDelivery::ALL_DAYS)],
            'sections.*.schedules.*.start_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'sections.*.schedules.*.end_time' => ['required', 'string', 'regex:/^\d{1,2}:\d{2}(:\d{2})?$/'],
            'sections.*.schedules.*.room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'sections.*.schedules.*.delivery_mode' => ['nullable', 'string', Rule::in(ScheduleDelivery::ACCEPTED_MODES)],
            'sections.*.schedules.*.component_type' => ['nullable', 'string', Rule::in(['lecture', 'lab'])],
            'sections.*.schedules.*.status' => ['nullable', 'string', Rule::in(['draft', 'published'])],
            'dry_run' => ['required', 'boolean'],
            'schedule_status' => ['required', 'string', Rule::in(['draft', 'published'])],
            'notify_enrolled' => ['sometimes', 'boolean'],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $outcome = $preflightService->batchBlueprint($validated, $actor);

        return response()->json($outcome['body'], $outcome['status']);
    }

    /**
     * Verify / unverify / finalize / unlock grades for one or many enrollments (VerifyGradesRequest/VerifyGradesResponse).
     */
    public function verifyGrades(
        Request $request,
        Classes $class,
        GradeComputationService $gradeComputation,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $validated = $request->validate([
            'enrollment_ids' => ['nullable', 'array'],
            'enrollment_ids.*' => ['required', 'integer'],
            'action' => ['required', 'string', Rule::in(['verify', 'unverify', 'finalize', 'unlock'])],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $action = (string) $validated['action'];
        $notes = isset($validated['notes']) && is_string($validated['notes']) && mb_trim($validated['notes']) !== ''
            ? mb_trim($validated['notes'])
            : null;
        $requestedIds = isset($validated['enrollment_ids']) && is_array($validated['enrollment_ids'])
            ? array_values(array_unique(array_map(intval(...), $validated['enrollment_ids'])))
            : null;

        $updated = 0;
        $updatedIds = [];
        $skipped = [];

        DB::transaction(function () use (
            $class,
            $requestedIds,
            $action,
            $actor,
            $notes,
            $gradeComputation,
            &$updated,
            &$updatedIds,
            &$skipped,
        ): void {
            $enrollments = $class->class_enrollments()
                ->when($requestedIds !== null, fn (Builder $query) => $query->whereIn('id', $requestedIds))
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->filter(fn (ClassEnrollment $enrollment): bool => ! in_array(
                    $enrollment->resolvedEnrollmentStatus(),
                    [ClassEnrollment::STATUS_DROPPED, ClassEnrollment::STATUS_WITHDRAWN],
                    true,
                ));

            foreach ($enrollments as $enrollment) {
                $hasAnyGrade = $enrollment->prelim_grade !== null
                    || $enrollment->midterm_grade !== null
                    || $enrollment->finals_grade !== null
                    || $enrollment->total_average !== null
                    || filled($enrollment->grade_symbol);

                if ($action === 'verify') {
                    if ((bool) $enrollment->is_grades_verified) {
                        $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'Grades are already verified.'];

                        continue;
                    }
                    if (! $hasAnyGrade) {
                        $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'No grades have been recorded yet.'];

                        continue;
                    }

                    $enrollment->is_grades_verified = true;
                    $enrollment->verified_by = $actor?->id;
                    $enrollment->verified_at = now();
                    $enrollment->verification_notes = $notes;
                    $enrollment->save();
                    $updated++;
                    $updatedIds[] = (int) $enrollment->id;

                    continue;
                }

                if ($action === 'unverify') {
                    if ((bool) $enrollment->is_grades_finalized) {
                        $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'Grades are finalized. Unlock before unverifying.'];

                        continue;
                    }
                    if (! (bool) $enrollment->is_grades_verified) {
                        $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'Grades are not verified.'];

                        continue;
                    }

                    $enrollment->is_grades_verified = false;
                    $enrollment->verified_by = null;
                    $enrollment->verified_at = null;
                    if ($notes !== null) {
                        $enrollment->verification_notes = $notes;
                    }
                    $enrollment->save();
                    $updated++;
                    $updatedIds[] = (int) $enrollment->id;

                    continue;
                }

                if ($action === 'finalize') {
                    if ((bool) $enrollment->is_grades_finalized) {
                        $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'Grades are already finalized.'];

                        continue;
                    }
                    if (! $hasAnyGrade) {
                        $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'No grades have been recorded yet.'];

                        continue;
                    }

                    if (
                        $enrollment->total_average === null
                        && is_numeric($enrollment->prelim_grade)
                        && is_numeric($enrollment->midterm_grade)
                        && is_numeric($enrollment->finals_grade)
                    ) {
                        $enrollment->total_average = $gradeComputation->totalAverage(
                            (float) $enrollment->prelim_grade,
                            (float) $enrollment->midterm_grade,
                            (float) $enrollment->finals_grade,
                        );
                    }

                    $enrollment->is_grades_finalized = true;
                    if (! (bool) $enrollment->is_grades_verified) {
                        $enrollment->is_grades_verified = true;
                        $enrollment->verified_by = $actor?->id;
                        $enrollment->verified_at = now();
                        if ($notes !== null) {
                            $enrollment->verification_notes = $notes;
                        }
                    }
                    $enrollment->save();
                    $updated++;
                    $updatedIds[] = (int) $enrollment->id;

                    continue;
                }

                // unlock
                if (! (bool) $enrollment->is_grades_finalized) {
                    $skipped[] = ['id' => (int) $enrollment->id, 'reason' => 'Grades are not finalized.'];

                    continue;
                }

                $enrollment->is_grades_finalized = false;
                $enrollment->save();
                $updated++;
                $updatedIds[] = (int) $enrollment->id;
            }
            if ($updated > 0 && function_exists('activity')) {
                $logger = activity('classes')
                    ->performedOn($class)
                    ->withProperties([
                        'action' => 'grades_'.$action,
                        'updated_count' => $updated,
                        'skipped_count' => count($skipped),
                        'enrollment_ids' => $updatedIds,
                        'notes' => $notes,
                    ]);

                if ($actor instanceof User) {
                    $logger->causedBy($actor);
                }

                $logger->log(sprintf('Grades %s applied to %d enrollment(s) in %s', $action, $updated, $class->record_title));
            }
        });

        $actionPastTense = match ($action) {
            'verify' => 'verified',
            'unverify' => 'unverified',
            'finalize' => 'finalized',
            default => 'unlocked',
        };

        $message = $updated > 0
            ? sprintf('%d enrollment(s) %s%s.', $updated, $actionPastTense, $skipped !== [] ? sprintf(' (%d skipped)', count($skipped)) : '')
            : sprintf('No enrollments were %s%s.', $actionPastTense, $skipped !== [] ? sprintf(' (%d skipped)', count($skipped)) : '');

        return response()->json([
            'updated' => $updated,
            'skipped' => $skipped,
            'message' => $message,
        ]);
    }

    /**
     * Send pending manual schedule-change notices to enrolled students.
     */
    public function sendScheduleNotices(
        Request $request,
        Classes $class,
        ClassScheduleChangeNotificationService $notifications,
    ): JsonResponse {
        $this->authorizeMutation($request);

        $actor = $request->user() instanceof User ? $request->user() : null;
        $result = $notifications->notifyPendingForClass($class, $actor);
        $count = (int) $result['count'];
        $recipients = (int) $result['recipients'];
        $failed = (int) $result['failed'];

        return response()->json([
            'count' => $count,
            'recipients' => $recipients,
            'failed' => $failed,
            'message' => $count > 0
                ? sprintf('Queued %d notice(s) across %d delivery channel(s).%s', $count - $failed, $recipients, $failed > 0 ? sprintf(' %d could not be sent and remain pending.', $failed) : '')
                : 'No pending schedule notices to send.',
        ]);
    }

    /**
     * Inline partial settings update that never touches the banner image.
     */
    public function quickSettings(Request $request, Classes $class): JsonResponse
    {
        $this->authorizeMutation($request);

        if ($class->trashed()) {
            return response()->json([
                'message' => 'Cannot update settings for an archived class.',
            ], 422);
        }

        $validated = $request->validate([
            'enable_announcements' => ['sometimes', 'boolean'],
            'enable_grade_visibility' => ['sometimes', 'boolean'],
            'enable_attendance_tracking' => ['sometimes', 'boolean'],
            'enable_online_resources' => ['sometimes', 'boolean'],
            'allow_late_submissions' => ['sometimes', 'boolean'],
            'enable_discussion_board' => ['sometimes', 'boolean'],
            'notify_students_on_schedule_changes' => ['sometimes', 'boolean'],
        ]);

        $merged = DB::transaction(function () use ($class, $validated): array {
            $locked = Classes::query()->lockForUpdate()->findOrFail($class->getKey());
            $merged = array_merge(Classes::getDefaultSettings(), (array) ($locked->settings ?? []));
            foreach (ClassCrudPreflightService::POLICY_SETTING_KEYS as $key) {
                if (array_key_exists($key, $validated)) {
                    $merged[$key] = (bool) $validated[$key];
                }
            }
            $locked->settings = $merged;
            $locked->save();

            return $merged;
        });

        $policySettings = [];
        foreach (ClassCrudPreflightService::POLICY_SETTING_KEYS as $key) {
            $policySettings[$key] = (bool) ($merged[$key] ?? false);
        }

        return response()->json([
            'settings' => $policySettings,
            'message' => 'Class settings updated.',
        ]);
    }

    private function authorizePreflight(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            AdministratorPermissionChecker::userHasAnyPermission(
                $user instanceof User ? $user : null,
                ['ViewAny:Classes', 'Create:Classes', 'Update:Classes'],
                [UserRole::Admin],
            ),
            403,
        );
    }

    private function authorizeCreate(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            AdministratorPermissionChecker::userHasAnyPermission(
                $user instanceof User ? $user : null,
                'Create:Classes',
                [UserRole::Admin],
            ),
            403,
        );
    }

    private function authorizeRead(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            AdministratorPermissionChecker::userHasAnyPermission(
                $user instanceof User ? $user : null,
                ['ViewAny:Classes', 'Update:Classes'],
                [UserRole::Admin],
            ),
            403,
        );
    }

    private function authorizeMutation(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            AdministratorPermissionChecker::userHasAnyPermission(
                $user instanceof User ? $user : null,
                'Update:Classes',
                [UserRole::Admin],
            ),
            403,
        );
    }

    /**
     * @param  list<string>  $timetableConflicts
     * @return array<string, mixed>
     */
    private function serializeEnrollment(
        ClassEnrollment $enrollment,
        string $timetableStatus = 'clear',
        array $timetableConflicts = [],
    ): array {
        return [
            'id' => (int) $enrollment->id,
            'student_id' => (int) $enrollment->student_id,
            'student' => $enrollment->student ? [
                'id' => (int) $enrollment->student->id,
                'student_id' => $enrollment->student->student_id,
                'name' => $enrollment->student->full_name,
                'course' => $enrollment->student->Course?->code,
                'academic_year' => $enrollment->student->academic_year,
                'email' => $enrollment->student->email,
            ] : null,
            'status' => (bool) $enrollment->status,
            'enrollment_status' => $enrollment->resolvedEnrollmentStatus(),
            'status_reason' => $enrollment->status_reason,
            'remarks' => $enrollment->remarks,
            'prelim_grade' => $enrollment->prelim_grade,
            'midterm_grade' => $enrollment->midterm_grade,
            'finals_grade' => $enrollment->finals_grade,
            'total_average' => $enrollment->total_average,
            'is_grades_verified' => (bool) $enrollment->is_grades_verified,
            'is_grades_finalized' => (bool) $enrollment->is_grades_finalized,
            'is_grade_protected' => $enrollment->isGradeProtected(),
            'deleted_at' => $enrollment->deleted_at?->toIso8601String(),
            'timetable_status' => $timetableStatus === 'clash' ? 'clash' : 'clear',
            'timetable_conflicts' => $timetableConflicts,
        ];
    }

    /**
     * @return list<array{id: int, section: string, record_title: string, students_count: int, maximum_slots: int}>
     */
    private function loadSiblingSections(Classes $class): array
    {
        $schoolYear = (string) ($class->school_year ?? '');
        $normalizedYear = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $compactYear = str_replace(' - ', '-', $normalizedYear);
        $schoolYearVariants = array_values(array_unique(array_filter([$schoolYear, $normalizedYear, $compactYear])));

        $query = Classes::query()
            ->where('id', '!=', $class->id)
            ->where('semester', $class->semester)
            ->withCount([
                'class_enrollments as active_students_count' => fn (Builder $q) => $q
                    ->where(fn (Builder $sq) => $sq->whereNull('status')->orWhere('status', true)),
            ])
            ->orderBy('section')
            ->orderBy('id');

        if ($schoolYearVariants !== []) {
            $query->whereIn('school_year', $schoolYearVariants);
        }

        if (filled($class->subject_code)) {
            $query->where('subject_code', $class->subject_code);
        } elseif ($class->subject_id) {
            $query->where('subject_id', $class->subject_id);
        } else {
            return [];
        }

        return $query->get(['id', 'subject_code', 'section', 'maximum_slots', 'school_year', 'semester'])
            ->map(fn (Classes $sibling): array => [
                'id' => (int) $sibling->id,
                'section' => (string) ($sibling->section ?? 'N/A'),
                'record_title' => $sibling->record_title,
                'students_count' => (int) ($sibling->active_students_count ?? 0),
                'maximum_slots' => (int) ($sibling->maximum_slots ?? 0),
            ])
            ->values()
            ->all();
    }
}
