<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Support\ScheduleDelivery;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final readonly class ClassRosterManagementService
{
    public function __construct(
        private ClassEnrollmentService $classEnrollmentService,
        private StudentScheduleService $studentScheduleService,
        private StudentHoldService $studentHoldService,
        private StudentSectionTransferService $studentSectionTransferService,
        private GeneralSettingsService $generalSettingsService,
    ) {}

    /**
     * Search candidate students for enrollment into a class and batch-compute all preflight guards.
     *
     * @return list<array<string, mixed>>
     */
    public function searchCandidates(Classes $class, string $query, int $limit = 15): array
    {
        $trimmed = mb_trim($query);
        if ($trimmed === '') {
            return [];
        }

        $limit = max(1, min(100, $limit));
        $tokens = array_slice(
            array_values(array_filter(preg_split('/\s+/u', $trimmed) ?: [], fn (string $token): bool => $token !== '')),
            0,
            4
        );

        $students = Student::query()
            ->with(['Course:id,code'])
            ->where(function (Builder $builder) use ($trimmed, $tokens): void {
                $builder->where(function (Builder $tokenQuery) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $like = '%'.mb_strtolower($token).'%';

                        $tokenQuery->where(function (Builder $fieldQuery) use ($like): void {
                            $fieldQuery->whereRaw('LOWER(first_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(middle_name, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(student_id, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(lrn, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like]);
                        });
                    }
                });

                if (ctype_digit($trimmed)) {
                    $builder->orWhere('id', (int) $trimmed);
                }
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit($limit)
            ->get();

        if ($students->isEmpty()) {
            return [];
        }

        $studentIds = $students
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        [$schoolYearVariants, $semester] = $this->resolveTermForClass($class);

        $activeCount = $this->activeEnrollmentCount((int) $class->id);
        $maximumSlots = (int) ($class->maximum_slots ?? 0);
        $isClassFull = $maximumSlots > 0 && $activeCount >= $maximumSlots;
        $wouldExceedCapacity = $maximumSlots > 0 && ($activeCount + 1) > $maximumSlots;

        $existingInThisClass = ClassEnrollment::query()
            ->where('class_id', $class->id)
            ->whereIn('student_id', $studentIds)
            ->get(['id', 'student_id', 'status', 'enrollment_status'])
            ->keyBy(fn (ClassEnrollment $enrollment): int => (int) $enrollment->student_id);

        $siblingMap = $this->batchSiblingSectionEnrollments($class, $studentIds, $schoolYearVariants, $semester);
        $holdSummaries = $this->studentHoldService->summariesForStudents($studentIds);

        $excludedClassByStudent = [];
        foreach ($siblingMap as $studentId => $sibling) {
            $excludedClassByStudent[$studentId] = [(int) $sibling['class_id']];
        }

        $conflictsByStudent = $this->batchScheduleConflicts(
            $class,
            $studentIds,
            $schoolYearVariants,
            $semester,
            $excludedClassByStudent,
        );

        return $students->map(function (Student $student) use (
            $existingInThisClass,
            $siblingMap,
            $holdSummaries,
            $conflictsByStudent,
            $isClassFull,
            $wouldExceedCapacity,
        ): array {
            $studentId = (int) $student->id;
            /** @var ClassEnrollment|null $existingHere */
            $existingHere = $existingInThisClass->get($studentId);
            $alreadyEnrolledHere = $existingHere instanceof ClassEnrollment && (bool) $existingHere->status;
            $sibling = $siblingMap[$studentId] ?? null;
            $holds = $holdSummaries[$studentId] ?? [
                'active_count' => 0,
                'blocking_count' => 0,
                'offices' => [],
            ];
            $conflicts = $conflictsByStudent[$studentId] ?? [];

            $recommendedAction = $this->determineRecommendedAction(
                alreadyEnrolledHere: $alreadyEnrolledHere,
                blockingHoldsCount: (int) ($holds['blocking_count'] ?? 0),
                scheduleConflictsCount: count($conflicts),
                hasSiblingEnrollment: $sibling !== null,
                wouldExceedCapacity: $wouldExceedCapacity,
            );

            return [
                'id' => $studentId,
                'student_id' => $student->student_id,
                'lrn' => $student->lrn,
                'first_name' => $student->first_name,
                'last_name' => $student->last_name,
                'full_name' => $student->full_name,
                'email' => $student->email,
                'course' => $student->Course?->code,
                'academic_year' => $student->academic_year,
                'already_enrolled_here' => $alreadyEnrolledHere,
                'existing_enrollment_id' => $existingHere instanceof ClassEnrollment ? (int) $existingHere->id : null,
                'existing_enrollment_status' => $existingHere?->resolvedEnrollmentStatus(),
                'sibling_section_enrollment' => $sibling,
                'holds' => $holds,
                'schedule_conflicts' => $conflicts,
                'is_class_full' => $isClassFull,
                'would_exceed_capacity' => $wouldExceedCapacity,
                'recommended_action' => $recommendedAction,
            ];
        })->values()->all();
    }

    /**
     * Enroll one or more students into a class under row-level locks with capacity, hold, clash, and sibling guards.
     *
     * @param  array<int, int|string>  $studentIdentifiers
     * @param  array<string, mixed>  $options
     * @return array{
     *     enrolled: list<array<string, mixed>>,
     *     transferred: list<array<string, mixed>>,
     *     skipped: list<array<string, mixed>>,
     *     errors: list<array<string, mixed>>
     * }
     *
     * @throws ValidationException
     */
    public function enrollStudents(
        Classes $class,
        array $studentIdentifiers,
        ?User $actor = null,
        array $options = [],
    ): array {
        $forceOverload = (bool) ($options['force_overload'] ?? false);
        $overrideReason = $this->nullableTrimmed($options['override_reason'] ?? null);
        $allowTransferFromSibling = (bool) ($options['allow_transfer_from_sibling'] ?? false);
        $allowClashOverride = (bool) ($options['allow_clash_override'] ?? false);
        $allowHoldOverride = (bool) ($options['allow_hold_override'] ?? false);
        $syncSubjectEnrollment = (bool) ($options['sync_subject_enrollment'] ?? false);
        $remarks = $this->nullableTrimmed($options['remarks'] ?? null);
        $enrollmentStatus = (string) ($options['enrollment_status'] ?? ClassEnrollment::STATUS_ENROLLED);

        if (! in_array($enrollmentStatus, ClassEnrollment::ENROLLMENT_STATUSES, true)) {
            throw ValidationException::withMessages([
                'enrollment_status' => [sprintf('Invalid enrollment status "%s".', $enrollmentStatus)],
            ]);
        }

        $statusBool = ClassEnrollment::statusBooleanFor($enrollmentStatus);
        $normalizedIdentifiers = $this->normalizeIdentifiers($studentIdentifiers);

        if ($normalizedIdentifiers === []) {
            return [
                'enrolled' => [],
                'transferred' => [],
                'skipped' => [],
                'errors' => [],
            ];
        }

        $isSingle = count($normalizedIdentifiers) === 1;
        [$resolvedStudents, $unmatchedErrors] = $this->resolveStudentsByIdentifiers($normalizedIdentifiers);

        if ($isSingle && $resolvedStudents === [] && $unmatchedErrors !== []) {
            throw ValidationException::withMessages([
                'student_identifiers' => [$unmatchedErrors[0]['message']],
            ]);
        }

        return DB::transaction(function () use (
            $class,
            $resolvedStudents,
            $unmatchedErrors,
            $actor,
            $isSingle,
            $forceOverload,
            $overrideReason,
            $allowTransferFromSibling,
            $allowClashOverride,
            $allowHoldOverride,
            $syncSubjectEnrollment,
            $remarks,
            $enrollmentStatus,
            $statusBool,
        ): array {
            $lockedClass = Classes::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();
            $lockedClass->loadMissing('schedules.room');

            $result = [
                'enrolled' => [],
                'transferred' => [],
                'skipped' => [],
                'errors' => $unmatchedErrors,
            ];

            if ($resolvedStudents === []) {
                return $result;
            }

            $studentIds = array_values(array_unique(array_map(
                fn (Student $student): int => (int) $student->id,
                $resolvedStudents,
            )));

            // Lock candidate student rows in ascending ID order to satisfy enrollOnceWhileLocked() and avoid deadlocks.
            $lockedStudentsById = Student::query()
                ->whereIn('id', $studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Student $student): int => (int) $student->id);

            [$schoolYearVariants, $semester] = $this->resolveTermForClass($lockedClass);

            $existingInThisClass = ClassEnrollment::query()
                ->where('class_id', $lockedClass->id)
                ->whereIn('student_id', $studentIds)
                ->get()
                ->keyBy(fn (ClassEnrollment $enrollment): int => (int) $enrollment->student_id);

            $siblingMap = $this->batchSiblingSectionEnrollments($lockedClass, $studentIds, $schoolYearVariants, $semester);
            $holdSummaries = $this->studentHoldService->summariesForStudents($studentIds);

            $excludedClassByStudent = [];
            if ($allowTransferFromSibling) {
                foreach ($siblingMap as $studentId => $sibling) {
                    $excludedClassByStudent[$studentId] = [(int) $sibling['class_id']];
                }
            }

            $conflictsByStudent = $this->batchScheduleConflicts(
                $lockedClass,
                $studentIds,
                $schoolYearVariants,
                $semester,
                $excludedClassByStudent,
            );

            $activeCount = $this->activeEnrollmentCount((int) $lockedClass->id);
            $maximumSlots = (int) ($lockedClass->maximum_slots ?? 0);

            foreach ($resolvedStudents as $candidate) {
                $studentId = (int) $candidate->id;
                /** @var Student|null $student */
                $student = $lockedStudentsById->get($studentId);
                if (! $student instanceof Student) {
                    continue;
                }

                /** @var ClassEnrollment|null $existingHere */
                $existingHere = $existingInThisClass->get($studentId);
                if ($existingHere instanceof ClassEnrollment && (bool) $existingHere->status) {
                    $result['skipped'][] = [
                        'student_id' => $studentId,
                        'student_number' => $student->student_id,
                        'student_name' => $student->full_name,
                        'enrollment_id' => (int) $existingHere->id,
                        'code' => 'already_enrolled',
                        'message' => 'Student is already actively enrolled in this class.',
                    ];

                    continue;
                }

                $appliedOverrides = [];

                // 1. Hold guard
                $holds = $holdSummaries[$studentId] ?? ['active_count' => 0, 'blocking_count' => 0, 'offices' => []];
                if (($holds['blocking_count'] ?? 0) > 0) {
                    if (! $allowHoldOverride || $overrideReason === null) {
                        $message = sprintf(
                            'Student %s has %d active blocking hold(s) (%s). Provide allow_hold_override and an override_reason to proceed.',
                            $student->full_name,
                            (int) $holds['blocking_count'],
                            implode(', ', $holds['offices']),
                        );

                        if ($isSingle) {
                            throw ValidationException::withMessages(['allow_hold_override' => [$message]]);
                        }

                        $result['errors'][] = [
                            'student_id' => $studentId,
                            'student_number' => $student->student_id,
                            'student_name' => $student->full_name,
                            'code' => 'blocked_hold',
                            'message' => $message,
                            'holds' => $holds,
                        ];

                        continue;
                    }

                    if ($actor instanceof User) {
                        $this->studentHoldService->assertNoBlockingEnrollmentHolds(
                            $student,
                            $actor,
                            true,
                            $overrideReason,
                        );
                    }

                    $appliedOverrides[] = 'hold';
                }

                // 2. Schedule clash guard
                $conflicts = $conflictsByStudent[$studentId] ?? [];
                if ($conflicts !== []) {
                    if (! $allowClashOverride || $overrideReason === null) {
                        $message = sprintf(
                            'Student %s has %d schedule conflict(s) with this class. Provide allow_clash_override and an override_reason to proceed.',
                            $student->full_name,
                            count($conflicts),
                        );

                        if ($isSingle) {
                            throw ValidationException::withMessages(['allow_clash_override' => [$message]]);
                        }

                        $result['errors'][] = [
                            'student_id' => $studentId,
                            'student_number' => $student->student_id,
                            'student_name' => $student->full_name,
                            'code' => 'schedule_conflict',
                            'message' => $message,
                            'conflicts' => $conflicts,
                        ];

                        continue;
                    }

                    $appliedOverrides[] = 'schedule_clash';
                }

                // 3. Capacity guard (for active enrollments)
                $wouldExceedCapacity = $statusBool && $maximumSlots > 0 && ($activeCount + 1) > $maximumSlots;
                if ($wouldExceedCapacity) {
                    if (! $forceOverload || $overrideReason === null) {
                        $message = sprintf(
                            'Class %s has reached its maximum capacity (%d/%d). Provide force_overload and an override_reason to proceed.',
                            $lockedClass->record_title,
                            $activeCount,
                            $maximumSlots,
                        );

                        if ($isSingle) {
                            throw ValidationException::withMessages(['force_overload' => [$message]]);
                        }

                        $result['errors'][] = [
                            'student_id' => $studentId,
                            'student_number' => $student->student_id,
                            'student_name' => $student->full_name,
                            'code' => 'capacity_exceeded',
                            'message' => $message,
                        ];

                        continue;
                    }

                    $appliedOverrides[] = 'capacity_overload';
                }

                // 4. Sibling section guard / transfer
                $sibling = $siblingMap[$studentId] ?? null;
                if ($sibling !== null) {
                    if (! $allowTransferFromSibling) {
                        $message = sprintf(
                            'Student %s is already enrolled in sibling section %s for this subject. Enable allow_transfer_from_sibling to transfer them.',
                            $student->full_name,
                            (string) $sibling['section'],
                        );

                        if ($isSingle) {
                            throw ValidationException::withMessages(['allow_transfer_from_sibling' => [$message]]);
                        }

                        $result['errors'][] = [
                            'student_id' => $studentId,
                            'student_number' => $student->student_id,
                            'student_name' => $student->full_name,
                            'code' => 'sibling_section_exists',
                            'message' => $message,
                            'sibling_section_enrollment' => $sibling,
                        ];

                        continue;
                    }

                    $siblingEnrollment = ClassEnrollment::query()->find((int) $sibling['enrollment_id']);
                    if (! $siblingEnrollment instanceof ClassEnrollment) {
                        $result['errors'][] = [
                            'student_id' => $studentId,
                            'student_number' => $student->student_id,
                            'student_name' => $student->full_name,
                            'code' => 'sibling_enrollment_missing',
                            'message' => 'Sibling section enrollment could not be found for transfer.',
                        ];

                        continue;
                    }

                    try {
                        $transferResult = $this->studentSectionTransferService->transferStudent(
                            $siblingEnrollment,
                            (int) $lockedClass->id,
                            $forceOverload,
                        );
                    } catch (Throwable $exception) {
                        if ($isSingle) {
                            throw ValidationException::withMessages(['student_identifiers' => [$exception->getMessage()]]);
                        }

                        $result['errors'][] = [
                            'student_id' => $studentId,
                            'student_number' => $student->student_id,
                            'student_name' => $student->full_name,
                            'code' => 'transfer_failed',
                            'message' => $exception->getMessage(),
                        ];

                        continue;
                    }

                    $transferredEnrollment = $siblingEnrollment->fresh();
                    if ($transferredEnrollment instanceof ClassEnrollment) {
                        $updates = [
                            'status' => $statusBool,
                            'enrollment_status' => $enrollmentStatus,
                        ];
                        if ($overrideReason !== null) {
                            $updates['status_reason'] = $overrideReason;
                        }
                        if ($remarks !== null) {
                            $updates['remarks'] = $remarks;
                        }
                        $transferredEnrollment->forceFill($updates)->save();

                        if ($appliedOverrides !== []) {
                            $this->logRosterActivity(
                                enrollment: $transferredEnrollment,
                                actor: $actor,
                                description: 'Student transferred into class with administrative overrides',
                                properties: [
                                    'action' => 'enroll_transfer',
                                    'class_id' => (int) $lockedClass->id,
                                    'from_class_id' => (int) $sibling['class_id'],
                                    'student_id' => $studentId,
                                    'overrides' => $appliedOverrides,
                                    'override_reason' => $overrideReason,
                                ],
                            );
                        }
                    }

                    if ($statusBool) {
                        $activeCount++;
                    }

                    $result['transferred'][] = [
                        'student_id' => $studentId,
                        'student_number' => $student->student_id,
                        'student_name' => $student->full_name,
                        'enrollment_id' => (int) $siblingEnrollment->id,
                        'from_class_id' => (int) $sibling['class_id'],
                        'from_section' => (string) $sibling['section'],
                        'to_class_id' => (int) $lockedClass->id,
                        'to_section' => (string) ($lockedClass->section ?? ''),
                        'overrides' => $appliedOverrides,
                        'transfer_result' => $transferResult,
                    ];

                    continue;
                }

                // 5. Standard enrollment (re-enrolls dropped record or creates new one)
                $enrollment = $this->classEnrollmentService->enrollOnceWhileLocked(
                    $studentId,
                    (int) $lockedClass->id,
                    [
                        'status' => $statusBool,
                        'enrollment_status' => $enrollmentStatus,
                        'status_reason' => $overrideReason,
                        'remarks' => $remarks,
                    ],
                );

                // Ensure metadata is synced even when enrollOnceWhileLocked() reused an existing row.
                $syncAttributes = [
                    'status' => $statusBool,
                    'enrollment_status' => $enrollmentStatus,
                ];
                if ($overrideReason !== null) {
                    $syncAttributes['status_reason'] = $overrideReason;
                }
                if ($remarks !== null) {
                    $syncAttributes['remarks'] = $remarks;
                }

                $enrollment->fill($syncAttributes);
                if ($enrollment->isDirty()) {
                    $enrollment->save();
                }

                if ($syncSubjectEnrollment) {
                    $this->syncExistingSubjectEnrollment($studentId, $lockedClass, $schoolYearVariants, $semester);
                }

                if ($statusBool) {
                    $activeCount++;
                }

                if ($appliedOverrides !== []) {
                    $this->logRosterActivity(
                        enrollment: $enrollment,
                        actor: $actor,
                        description: 'Student enrolled in class with administrative overrides',
                        properties: [
                            'action' => 'enroll_override',
                            'class_id' => (int) $lockedClass->id,
                            'student_id' => $studentId,
                            'enrollment_status' => $enrollmentStatus,
                            'overrides' => $appliedOverrides,
                            'override_reason' => $overrideReason,
                        ],
                    );
                }

                $result['enrolled'][] = [
                    'student_id' => $studentId,
                    'student_number' => $student->student_id,
                    'student_name' => $student->full_name,
                    'enrollment_id' => (int) $enrollment->id,
                    'enrollment_status' => $enrollment->resolvedEnrollmentStatus(),
                    'overrides' => $appliedOverrides,
                ];
            }

            return $result;
        });
    }

    /**
     * Update an enrollment's status, remarks, or period grades with grade-lock protection.
     *
     * @param  array<string, mixed>  $data
     *
     * @throws ValidationException
     */
    public function updateEnrollment(
        Classes $class,
        ClassEnrollment $enrollment,
        array $data,
        ?User $actor = null,
    ): ClassEnrollment {
        return DB::transaction(function () use ($class, $enrollment, $data, $actor): ClassEnrollment {
            $locked = ClassEnrollment::query()->whereKey($enrollment->getKey())->lockForUpdate()->firstOrFail();

            return $this->updateLockedEnrollment($class, $locked, $data, $actor);
        });
    }

    /**
     * Drop a student's enrollment from a class with grade-protection guard and audit trail.
     *
     * @throws ValidationException
     */
    public function dropEnrollment(
        Classes $class,
        ClassEnrollment $enrollment,
        ?User $actor = null,
        ?string $reason = null,
        bool $forceGradeOverride = false,
    ): ClassEnrollment {
        $this->assertEnrollmentBelongsToClass($class, $enrollment);

        $cleanReason = $this->nullableTrimmed($reason);

        if ($enrollment->isGradeProtected() && (! $forceGradeOverride || $cleanReason === null)) {
            throw ValidationException::withMessages([
                'force_grade_override' => [
                    'This enrollment has recorded, finalized, or verified grades. Provide force_grade_override and a non-empty reason to drop it.',
                ],
            ]);
        }

        return DB::transaction(function () use ($class, $enrollment, $actor, $cleanReason, $forceGradeOverride): ClassEnrollment {
            $enrollment->forceFill([
                'enrollment_status' => ClassEnrollment::STATUS_DROPPED,
                'status' => false,
                'status_reason' => $cleanReason,
            ])->save();

            $this->logRosterActivity(
                enrollment: $enrollment,
                actor: $actor,
                description: 'Student enrollment dropped from class',
                properties: [
                    'action' => 'drop',
                    'class_id' => (int) $class->id,
                    'student_id' => (int) $enrollment->student_id,
                    'reason' => $cleanReason,
                    'force_grade_override' => $forceGradeOverride,
                ],
            );

            return $enrollment->fresh() ?? $enrollment;
        });
    }

    /**
     * Restore a dropped or soft-deleted enrollment back to active status with capacity guard.
     *
     * @throws ValidationException
     */
    public function restoreEnrollment(
        Classes $class,
        ClassEnrollment $enrollment,
        ?User $actor = null,
        bool $forceOverload = false,
        ?string $reason = null,
    ): ClassEnrollment {
        $this->assertEnrollmentBelongsToClass($class, $enrollment);

        $cleanReason = $this->nullableTrimmed($reason);

        return DB::transaction(function () use ($class, $enrollment, $actor, $forceOverload, $cleanReason): ClassEnrollment {
            $lockedClass = Classes::query()->whereKey($class->id)->lockForUpdate()->firstOrFail();

            $alreadyActive = ! $enrollment->trashed()
                && (bool) $enrollment->status
                && $enrollment->resolvedEnrollmentStatus() === ClassEnrollment::STATUS_ENROLLED;

            $wouldExceedCapacity = false;
            if (! $alreadyActive) {
                $activeCount = $this->activeEnrollmentCount((int) $lockedClass->id);
                $maximumSlots = (int) ($lockedClass->maximum_slots ?? 0);
                $wouldExceedCapacity = $maximumSlots > 0 && ($activeCount + 1) > $maximumSlots;

                if ($wouldExceedCapacity && (! $forceOverload || $cleanReason === null)) {
                    throw ValidationException::withMessages([
                        'force_overload' => [
                            sprintf(
                                'Class %s has reached its maximum capacity (%d/%d). Provide force_overload and a non-empty reason to restore this enrollment.',
                                $lockedClass->record_title,
                                $activeCount,
                                $maximumSlots,
                            ),
                        ],
                    ]);
                }
            }

            if ($enrollment->trashed()) {
                $enrollment->restore();
            }

            $enrollment->forceFill([
                'enrollment_status' => ClassEnrollment::STATUS_ENROLLED,
                'status' => true,
                'status_reason' => $cleanReason,
            ])->save();

            $this->logRosterActivity(
                enrollment: $enrollment,
                actor: $actor,
                description: 'Student enrollment restored to class',
                properties: [
                    'action' => 'restore',
                    'class_id' => (int) $lockedClass->id,
                    'student_id' => (int) $enrollment->student_id,
                    'force_overload' => $wouldExceedCapacity && $forceOverload,
                    'reason' => $cleanReason,
                ],
            );

            return $enrollment->fresh() ?? $enrollment;
        });
    }

    /**
     * Bulk-move students from one section to another section of the same subject.
     *
     * @param  array<int, int|string>  $studentIds
     * @return array{
     *     source_class_id: int,
     *     target_class_id: int,
     *     moved: list<array<string, mixed>>,
     *     skipped: list<array<string, mixed>>,
     *     errors: list<array<string, mixed>>,
     *     moved_count: int,
     *     skipped_count: int,
     *     error_count: int
     * }
     *
     * @throws ValidationException
     */
    public function bulkMoveStudents(
        Classes $sourceClass,
        Classes $targetClass,
        array $studentIds,
        ?User $actor = null,
        bool $forceOverload = false,
        ?string $reason = null,
    ): array {
        if ((int) $sourceClass->id === (int) $targetClass->id) {
            throw ValidationException::withMessages([
                'target_class_id' => ['Source and target classes must be different sections.'],
            ]);
        }

        if (! $this->isSameSubject($sourceClass, $targetClass)) {
            throw ValidationException::withMessages([
                'target_class_id' => ['Cannot move students between classes of different subjects.'],
            ]);
        }

        $cleanReason = $this->nullableTrimmed($reason);
        $normalizedStudentIds = array_values(array_unique(array_map(
            fn (int|string $id): int => (int) $id,
            array_filter($studentIds, fn (mixed $id): bool => is_numeric($id)),
        )));

        if ($normalizedStudentIds === []) {
            return [
                'source_class_id' => (int) $sourceClass->id,
                'target_class_id' => (int) $targetClass->id,
                'moved' => [],
                'skipped' => [],
                'errors' => [],
                'moved_count' => 0,
                'skipped_count' => 0,
                'error_count' => 0,
            ];
        }

        return DB::transaction(function () use (
            $sourceClass,
            $targetClass,
            $normalizedStudentIds,
            $actor,
            $forceOverload,
            $cleanReason,
        ): array {
            $lockIds = [(int) $sourceClass->id, (int) $targetClass->id];
            sort($lockIds);

            $lockedClasses = Classes::query()
                ->whereIn('id', $lockIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (Classes $item): int => (int) $item->id);

            /** @var Classes $lockedSource */
            $lockedSource = $lockedClasses->get((int) $sourceClass->id) ?? $sourceClass;
            /** @var Classes $lockedTarget */
            $lockedTarget = $lockedClasses->get((int) $targetClass->id) ?? $targetClass;
            $lockedTarget->loadMissing('schedules.room');

            $sourceEnrollments = ClassEnrollment::query()
                ->with('student:id,student_id,first_name,last_name')
                ->where('class_id', $lockedSource->id)
                ->whereIn('student_id', $normalizedStudentIds)
                ->where('status', true)
                ->get()
                ->keyBy(fn (ClassEnrollment $enrollment): int => (int) $enrollment->student_id);

            $alreadyInTargetIds = ClassEnrollment::query()
                ->where('class_id', $lockedTarget->id)
                ->whereIn('student_id', $normalizedStudentIds)
                ->where('status', true)
                ->pluck('student_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            [$schoolYearVariants, $semester] = $this->resolveTermForClass($lockedTarget);

            $excludedClassByStudent = [];
            foreach ($normalizedStudentIds as $sid) {
                $excludedClassByStudent[$sid] = [(int) $lockedSource->id];
            }

            $conflictsByStudent = $this->batchScheduleConflicts(
                $lockedTarget,
                $normalizedStudentIds,
                $schoolYearVariants,
                $semester,
                $excludedClassByStudent,
            );

            $targetActiveCount = $this->activeEnrollmentCount((int) $lockedTarget->id);
            $targetMaximumSlots = (int) ($lockedTarget->maximum_slots ?? 0);

            $moved = [];
            $skipped = [];
            $errors = [];

            foreach ($normalizedStudentIds as $studentId) {
                /** @var ClassEnrollment|null $enrollment */
                $enrollment = $sourceEnrollments->get($studentId);
                if (! $enrollment instanceof ClassEnrollment) {
                    $skipped[] = [
                        'student_id' => $studentId,
                        'code' => 'not_enrolled_in_source',
                        'message' => 'Student is not actively enrolled in the source class.',
                    ];

                    continue;
                }

                $studentName = $enrollment->student?->full_name ?? 'Unknown Student';

                if (in_array($studentId, $alreadyInTargetIds, true)) {
                    $skipped[] = [
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'code' => 'already_enrolled_in_target',
                        'message' => 'Student is already actively enrolled in the target class.',
                    ];

                    continue;
                }

                if ((bool) $enrollment->is_grades_verified || (bool) $enrollment->is_grades_finalized) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'code' => 'grades_locked',
                        'message' => 'Cannot move student because grades are already finalized or verified.',
                    ];

                    continue;
                }

                $conflicts = $conflictsByStudent[$studentId] ?? [];
                if ($conflicts !== []) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'code' => 'schedule_conflict',
                        'message' => sprintf('Target section schedule conflicts with %d existing class meeting(s).', count($conflicts)),
                        'conflicts' => $conflicts,
                    ];

                    continue;
                }

                $wouldExceedCapacity = $targetMaximumSlots > 0 && ($targetActiveCount + 1) > $targetMaximumSlots;
                if ($wouldExceedCapacity && (! $forceOverload || $cleanReason === null)) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'code' => 'capacity_exceeded',
                        'message' => sprintf(
                            'Target class %s is at maximum capacity (%d/%d). Provide force_overload and a reason to proceed.',
                            $lockedTarget->record_title,
                            $targetActiveCount,
                            $targetMaximumSlots,
                        ),
                    ];

                    continue;
                }

                try {
                    $transferResult = $this->studentSectionTransferService->transferStudent(
                        $enrollment,
                        (int) $lockedTarget->id,
                        $wouldExceedCapacity ? $forceOverload : false,
                    );
                } catch (Throwable $exception) {
                    $errors[] = [
                        'student_id' => $studentId,
                        'student_name' => $studentName,
                        'code' => 'transfer_failed',
                        'message' => $exception->getMessage(),
                    ];

                    continue;
                }

                $targetActiveCount++;

                $this->logRosterActivity(
                    enrollment: $enrollment,
                    actor: $actor,
                    description: 'Student moved to another section via bulk move',
                    properties: [
                        'action' => 'bulk_move',
                        'source_class_id' => (int) $lockedSource->id,
                        'target_class_id' => (int) $lockedTarget->id,
                        'student_id' => $studentId,
                        'force_overload' => $wouldExceedCapacity && $forceOverload,
                        'reason' => $cleanReason,
                    ],
                );

                $moved[] = [
                    'student_id' => $studentId,
                    'student_name' => $studentName,
                    'enrollment_id' => (int) $enrollment->id,
                    'transfer_result' => $transferResult,
                ];
            }

            return [
                'source_class_id' => (int) $lockedSource->id,
                'target_class_id' => (int) $lockedTarget->id,
                'moved' => $moved,
                'skipped' => $skipped,
                'errors' => $errors,
                'moved_count' => count($moved),
                'skipped_count' => count($skipped),
                'error_count' => count($errors),
            ];
        });
    }

    /** @param array<string, mixed> $data */
    private function updateLockedEnrollment(
        Classes $class,
        ClassEnrollment $enrollment,
        array $data,
        ?User $actor,
    ): ClassEnrollment {
        $this->assertEnrollmentBelongsToClass($class, $enrollment);

        $forceGradeOverride = (bool) ($data['force_grade_override'] ?? false);
        $overrideReason = $this->nullableTrimmed($data['override_reason'] ?? null);

        $updates = [];
        $newEnrollmentStatus = null;

        if (array_key_exists('enrollment_status', $data) && $data['enrollment_status'] !== null) {
            $newEnrollmentStatus = (string) $data['enrollment_status'];
            if (! in_array($newEnrollmentStatus, ClassEnrollment::ENROLLMENT_STATUSES, true)) {
                throw ValidationException::withMessages([
                    'enrollment_status' => [sprintf('Invalid enrollment status "%s".', $newEnrollmentStatus)],
                ]);
            }

            $updates['enrollment_status'] = $newEnrollmentStatus;
            $updates['status'] = ClassEnrollment::statusBooleanFor($newEnrollmentStatus);
        }

        if (array_key_exists('status_reason', $data)) {
            $updates['status_reason'] = $this->nullableTrimmed($data['status_reason']);
        }

        if (array_key_exists('remarks', $data)) {
            $updates['remarks'] = $this->nullableTrimmed($data['remarks']);
        }

        $gradeKeys = ['prelim_grade', 'midterm_grade', 'finals_grade'];
        $modifyingGrades = false;
        $gradeFieldProvided = false;

        foreach ($gradeKeys as $gradeKey) {
            if (! array_key_exists($gradeKey, $data)) {
                continue;
            }

            $gradeFieldProvided = true;
            $rawValue = $data[$gradeKey];
            $normalizedValue = ($rawValue === '' || $rawValue === null) ? null : round((float) $rawValue, 2);
            $currentValue = $enrollment->{$gradeKey} === null ? null : round((float) $enrollment->{$gradeKey}, 2);

            if ($normalizedValue !== $currentValue) {
                $modifyingGrades = true;
            }

            $updates[$gradeKey] = $normalizedValue;
        }

        $isDroppingOrWithdrawing = $newEnrollmentStatus !== null
            && in_array($newEnrollmentStatus, [ClassEnrollment::STATUS_DROPPED, ClassEnrollment::STATUS_WITHDRAWN], true);

        $isGradeLocked = (bool) $enrollment->is_grades_verified || (bool) $enrollment->is_grades_finalized;
        $requiresGradeOverride = ($isGradeLocked && ($modifyingGrades || $isDroppingOrWithdrawing))
            || ($isDroppingOrWithdrawing && $enrollment->isGradeProtected());

        if ($requiresGradeOverride && (! $forceGradeOverride || $overrideReason === null)) {
            throw ValidationException::withMessages([
                'force_grade_override' => [
                    'Grades are recorded, finalized, or verified for this enrollment. Provide force_grade_override and a non-empty override_reason to proceed.',
                ],
            ]);
        }

        if ($gradeFieldProvided) {
            $prelim = array_key_exists('prelim_grade', $updates) ? $updates['prelim_grade'] : $enrollment->prelim_grade;
            $midterm = array_key_exists('midterm_grade', $updates) ? $updates['midterm_grade'] : $enrollment->midterm_grade;
            $finals = array_key_exists('finals_grade', $updates) ? $updates['finals_grade'] : $enrollment->finals_grade;

            if (is_numeric($prelim) && is_numeric($midterm) && is_numeric($finals)) {
                $updates['total_average'] = app(GradeComputationService::class)->totalAverage(
                    (float) $prelim,
                    (float) $midterm,
                    (float) $finals,
                );
            } else {
                $updates['total_average'] = null;
            }
        }

        if ($requiresGradeOverride && $overrideReason !== null && ! array_key_exists('status_reason', $updates)) {
            $updates['status_reason'] = $overrideReason;
        }

        if ($updates !== []) {
            $enrollment->fill($updates)->save();
        }

        if ($requiresGradeOverride && $forceGradeOverride && $overrideReason !== null) {
            $this->logRosterActivity(
                enrollment: $enrollment,
                actor: $actor,
                description: 'Class enrollment updated with grade-lock override',
                properties: [
                    'action' => 'update_grade_override',
                    'class_id' => (int) $class->id,
                    'student_id' => (int) $enrollment->student_id,
                    'override_reason' => $overrideReason,
                    'updated_fields' => array_keys($updates),
                ],
            );
        }

        return $enrollment->fresh() ?? $enrollment;
    }

    /**
     * Resolve school year variants and semester for a given class.
     *
     * @return array{0: list<string>, 1: int}
     */
    private function resolveTermForClass(Classes $class): array
    {
        $schoolYear = filled($class->school_year)
            ? (string) $class->school_year
            : $this->generalSettingsService->getCurrentSchoolYearString();

        $semester = $class->semester !== null
            ? (int) $class->semester
            : $this->generalSettingsService->getCurrentSemester();

        $normalized = GeneralSettingsService::normalizeSchoolYear($schoolYear);
        $compact = str_replace(' ', '', $normalized);
        $variants = array_values(array_unique([$schoolYear, $normalized, $compact]));

        return [$variants, $semester];
    }

    private function activeEnrollmentCount(int $classId): int
    {
        return ClassEnrollment::query()
            ->where('class_id', $classId)
            ->where('status', true)
            ->count();
    }

    /**
     * Batch-detect sibling section enrollments (same subject_code + school_year + semester, different class_id).
     *
     * @param  list<int>  $studentIds
     * @param  list<string>  $schoolYearVariants
     * @return array<int, array{class_id: int, section: string, enrollment_id: int}>
     */
    private function batchSiblingSectionEnrollments(
        Classes $class,
        array $studentIds,
        array $schoolYearVariants,
        int $semester,
    ): array {
        $subjectCode = mb_trim((string) ($class->subject_code ?? ''));
        if ($studentIds === [] || $subjectCode === '') {
            return [];
        }

        $siblingEnrollments = ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('class_id', '!=', $class->id)
            ->where('status', true)
            ->whereHas('class', function (Builder $query) use ($subjectCode, $schoolYearVariants, $semester): void {
                $query->where('subject_code', $subjectCode)
                    ->whereIn('school_year', $schoolYearVariants)
                    ->where('semester', $semester);
            })
            ->with(['class:id,section,subject_code,school_year,semester'])
            ->get(['id', 'student_id', 'class_id']);

        $result = [];
        foreach ($siblingEnrollments as $enrollment) {
            $studentId = (int) $enrollment->student_id;
            if (isset($result[$studentId])) {
                continue;
            }

            $result[$studentId] = [
                'class_id' => (int) $enrollment->class_id,
                'section' => (string) ($enrollment->class?->section ?? ''),
                'enrollment_id' => (int) $enrollment->id,
            ];
        }

        return $result;
    }

    /**
     * Batch-compute schedule conflicts between the target class and each candidate student's active term classes
     * in a single query (zero N+1).
     *
     * @param  list<int>  $studentIds
     * @param  list<string>  $schoolYearVariants
     * @param  array<int, list<int>>  $excludedClassIdsByStudent
     * @return array<int, list<array<string, mixed>>>
     */
    private function batchScheduleConflicts(
        Classes $targetClass,
        array $studentIds,
        array $schoolYearVariants,
        int $semester,
        array $excludedClassIdsByStudent = [],
    ): array {
        if ($studentIds === []) {
            return [];
        }

        $targetClass->loadMissing('schedules.room');
        $policy = ScheduleDelivery::policy();
        $targetSlots = $this->extractCheckableSlots($targetClass, $policy);

        if ($targetSlots === []) {
            return [];
        }

        /** @var Collection<int, ClassEnrollment> $termEnrollments */
        $termEnrollments = ClassEnrollment::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', true)
            ->where('class_id', '!=', $targetClass->id)
            ->whereHas('class', function (Builder $query) use ($schoolYearVariants, $semester): void {
                $query->whereIn('school_year', $schoolYearVariants)
                    ->where('semester', $semester);
            })
            ->with([
                'class' => function ($query): void {
                    $query->select(['id', 'subject_code', 'section', 'delivery_mode', 'school_year', 'semester'])
                        ->with(['schedules.room:id,name']);
                },
            ])
            ->get(['id', 'student_id', 'class_id']);

        $conflictsByStudent = [];

        foreach ($termEnrollments->groupBy(fn (ClassEnrollment $item): int => (int) $item->student_id) as $studentId => $enrollments) {
            $excludedIds = $excludedClassIdsByStudent[(int) $studentId] ?? [];
            $seenKeys = [];
            $studentConflicts = [];

            foreach ($enrollments as $enrollment) {
                $otherClass = $enrollment->class;
                if (! $otherClass instanceof Classes) {
                    continue;
                }

                if (in_array((int) $otherClass->id, $excludedIds, true)) {
                    continue;
                }

                $otherSlots = $this->extractCheckableSlots($otherClass, $policy);
                foreach ($targetSlots as $targetSlot) {
                    foreach ($otherSlots as $otherSlot) {
                        if ($targetSlot['day_key'] !== $otherSlot['day_key']) {
                            continue;
                        }

                        $overlapStart = max($targetSlot['start_minutes'], $otherSlot['start_minutes']);
                        $overlapEnd = min($targetSlot['end_minutes'], $otherSlot['end_minutes']);

                        if ($overlapStart >= $overlapEnd) {
                            continue;
                        }

                        $key = sprintf(
                            '%s|%d|%d|%d|%d',
                            $targetSlot['day_key'],
                            $targetSlot['schedule_id'],
                            $otherSlot['schedule_id'],
                            $overlapStart,
                            $overlapEnd,
                        );

                        if (isset($seenKeys[$key])) {
                            continue;
                        }

                        $seenKeys[$key] = true;
                        $studentConflicts[] = [
                            'day' => $targetSlot['day'],
                            'overlap_start' => $this->minutesToTime($overlapStart),
                            'overlap_end' => $this->minutesToTime($overlapEnd),
                            'conflicting_class_id' => (int) $otherClass->id,
                            'conflicting_subject_code' => (string) ($otherClass->subject_code ?? 'N/A'),
                            'conflicting_section' => (string) ($otherClass->section ?? 'N/A'),
                            'conflicting_start_time' => $otherSlot['start_time'],
                            'conflicting_end_time' => $otherSlot['end_time'],
                            'conflicting_room' => $otherSlot['room'],
                            'target_start_time' => $targetSlot['start_time'],
                            'target_end_time' => $targetSlot['end_time'],
                        ];
                    }
                }
            }

            if ($studentConflicts !== []) {
                $conflictsByStudent[(int) $studentId] = $studentConflicts;
            }
        }

        return $conflictsByStudent;
    }

    /**
     * @param  array<string, mixed>  $policy
     * @return list<array{
     *     schedule_id: int,
     *     day: string,
     *     day_key: string,
     *     start_time: string,
     *     end_time: string,
     *     start_minutes: int,
     *     end_minutes: int,
     *     room: string
     * }>
     */
    private function extractCheckableSlots(Classes $class, array $policy): array
    {
        $slots = [];

        /** @var Schedule $schedule */
        foreach ($class->schedules as $schedule) {
            $deliveryMode = $schedule->delivery_mode ?? $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE;
            if (! ScheduleDelivery::checksStudentCohortConflicts($deliveryMode, $policy)) {
                continue;
            }

            $dayRaw = mb_trim((string) $schedule->day_of_week);
            $startTime = $schedule->start_time?->format('H:i');
            $endTime = $schedule->end_time?->format('H:i');

            if ($dayRaw === '' || $startTime === null || $endTime === null) {
                continue;
            }

            $startMinutes = $this->timeToMinutes($startTime);
            $endMinutes = $this->timeToMinutes($endTime);
            if ($startMinutes === null || $endMinutes === null || $endMinutes <= $startMinutes) {
                continue;
            }

            $dayKey = mb_strtolower($dayRaw);
            $slots[] = [
                'schedule_id' => (int) $schedule->id,
                'day' => mb_ucfirst($dayKey),
                'day_key' => $dayKey,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'start_minutes' => $startMinutes,
                'end_minutes' => $endMinutes,
                'room' => ScheduleDelivery::locationLabel($deliveryMode, $schedule->room?->name, 'Room TBA'),
            ];
        }

        return $slots;
    }

    private function determineRecommendedAction(
        bool $alreadyEnrolledHere,
        int $blockingHoldsCount,
        int $scheduleConflictsCount,
        bool $hasSiblingEnrollment,
        bool $wouldExceedCapacity,
    ): string {
        if ($alreadyEnrolledHere) {
            return 'already_enrolled';
        }

        if ($blockingHoldsCount > 0) {
            return 'blocked_hold';
        }

        if ($scheduleConflictsCount > 0) {
            return 'blocked_clash';
        }

        if ($hasSiblingEnrollment) {
            return 'transfer';
        }

        if ($wouldExceedCapacity) {
            return 'overload_required';
        }

        return 'enroll';
    }

    /**
     * @param  array<int, int|string>  $identifiers
     * @return list<int|string>
     */
    private function normalizeIdentifiers(array $identifiers): array
    {
        $normalized = [];
        $seen = [];

        foreach ($identifiers as $raw) {
            if (is_int($raw)) {
                $key = 'int:'.$raw;
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $normalized[] = $raw;
                }

                continue;
            }

            if (is_string($raw)) {
                $trimmed = mb_trim($raw);
                if ($trimmed === '') {
                    continue;
                }

                $key = 'str:'.mb_strtolower($trimmed);
                if (! isset($seen[$key])) {
                    $seen[$key] = true;
                    $normalized[] = $trimmed;
                }
            }
        }

        return $normalized;
    }

    /**
     * Batch-resolve mixed student identifiers (numeric primary IDs, student_id numbers, LRNs, emails).
     *
     * @param  list<int|string>  $identifiers
     * @return array{0: list<Student>, 1: list<array<string, mixed>>}
     */
    private function resolveStudentsByIdentifiers(array $identifiers): array
    {
        $numericIds = [];
        $stringTokens = [];
        $lowerEmails = [];

        foreach ($identifiers as $identifier) {
            if (is_int($identifier)) {
                $numericIds[] = $identifier;
                $stringTokens[] = (string) $identifier;
            } else {
                $stringTokens[] = $identifier;
                if (ctype_digit($identifier)) {
                    $numericIds[] = (int) $identifier;
                }
                if (str_contains($identifier, '@')) {
                    $lowerEmails[] = mb_strtolower($identifier);
                }
            }
        }

        $numericIds = array_values(array_unique($numericIds));
        $stringTokens = array_values(array_unique($stringTokens));
        $lowerEmails = array_values(array_unique($lowerEmails));

        $candidates = Student::query()
            ->where(function (Builder $query) use ($numericIds, $stringTokens, $lowerEmails): void {
                $hasClause = false;

                if ($numericIds !== []) {
                    $query->whereIn('id', $numericIds);
                    $hasClause = true;
                }

                if ($stringTokens !== []) {
                    if ($hasClause) {
                        $query->orWhereIn('student_id', $stringTokens)
                            ->orWhereIn('lrn', $stringTokens);
                    } else {
                        $query->whereIn('student_id', $stringTokens)
                            ->orWhereIn('lrn', $stringTokens);
                        $hasClause = true;
                    }
                }

                if ($lowerEmails !== []) {
                    $placeholders = implode(', ', array_fill(0, count($lowerEmails), '?'));
                    if ($hasClause) {
                        $query->orWhereRaw("LOWER(email) IN ({$placeholders})", $lowerEmails);
                    } else {
                        $query->whereRaw("LOWER(email) IN ({$placeholders})", $lowerEmails);
                    }
                }
            })
            ->get();

        $byId = [];
        $byStudentId = [];
        $byLrn = [];
        $byEmail = [];

        foreach ($candidates as $student) {
            $byId[(int) $student->id] = $student;

            if (filled($student->student_id)) {
                $byStudentId[mb_strtolower(mb_trim((string) $student->student_id))] = $student;
            }
            if (filled($student->lrn)) {
                $byLrn[mb_strtolower(mb_trim((string) $student->lrn))] = $student;
            }
            if (filled($student->email)) {
                $byEmail[mb_strtolower(mb_trim((string) $student->email))] = $student;
            }
        }

        $resolved = [];
        $seenStudentIds = [];
        $errors = [];

        foreach ($identifiers as $identifier) {
            $matched = null;

            if (is_int($identifier)) {
                $matched = $byId[$identifier] ?? $byStudentId[(string) $identifier] ?? null;
            } else {
                $lower = mb_strtolower($identifier);
                if (ctype_digit($identifier) && isset($byId[(int) $identifier])) {
                    $matched = $byId[(int) $identifier];
                }
                $matched ??= $byStudentId[$lower] ?? $byLrn[$lower] ?? $byEmail[$lower] ?? null;
            }

            if (! $matched instanceof Student) {
                $errors[] = [
                    'identifier' => $identifier,
                    'code' => 'student_not_found',
                    'message' => sprintf('No student found matching identifier "%s".', (string) $identifier),
                ];

                continue;
            }

            $matchedId = (int) $matched->id;
            if (isset($seenStudentIds[$matchedId])) {
                continue;
            }

            $seenStudentIds[$matchedId] = true;
            $resolved[] = $matched;
        }

        return [$resolved, $errors];
    }

    /**
     * Sync an existing term SubjectEnrollment to the newly enrolled class when requested.
     *
     * @param  list<string>  $schoolYearVariants
     */
    private function syncExistingSubjectEnrollment(
        int $studentId,
        Classes $class,
        array $schoolYearVariants,
        int $semester,
    ): void {
        $subjectCode = mb_trim((string) ($class->subject_code ?? ''));
        if ($subjectCode === '' && ! $class->subject_id) {
            return;
        }

        $subjectEnrollment = SubjectEnrollment::query()
            ->where('student_id', $studentId)
            ->whereIn('school_year', $schoolYearVariants)
            ->where('semester', $semester)
            ->where(function (Builder $query) use ($class, $subjectCode): void {
                if ($class->subject_id) {
                    $query->where('subject_id', $class->subject_id);
                }
                if ($subjectCode !== '') {
                    $query->orWhereHas('subject', fn (Builder $subjectQuery) => $subjectQuery->where('code', $subjectCode));
                }
            })
            ->first();

        if (! $subjectEnrollment instanceof SubjectEnrollment) {
            return;
        }

        $subjectEnrollment->class_id = (int) $class->id;
        $subjectEnrollment->section = $class->section ?? (string) $class->id;
        $subjectEnrollment->save();
    }

    /**
     * @throws ValidationException
     */
    private function assertEnrollmentBelongsToClass(Classes $class, ClassEnrollment $enrollment): void
    {
        if ((int) $enrollment->class_id !== (int) $class->id) {
            throw ValidationException::withMessages([
                'enrollment' => ['The specified enrollment does not belong to this class.'],
            ]);
        }
    }

    private function isSameSubject(Classes $sourceClass, Classes $targetClass): bool
    {
        $sourceCode = mb_strtolower(mb_trim((string) ($sourceClass->subject_code ?? '')));
        $targetCode = mb_strtolower(mb_trim((string) ($targetClass->subject_code ?? '')));

        if ($sourceCode !== '' && $targetCode !== '') {
            return $sourceCode === $targetCode;
        }

        return $sourceClass->subject_id !== null
            && $targetClass->subject_id !== null
            && (int) $sourceClass->subject_id === (int) $targetClass->subject_id;
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function logRosterActivity(
        ClassEnrollment $enrollment,
        ?User $actor,
        string $description,
        array $properties,
    ): void {
        if (! function_exists('activity')) {
            return;
        }

        $logger = activity('class_enrollments')
            ->performedOn($enrollment)
            ->withProperties($properties);

        if ($actor instanceof User) {
            $logger->causedBy($actor);
        }

        $logger->log($description);
    }

    private function nullableTrimmed(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = mb_trim($value);

        return $trimmed === '' ? null : $trimmed;
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
