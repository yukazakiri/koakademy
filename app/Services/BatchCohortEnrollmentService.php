<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\Enrollment\EnrollmentSubmissionData;
use App\Enrollment\EnrollmentFeeCalculator;
use App\Enrollment\EnrollmentWorkflowCoordinator;
use App\Enums\StudentStatus;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final readonly class BatchCohortEnrollmentService
{
    public function __construct(
        private GeneralSettingsService $settingsService,
        private EnrollmentPipelineService $pipelineService,
        private EnrollmentWorkflowCoordinator $workflowCoordinator,
        private EnrollmentService $enrollmentService,
        private EnrollmentPeriodService $periodService,
        private StudentHoldService $holdService,
    ) {}

    /**
     * Build a preview of eligible continuing students, curriculum subjects, and available block sections.
     *
     * @return array<string, mixed>
     */
    public function preview(
        int $courseId,
        int $sourceYearLevel,
        int $targetYearLevel,
        int $targetSemester,
        ?string $schoolYear = null,
    ): array {
        $course = Course::query()->findOrFail($courseId);
        $resolvedYear = GeneralSettingsService::normalizeSchoolYear(
            $schoolYear ?? $this->settingsService->getCurrentSchoolYearString()
        );

        $alreadyEnrolledStudentIds = StudentEnrollment::query()
            ->where('school_year', $resolvedYear)
            ->where('semester', $targetSemester)
            ->pluck('student_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->all();

        $studentsQuery = Student::query()
            ->with('Course')
            ->where('course_id', $course->id)
            ->where('academic_year', $sourceYearLevel)
            ->whereNotIn('status', [
                StudentStatus::Rejected->value,
                StudentStatus::Dropped->value,
                StudentStatus::Graduated->value,
            ])
            ->when($alreadyEnrolledStudentIds !== [], fn ($q) => $q->whereNotIn('id', $alreadyEnrolledStudentIds))
            ->orderBy('last_name')
            ->orderBy('first_name');

        $students = $studentsQuery->limit(250)->get();
        $holdSummaries = $this->holdService->summariesForStudents(
            $students->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        );

        $subjects = Subject::query()
            ->where('course_id', $course->id)
            ->where('academic_year', $targetYearLevel)
            ->where('semester', $targetSemester)
            ->orderBy('code')
            ->get();

        $classes = $this->findMatchingClassesForSubjects($subjects, $course->id, $resolvedYear, $targetSemester);
        $sections = $this->summarizeBlockSections($classes, $subjects);

        $lecRate = (float) ($course->lec_per_unit ?? 0);
        $labRate = (float) ($course->lab_per_unit ?? 0);
        $miscFee = (float) ($course->getMiscellaneousFee() ?? EnrollmentFeeCalculator::Defaults['miscellaneous_fee_fallback']);

        $subjectPayloads = $subjects->map(function (Subject $subject) use ($lecRate, $labRate, $classes): array {
            $lecUnits = (int) ($subject->lecture ?? 0);
            $labUnits = (int) ($subject->laboratory ?? 0);
            $matchedClasses = $this->classesForSubject($classes, $subject);

            return [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'lecture_units' => $lecUnits,
                'laboratory_units' => $labUnits,
                'total_units' => $lecUnits + $labUnits,
                'lecture_fee' => round($lecUnits * $lecRate, 2),
                'laboratory_fee' => round($labUnits * $labRate, 2),
                'available_classes_count' => $matchedClasses->count(),
            ];
        })->values();

        $estimatedSubjectsTotal = (float) $subjectPayloads->sum(fn (array $s): float => $s['lecture_fee'] + $s['laboratory_fee']);
        $periodStatus = $this->periodService->describe($resolvedYear, $targetSemester);
        $lateFee = ($periodStatus['is_late'] && $periodStatus['auto_apply_late_fee']) ? (float) $periodStatus['late_fee_amount'] : 0.0;

        return [
            'course' => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'miscellaneous_fee' => $miscFee,
            ],
            'term' => [
                'school_year' => $resolvedYear,
                'semester' => $targetSemester,
            ],
            'students' => $students->map(function (Student $student) use ($holdSummaries): array {
                $holds = $holdSummaries[(int) $student->id] ?? ['active_count' => 0, 'blocking_count' => 0, 'offices' => []];

                return [
                    'id' => $student->id,
                    'student_number' => $student->student_id ?? $student->id,
                    'full_name' => $student->full_name,
                    'email' => $student->email,
                    'academic_year' => $student->academic_year,
                    'status' => $student->status instanceof StudentStatus ? $student->status->value : (string) $student->status,
                    'active_holds_count' => $holds['active_count'],
                    'blocking_holds_count' => $holds['blocking_count'],
                    'hold_offices' => $holds['offices'],
                ];
            })->values()->all(),
            'subjects' => $subjectPayloads->all(),
            'available_sections' => $sections,
            'estimated_tuition_per_student' => round($estimatedSubjectsTotal + $miscFee + $lateFee, 2),
            'late_fee_applied' => $lateFee,
            'enrollment_period' => $periodStatus,
        ];
    }

    /**
     * Execute cohort / block-section enrollment for the chosen students.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function execute(array $data, User $actor): array
    {
        $course = Course::query()->findOrFail((int) $data['course_id']);
        $targetYearLevel = (int) $data['target_year_level'];
        $targetSemester = (int) $data['target_semester'];
        $schoolYear = GeneralSettingsService::normalizeSchoolYear(
            isset($data['school_year']) && filled($data['school_year'])
                ? (string) $data['school_year']
                : $this->settingsService->getCurrentSchoolYearString(),
        );

        $periodStatus = $this->periodService->assertEnrollmentAllowed(
            overrideWindow: (bool) ($data['override_window'] ?? false),
            schoolYear: $schoolYear,
            semester: $targetSemester,
        );
        $additionalFees = $this->periodService->appendLateFeeIfApplicable([], $periodStatus);

        $subjectIds = array_values(array_unique(array_map(intval(...), $data['subject_ids'] ?? [])));
        $subjects = Subject::query()
            ->where('course_id', $course->id)
            ->whereIn('id', $subjectIds)
            ->orderBy('code')
            ->get();

        if ($subjects->isEmpty()) {
            throw new RuntimeException('Select at least one curriculum subject for the cohort block.');
        }

        $autoAssign = (bool) ($data['auto_assign_classes'] ?? true);
        $blockSection = isset($data['block_section']) && mb_trim((string) $data['block_section']) !== ''
            ? mb_trim((string) $data['block_section'])
            : null;
        $forceOverload = (bool) ($data['force_overload'] ?? false);
        $overrideHolds = (bool) ($data['override_holds'] ?? false);
        $overrideHoldsReason = isset($data['override_holds_reason']) ? (string) $data['override_holds_reason'] : null;
        $promoteYearLevel = (bool) ($data['promote_student_year_level'] ?? true);

        $miscFee = isset($data['miscellaneous_fee']) && $data['miscellaneous_fee'] !== null
            ? (float) $data['miscellaneous_fee']
            : (float) ($course->getMiscellaneousFee() ?? EnrollmentFeeCalculator::Defaults['miscellaneous_fee_fallback']);
        $downpayment = (float) ($data['downpayment'] ?? 0);

        $studentIds = array_values(array_unique(array_map(intval(...), $data['student_ids'] ?? [])));
        $students = Student::query()
            ->with('Course')
            ->whereIn('id', $studentIds)
            ->get()
            ->keyBy('id');

        $pendingStatus = $this->pipelineService->getPendingStatus();
        $lecRate = (float) ($course->lec_per_unit ?? 0);
        $labRate = (float) ($course->lab_per_unit ?? 0);

        $enrolledCount = 0;
        $skippedCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($studentIds as $studentId) {
            $student = $students->get($studentId);
            if (! $student instanceof Student) {
                $skippedCount++;
                $results[] = [
                    'student_id' => $studentId,
                    'student_name' => 'Student #'.$studentId,
                    'status' => 'skipped',
                    'enrollment_id' => null,
                    'assigned_classes_count' => 0,
                    'unassigned_classes_count' => 0,
                    'message' => 'Student record not found.',
                ];

                continue;
            }

            try {
                $outcome = DB::transaction(function () use (
                    $student,
                    $course,
                    $subjects,
                    $schoolYear,
                    $targetSemester,
                    $targetYearLevel,
                    $pendingStatus,
                    $lecRate,
                    $labRate,
                    $miscFee,
                    $downpayment,
                    $additionalFees,
                    $autoAssign,
                    $blockSection,
                    $forceOverload,
                    $overrideHolds,
                    $overrideHoldsReason,
                    $promoteYearLevel,
                    $actor,
                ): array {
                    $existing = StudentEnrollment::query()
                        ->where('student_id', $student->id)
                        ->where('school_year', $schoolYear)
                        ->where('semester', $targetSemester)
                        ->first();

                    if ($existing instanceof StudentEnrollment) {
                        return [
                            'status' => 'skipped',
                            'enrollment_id' => $existing->id,
                            'assigned_classes_count' => 0,
                            'unassigned_classes_count' => 0,
                            'message' => 'Already enrolled for this term.',
                        ];
                    }

                    $this->holdService->assertNoBlockingEnrollmentHolds(
                        student: $student,
                        actor: $actor,
                        overrideHolds: $overrideHolds,
                        overrideReason: $overrideHoldsReason,
                    );

                    // Re-query class counts inside transaction for accurate capacity checks
                    $availableClasses = $autoAssign
                        ? $this->findMatchingClassesForSubjects($subjects, $course->id, $schoolYear, $targetSemester)
                        : collect();

                    $subjectRows = [];
                    $classAssignments = [];
                    $assignedCount = 0;
                    $unassignedCount = 0;

                    foreach ($subjects as $subject) {
                        $lecUnits = (int) ($subject->lecture ?? 0);
                        $labUnits = (int) ($subject->laboratory ?? 0);
                        $pickedClass = $autoAssign
                            ? $this->pickClassForSubject($availableClasses, $subject, $blockSection, $forceOverload)
                            : null;

                        if ($pickedClass instanceof Classes) {
                            $assignedCount++;
                            $classAssignments[] = [
                                'subject_id' => $subject->id,
                                'class_id' => $pickedClass->id,
                            ];
                        } else {
                            $unassignedCount++;
                        }

                        $subjectRows[] = [
                            'subject_id' => $subject->id,
                            'class_id' => $pickedClass?->id,
                            'is_modular' => false,
                            'exclude_from_tuition' => false,
                            'lecture_fee' => round($lecUnits * $lecRate, 2),
                            'laboratory_fee' => round($labUnits * $labRate, 2),
                            'enrolled_lecture_units' => $lecUnits,
                            'enrolled_laboratory_units' => $labUnits,
                        ];
                    }

                    $submission = new EnrollmentSubmissionData(
                        enrollmentAttributes: [
                            'student_id' => $student->id,
                            'course_id' => $course->id,
                            'status' => $pendingStatus,
                            'semester' => $targetSemester,
                            'academic_year' => $targetYearLevel,
                            'school_year' => $schoolYear,
                            'downpayment' => $downpayment,
                        ],
                        subjects: $subjectRows,
                        classAssignments: $classAssignments,
                        additionalFees: $additionalFees,
                        billingOverrides: [
                            'discount_percentage' => 0,
                            'discount_id' => null,
                            'miscellaneous_fee' => $miscFee,
                            'downpayment' => $downpayment,
                        ],
                        channel: 'administrator',
                        idempotencyKey: 'batch-cohort:'.Str::uuid(),
                        actor: $actor,
                    );

                    $enrollment = $this->workflowCoordinator->submit(
                        $submission,
                        function (StudentEnrollment $legacyEnrollment) use (
                            $subjectRows,
                            $student,
                            $targetYearLevel,
                            $schoolYear,
                            $targetSemester,
                            $additionalFees,
                            $miscFee,
                            $downpayment,
                        ): void {
                            foreach ($subjectRows as $subjectData) {
                                $classId = $subjectData['class_id'];
                                $legacyEnrollment->subjectsEnrolled()->create([
                                    ...$subjectData,
                                    'student_id' => $student->id,
                                    'academic_year' => $targetYearLevel,
                                    'school_year' => $schoolYear,
                                    'semester' => $targetSemester,
                                ]);

                                if ($classId) {
                                    $classEnrollment = ClassEnrollment::query()->withTrashed()->firstOrNew([
                                        'student_id' => $student->id,
                                        'class_id' => $classId,
                                    ]);
                                    if ($classEnrollment->trashed()) {
                                        $classEnrollment->restore();
                                    }
                                    $classEnrollment->status = true;
                                    $classEnrollment->save();
                                }
                            }

                            foreach ($additionalFees as $fee) {
                                $legacyEnrollment->additionalFees()->create([
                                    'fee_name' => $fee['fee_name'],
                                    'amount' => $fee['amount'],
                                ]);
                            }

                            $tuition = $this->enrollmentService->createStudentTuition($legacyEnrollment, [
                                'subjectsEnrolled' => $subjectRows,
                                'discount' => 0,
                                'discount_id' => null,
                                'miscellaneous_fee' => $miscFee,
                                'downpayment' => $downpayment,
                                'additionalFees' => $additionalFees,
                            ]);

                            if (! $tuition instanceof StudentTuition) {
                                throw new RuntimeException('Failed to generate tuition assessment.');
                            }
                        },
                    );

                    if ($promoteYearLevel && (int) $student->academic_year !== $targetYearLevel) {
                        $student->forceFill(['academic_year' => $targetYearLevel])->save();
                    }

                    return [
                        'status' => 'enrolled',
                        'enrollment_id' => $enrollment->id,
                        'assigned_classes_count' => $assignedCount,
                        'unassigned_classes_count' => $unassignedCount,
                        'message' => $unassignedCount > 0
                            ? sprintf('Enrolled (%d/%d classes assigned; %d awaiting open seats)', $assignedCount, count($subjectRows), $unassignedCount)
                            : sprintf('Enrolled with all %d classes assigned.', $assignedCount),
                    ];
                });

                if ($outcome['status'] === 'enrolled') {
                    $enrolledCount++;
                } else {
                    $skippedCount++;
                }

                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    ...$outcome,
                ];
            } catch (Throwable $throwable) {
                $failedCount++;
                $results[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->full_name,
                    'status' => 'failed',
                    'enrollment_id' => null,
                    'assigned_classes_count' => 0,
                    'unassigned_classes_count' => 0,
                    'message' => $throwable->getMessage(),
                ];
            }
        }

        return [
            'enrolled_count' => $enrolledCount,
            'skipped_count' => $skippedCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ];
    }

    /**
     * @param  Collection<int, Subject>  $subjects
     * @return Collection<int, Classes>
     */
    private function findMatchingClassesForSubjects(Collection $subjects, int $courseId, string $schoolYear, int $semester): Collection
    {
        if ($subjects->isEmpty()) {
            return collect();
        }

        $subjectIds = $subjects->pluck('id')->all();
        $subjectCodes = $subjects->pluck('code')->filter()->map(fn ($c): string => mb_strtolower(mb_trim((string) $c)))->all();

        return Classes::query()
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->withCount('class_enrollments')
            ->get()
            ->filter(function (Classes $class) use ($courseId, $subjectIds, $subjectCodes): bool {
                $courseMatches = empty($class->course_codes)
                    || in_array((string) $courseId, array_map(strval(...), (array) $class->course_codes), true);

                if (! $courseMatches) {
                    return false;
                }

                $classSubjectIds = array_map(intval(...), (array) ($class->subject_ids ?? []));
                if (array_intersect($subjectIds, $classSubjectIds) !== []) {
                    return true;
                }

                $codes = array_map(
                    fn (string $c): string => mb_strtolower(mb_trim($c)),
                    explode(',', (string) $class->subject_code),
                );

                return array_intersect($subjectCodes, $codes) !== [];
            })
            ->values();
    }

    /**
     * @param  Collection<int, Classes>  $classes
     * @return Collection<int, Classes>
     */
    private function classesForSubject(Collection $classes, Subject $subject): Collection
    {
        $needle = mb_strtolower(mb_trim((string) $subject->code));

        return $classes->filter(function (Classes $class) use ($subject, $needle): bool {
            $ids = array_map(intval(...), (array) ($class->subject_ids ?? []));
            if (in_array((int) $subject->id, $ids, true)) {
                return true;
            }

            $codes = array_map(
                fn (string $c): string => mb_strtolower(mb_trim($c)),
                explode(',', (string) $class->subject_code),
            );

            return in_array($needle, $codes, true);
        })->values();
    }

    /**
     * @param  Collection<int, Classes>  $classes
     * @param  Collection<int, Subject>  $subjects
     * @return list<array<string, mixed>>
     */
    private function summarizeBlockSections(Collection $classes, Collection $subjects): array
    {
        return $classes
            ->filter(fn (Classes $class): bool => filled($class->section))
            ->groupBy(fn (Classes $class): string => mb_trim((string) $class->section))
            ->map(function (Collection $sectionClasses, string $sectionName) use ($subjects): array {
                $matchedSubjectCount = $subjects->filter(
                    fn (Subject $subject): bool => $this->classesForSubject($sectionClasses, $subject)->isNotEmpty(),
                )->count();

                $availableSlotsList = $sectionClasses
                    ->filter(fn (Classes $c): bool => (int) ($c->maximum_slots ?? 0) > 0)
                    ->map(fn (Classes $c): int => max(0, (int) $c->maximum_slots - (int) ($c->class_enrollments_count ?? 0)));

                $minAvailable = $availableSlotsList->isNotEmpty() ? (int) $availableSlotsList->min() : null;

                return [
                    'section' => $sectionName,
                    'matched_subjects_count' => $matchedSubjectCount,
                    'total_subjects_count' => $subjects->count(),
                    'classes_count' => $sectionClasses->count(),
                    'min_available_slots' => $minAvailable,
                    'has_full_class' => $minAvailable !== null && $minAvailable <= 0,
                ];
            })
            ->sortByDesc('matched_subjects_count')
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, Classes>  $availableClasses
     */
    private function pickClassForSubject(
        Collection $availableClasses,
        Subject $subject,
        ?string $blockSection,
        bool $forceOverload,
    ): ?Classes {
        $candidates = $this->classesForSubject($availableClasses, $subject);
        if ($candidates->isEmpty()) {
            return null;
        }

        $hasSeat = static fn (Classes $class): bool => $forceOverload
            || (int) ($class->maximum_slots ?? 0) <= 0
            || (int) ($class->class_enrollments_count ?? 0) < (int) $class->maximum_slots;

        if ($blockSection !== null) {
            $preferred = $candidates->first(
                fn (Classes $class): bool => mb_strtolower(mb_trim((string) $class->section)) === mb_strtolower($blockSection) && $hasSeat($class),
            );
            if ($preferred instanceof Classes) {
                return $preferred;
            }
        }

        return $candidates
            ->filter($hasSeat)
            ->sortBy(fn (Classes $class): int => (int) ($class->class_enrollments_count ?? 0))
            ->first();
    }
}
