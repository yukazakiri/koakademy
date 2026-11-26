<?php

declare(strict_types=1);

namespace App\Services;

use App\Enrollment\EnrollmentCancellationService;
use App\Enrollment\EnrollmentFeeCalculator;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\EnrollmentClassWaitlist;
use App\Models\EnrollmentTransferLog;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final readonly class EnrollmentTransferService
{
    public function __construct(
        private EnrollmentLoadChangeService $loadChangeService,
    ) {}

    /**
     * Gather transfer history, available target sections, and target courses for an enrollment.
     *
     * @return array<string, mixed>
     */
    public function options(StudentEnrollment $enrollment): array
    {
        $enrollment->loadMissing(['course', 'subjectsEnrolled.class', 'subjectsEnrolled.subject']);

        $currentSection = $enrollment->subjectsEnrolled
            ->map(fn (SubjectEnrollment $se): ?string => $se->class?->section)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        $availableSections = Classes::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->whereNotNull('section')
            ->get()
            ->filter(function (Classes $class) use ($enrollment): bool {
                if (! $enrollment->course_id || empty($class->course_codes)) {
                    return true;
                }

                return in_array((string) $enrollment->course_id, array_map(strval(...), (array) $class->course_codes), true);
            })
            ->pluck('section')
            ->map(fn ($s): string => mb_trim((string) $s))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $courses = Course::query()
            ->orderBy('code')
            ->get(['id', 'code', 'title'])
            ->map(fn (Course $course): array => [
                'id' => $course->id,
                'code' => $course->code,
                'title' => $course->title,
                'is_current' => (int) $course->id === (int) $enrollment->course_id,
            ])
            ->values()
            ->all();

        $history = EnrollmentTransferLog::query()
            ->with(['fromCourse:id,code,title', 'toCourse:id,code,title', 'performedBy:id,name'])
            ->where('student_enrollment_id', $enrollment->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (EnrollmentTransferLog $log): array => $this->serialize($log))
            ->values()
            ->all();

        return [
            'current_course' => $enrollment->course ? [
                'id' => $enrollment->course->id,
                'code' => $enrollment->course->code,
                'title' => $enrollment->course->title,
            ] : null,
            'current_section' => $currentSection,
            'academic_year' => $enrollment->academic_year,
            'available_sections' => $availableSections,
            'courses' => $courses,
            'history' => $history,
        ];
    }

    /**
     * Preview moving all subjects on an enrollment into a target block section.
     *
     * @return array<string, mixed>
     */
    public function previewSectionTransfer(StudentEnrollment $enrollment, string $targetSection, bool $forceOverload = false): array
    {
        $enrollment->loadMissing(['subjectsEnrolled.subject', 'subjectsEnrolled.class']);
        $cleanTarget = mb_trim($targetSection);

        $sectionClasses = Classes::query()
            ->where('school_year', $enrollment->school_year)
            ->where('semester', $enrollment->semester)
            ->whereRaw('LOWER(TRIM(section)) = LOWER(?)', [$cleanTarget])
            ->withCount(['class_enrollments' => fn ($query) => $query->where('status', true)])
            ->get();

        $movable = [];
        $full = [];
        $unmatched = [];

        foreach ($enrollment->subjectsEnrolled as $se) {
            if (! $se->subject instanceof Subject) {
                continue;
            }

            $matched = $this->findClassForSubject($sectionClasses, $se->subject);
            if (! $matched instanceof Classes) {
                $unmatched[] = [
                    'subject_code' => $se->subject->code,
                    'subject_title' => $se->subject->title,
                    'current_section' => $se->class?->section,
                ];

                continue;
            }

            $enrolled = (int) ($matched->class_enrollments_count ?? 0);
            $max = (int) ($matched->maximum_slots ?? 0);
            $isFull = $max > 0 && $enrolled >= $max && $se->class_id !== $matched->id;

            $entry = [
                'subject_enrollment_id' => $se->id,
                'subject_code' => $se->subject->code,
                'subject_title' => $se->subject->title,
                'from_section' => $se->class?->section ?? 'Unassigned',
                'to_class_id' => $matched->id,
                'to_section' => $matched->section,
                'enrolled_count' => $enrolled,
                'max_slots' => $max,
            ];

            if ($isFull && ! $forceOverload) {
                $full[] = $entry;
            } else {
                $movable[] = $entry;
            }
        }

        return [
            'transfer_type' => EnrollmentTransferLog::TypeSectionTransfer,
            'target_section' => $cleanTarget,
            'movable' => $movable,
            'full' => $full,
            'unmatched' => $unmatched,
            'movable_count' => count($movable),
            'full_count' => count($full),
            'unmatched_count' => count($unmatched),
        ];
    }

    /**
     * Atomically execute a block section transfer and preserve audit history.
     */
    public function executeSectionTransfer(
        StudentEnrollment $enrollment,
        string $targetSection,
        string $reason,
        User $actor,
        bool $forceOverload = false,
    ): EnrollmentTransferLog {
        if (in_array($enrollment->terminal_outcome, [EnrollmentCancellationService::OUTCOME, 'rejected'], true) || $enrollment->trashed()) {
            throw new RuntimeException('Cannot transfer sections on a cancelled, rejected, or trashed enrollment.');
        }

        $preview = $this->previewSectionTransfer($enrollment, $targetSection, $forceOverload);
        if ($preview['movable_count'] === 0) {
            throw new RuntimeException(
                $preview['full_count'] > 0
                    ? 'All matched classes in Section '.$targetSection.' are at capacity. Enable seat overload override to proceed.'
                    : 'No enrolled subjects have matching classes in Section '.$targetSection.'.',
            );
        }

        $enrollment->loadMissing(['subjectsEnrolled.class', 'studentTuition']);
        $fromSection = $enrollment->subjectsEnrolled
            ->map(fn (SubjectEnrollment $se): ?string => $se->class?->section)
            ->filter()
            ->countBy()
            ->sortDesc()
            ->keys()
            ->first();

        return DB::transaction(function () use ($enrollment, $preview, $fromSection, $targetSection, $reason, $actor, $forceOverload): EnrollmentTransferLog {
            $reassigned = 0;
            $targetClassIds = collect($preview['movable'])->pluck('to_class_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
            $lockedClasses = $targetClassIds->isNotEmpty()
                ? Classes::query()->whereIn('id', $targetClassIds)->lockForUpdate()->get()->keyBy('id')
                : collect();

            foreach ($preview['movable'] as $move) {
                $se = SubjectEnrollment::query()->find($move['subject_enrollment_id']);
                if (! $se instanceof SubjectEnrollment) {
                    continue;
                }

                $oldClassId = $se->class_id;
                $newClassId = (int) $move['to_class_id'];
                $targetClass = $lockedClasses->get($newClassId);

                if ($targetClass instanceof Classes && $targetClass->maximum_slots > 0 && $oldClassId !== $newClassId) {
                    $activeSeats = ClassEnrollment::query()
                        ->where('class_id', $newClassId)
                        ->where('student_id', '!=', $enrollment->student_id)
                        ->where('status', true)
                        ->count();
                    if (! $forceOverload && $activeSeats >= $targetClass->maximum_slots) {
                        throw new RuntimeException("Section {$targetClass->section} filled up before transfer completed.");
                    }
                }

                if ($oldClassId && $oldClassId !== $newClassId) {
                    ClassEnrollment::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('class_id', $oldClassId)
                        ->delete();
                }

                $newClassEnrollment = ClassEnrollment::query()->withTrashed()->firstOrNew([
                    'student_id' => $enrollment->student_id,
                    'class_id' => $newClassId,
                ]);
                if ($newClassEnrollment->trashed()) {
                    $newClassEnrollment->restore();
                }
                $newClassEnrollment->status = true;
                $newClassEnrollment->save();

                $se->forceFill([
                    'class_id' => $newClassId,
                    'section' => (string) $move['to_section'],
                ])->saveQuietly();

                EnrollmentClassWaitlist::query()
                    ->where('student_enrollment_id', $enrollment->id)
                    ->where('subject_enrollment_id', $se->id)
                    ->where('status', EnrollmentClassWaitlist::StatusWaiting)
                    ->update([
                        'status' => EnrollmentClassWaitlist::StatusPromoted,
                        'promoted_class_id' => $newClassId,
                        'promoted_by' => $actor->id,
                        'promoted_at' => now(),
                    ]);

                $reassigned++;
            }

            $tuitionTotal = (float) ($enrollment->studentTuition?->overall_tuition ?? 0);

            return EnrollmentTransferLog::query()->create([
                'student_enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'transfer_type' => EnrollmentTransferLog::TypeSectionTransfer,
                'from_course_id' => $enrollment->course_id,
                'to_course_id' => $enrollment->course_id,
                'from_section' => $fromSection,
                'to_section' => mb_trim($targetSection),
                'credited_subjects_count' => 0,
                'reassigned_classes_count' => $reassigned,
                'unmatched_subjects_count' => $preview['unmatched_count'] + $preview['full_count'],
                'previous_tuition_total' => $tuitionTotal,
                'new_tuition_total' => $tuitionTotal,
                'fee_delta' => 0,
                'details' => [
                    'moved' => $preview['movable'],
                    'unmatched' => $preview['unmatched'],
                    'full' => $preview['full'],
                ],
                'reason' => mb_trim($reason),
                'performed_by' => $actor->id,
            ]);
        });
    }

    /**
     * Preview shifting an enrollment to another program, showing credited overlaps and tuition fee delta.
     *
     * @return array<string, mixed>
     */
    public function previewProgramShift(
        StudentEnrollment $enrollment,
        int $targetCourseId,
        ?int $targetYearLevel = null,
    ): array {
        $enrollment->loadMissing(['course', 'subjectsEnrolled.subject', 'studentTuition', 'additionalFees']);
        $targetCourse = Course::query()->findOrFail($targetCourseId);

        if ((int) $targetCourse->id === (int) $enrollment->course_id) {
            throw new RuntimeException('Select a different target program to perform a program shift.');
        }

        $yearLevel = $targetYearLevel ?? (int) ($enrollment->academic_year ?? 1);
        $targetSubjects = Subject::query()
            ->where('course_id', $targetCourse->id)
            ->where('academic_year', $yearLevel)
            ->where('semester', $enrollment->semester)
            ->orderBy('code')
            ->get();

        $currentEnrolled = $enrollment->subjectsEnrolled;

        $credited = [];
        $added = [];
        $matchedCurrentIds = [];

        foreach ($targetSubjects as $targetSubject) {
            $overlap = $currentEnrolled->first(function (SubjectEnrollment $se) use ($targetSubject): bool {
                $currCode = mb_strtolower(mb_trim((string) ($se->subject?->code ?? '')));
                $currTitle = mb_strtolower(mb_trim((string) ($se->subject?->title ?? '')));
                $tgtCode = mb_strtolower(mb_trim((string) $targetSubject->code));
                $tgtTitle = mb_strtolower(mb_trim((string) $targetSubject->title));

                return ($currCode !== '' && $currCode === $tgtCode) || ($currTitle !== '' && $currTitle === $tgtTitle);
            });

            if ($overlap instanceof SubjectEnrollment) {
                $matchedCurrentIds[] = $overlap->id;
                $credited[] = [
                    'from_subject_enrollment_id' => $overlap->id,
                    'from_code' => $overlap->subject?->code,
                    'to_subject_id' => $targetSubject->id,
                    'to_code' => $targetSubject->code,
                    'title' => $targetSubject->title,
                    'units' => (int) ($targetSubject->lecture ?? 0) + (int) ($targetSubject->laboratory ?? 0),
                ];
            } else {
                $added[] = [
                    'subject_id' => $targetSubject->id,
                    'code' => $targetSubject->code,
                    'title' => $targetSubject->title,
                    'units' => (int) ($targetSubject->lecture ?? 0) + (int) ($targetSubject->laboratory ?? 0),
                ];
            }
        }

        $dropped = $currentEnrolled
            ->filter(fn (SubjectEnrollment $se): bool => ! in_array($se->id, $matchedCurrentIds, true))
            ->map(fn (SubjectEnrollment $se): array => [
                'subject_enrollment_id' => $se->id,
                'code' => $se->subject?->code ?? 'Subject',
                'title' => $se->subject?->title ?? 'Untitled',
                'units' => (int) ($se->enrolled_lecture_units ?? $se->subject?->lecture ?? 0) + (int) ($se->enrolled_laboratory_units ?? $se->subject?->laboratory ?? 0),
            ])
            ->values()
            ->all();

        $lecRate = (float) ($targetCourse->lec_per_unit ?? 0);
        $labRate = (float) ($targetCourse->lab_per_unit ?? 0);
        $newGrossTuition = round((float) $targetSubjects->sum(
            fn (Subject $s): float => ((int) ($s->lecture ?? 0) * $lecRate) + ((int) ($s->laboratory ?? 0) * $labRate),
        ), 2);
        $discountPct = (float) ($enrollment->studentTuition?->discount ?? 0);
        $discounted = round($newGrossTuition * max(0.0, 1.0 - ($discountPct / 100.0)), 2);
        $targetMisc = (float) ($targetCourse->getMiscellaneousFee() ?? $enrollment->studentTuition?->total_miscelaneous_fees ?? EnrollmentFeeCalculator::Defaults['miscellaneous_fee_fallback']);
        $additionalTotal = round((float) $enrollment->additionalFees->sum('amount'), 2);

        $previousTotal = (float) ($enrollment->studentTuition?->overall_tuition ?? 0);
        $newTotal = round($discounted + $targetMisc + $additionalTotal, 2);

        return [
            'transfer_type' => EnrollmentTransferLog::TypeProgramShift,
            'from_course' => [
                'id' => $enrollment->course?->id,
                'code' => $enrollment->course?->code,
                'title' => $enrollment->course?->title,
            ],
            'to_course' => [
                'id' => $targetCourse->id,
                'code' => $targetCourse->code,
                'title' => $targetCourse->title,
            ],
            'target_year_level' => $yearLevel,
            'credited_subjects' => $credited,
            'added_subjects' => $added,
            'dropped_subjects' => $dropped,
            'previous_tuition_total' => $previousTotal,
            'new_tuition_total' => $newTotal,
            'fee_delta' => round($newTotal - $previousTotal, 2),
        ];
    }

    /**
     * Atomically shift a student enrollment to a new program, crediting overlaps and reconciling fees.
     */
    public function executeProgramShift(
        StudentEnrollment $enrollment,
        int $targetCourseId,
        string $reason,
        User $actor,
        ?int $targetYearLevel = null,
        ?string $targetSection = null,
        bool $reconcileCurriculum = true,
        bool $forceOverload = false,
        bool $confirmGradeLoss = false,
    ): EnrollmentTransferLog {
        if (in_array($enrollment->terminal_outcome, [EnrollmentCancellationService::OUTCOME, 'rejected'], true) || $enrollment->trashed()) {
            throw new RuntimeException('Cannot shift programs on a cancelled, rejected, or trashed enrollment.');
        }

        $preview = $this->previewProgramShift($enrollment, $targetCourseId, $targetYearLevel);
        $targetCourse = Course::query()->findOrFail($targetCourseId);
        $yearLevel = (int) $preview['target_year_level'];

        return DB::transaction(function () use (
            $enrollment,
            $targetCourse,
            $preview,
            $yearLevel,
            $targetSection,
            $reconcileCurriculum,
            $forceOverload,
            $confirmGradeLoss,
            $reason,
            $actor,
        ): EnrollmentTransferLog {
            $enrollment->load(['student', 'subjectsEnrolled', 'studentTuition']);
            $fromCourseId = $enrollment->course_id;
            $previousTotal = (float) ($enrollment->studentTuition?->overall_tuition ?? 0);

            if ($reconcileCurriculum && ! $confirmGradeLoss) {
                foreach ($preview['dropped_subjects'] as $dropped) {
                    $se = SubjectEnrollment::query()->find($dropped['subject_enrollment_id']);
                    if (! $se instanceof SubjectEnrollment) {
                        continue;
                    }
                    $hasGrade = $se->grade !== null
                        || ($se->class_id !== null && ClassEnrollment::query()
                            ->where('student_id', $enrollment->student_id)
                            ->where('class_id', $se->class_id)
                            ->where(function ($query): void {
                                $query->whereNotNull('prelim_grade')
                                    ->orWhereNotNull('midterm_grade')
                                    ->orWhereNotNull('finals_grade')
                                    ->orWhereNotNull('total_average');
                            })
                            ->exists());
                    if ($hasGrade) {
                        throw new RuntimeException('Cannot drop graded subjects during a program shift without explicit grade-loss confirmation.');
                    }
                }
            }

            // Update enrollment & student course
            $enrollment->forceFill([
                'course_id' => $targetCourse->id,
                'academic_year' => $yearLevel,
            ])->save();

            if ($enrollment->student) {
                $enrollment->student->forceFill([
                    'course_id' => $targetCourse->id,
                    'academic_year' => $yearLevel,
                ])->save();
            }

            $reassignedClasses = 0;

            if ($reconcileCurriculum) {
                $lecRate = (float) ($targetCourse->lec_per_unit ?? 0);
                $labRate = (float) ($targetCourse->lab_per_unit ?? 0);

                // 1. Drop non-overlapping subject enrollments & class seats
                foreach ($preview['dropped_subjects'] as $dropped) {
                    $se = SubjectEnrollment::query()->find($dropped['subject_enrollment_id']);
                    if (! $se instanceof SubjectEnrollment) {
                        continue;
                    }
                    $oldClassId = $se->class_id;
                    $se->delete();
                    if ($oldClassId) {
                        ClassEnrollment::query()
                            ->where('student_id', $enrollment->student_id)
                            ->where('class_id', $oldClassId)
                            ->delete();
                    }
                }

                // 2. Credit/map overlapping subjects to target curriculum subject & fee rates
                foreach ($preview['credited_subjects'] as $credited) {
                    $se = SubjectEnrollment::query()->find($credited['from_subject_enrollment_id']);
                    $tgtSubject = Subject::query()->find($credited['to_subject_id']);
                    if (! $se instanceof SubjectEnrollment || ! $tgtSubject instanceof Subject) {
                        continue;
                    }
                    $lecU = (int) ($tgtSubject->lecture ?? 0);
                    $labU = (int) ($tgtSubject->laboratory ?? 0);
                    $se->forceFill([
                        'subject_id' => $tgtSubject->id,
                        'academic_year' => $yearLevel,
                        'enrolled_lecture_units' => $lecU,
                        'enrolled_laboratory_units' => $labU,
                        'lecture_fee' => round($lecU * $lecRate, 2),
                        'laboratory_fee' => round($labU * $labRate, 2),
                    ])->saveQuietly();
                }

                // 3. Add missing target curriculum subjects & assign classes if open
                $termClasses = Classes::query()
                    ->where('school_year', $enrollment->school_year)
                    ->where('semester', $enrollment->semester)
                    ->withCount(['class_enrollments' => fn ($query) => $query->where('status', true)])
                    ->lockForUpdate()
                    ->get();

                foreach ($preview['added_subjects'] as $added) {
                    $tgtSubject = Subject::query()->find($added['subject_id']);
                    if (! $tgtSubject instanceof Subject) {
                        continue;
                    }
                    $lecU = (int) ($tgtSubject->lecture ?? 0);
                    $labU = (int) ($tgtSubject->laboratory ?? 0);

                    $matchedClass = $this->findClassForSubject($termClasses, $tgtSubject, $targetSection, $forceOverload);

                    $enrollment->subjectsEnrolled()->create([
                        'subject_id' => $tgtSubject->id,
                        'class_id' => $matchedClass?->id,
                        'section' => $matchedClass?->section,
                        'student_id' => $enrollment->student_id,
                        'academic_year' => $yearLevel,
                        'school_year' => $enrollment->school_year,
                        'semester' => $enrollment->semester,
                        'enrolled_lecture_units' => $lecU,
                        'enrolled_laboratory_units' => $labU,
                        'lecture_fee' => round($lecU * $lecRate, 2),
                        'laboratory_fee' => round($labU * $labRate, 2),
                        'is_modular' => false,
                        'exclude_from_tuition' => false,
                    ]);

                    if ($matchedClass instanceof Classes) {
                        $ce = ClassEnrollment::query()->withTrashed()->firstOrNew([
                            'student_id' => $enrollment->student_id,
                            'class_id' => $matchedClass->id,
                        ]);
                        if ($ce->trashed()) {
                            $ce->restore();
                        }
                        $ce->status = true;
                        $ce->save();
                        $reassignedClasses++;
                    }
                }

                if ($enrollment->studentTuition instanceof StudentTuition) {
                    $targetMisc = (float) ($targetCourse->getMiscellaneousFee() ?? $enrollment->studentTuition->total_miscelaneous_fees ?? 0);
                    $enrollment->studentTuition->forceFill([
                        'academic_year' => $yearLevel,
                        'total_miscelaneous_fees' => $targetMisc,
                    ])->save();
                }

                $newTotal = $this->loadChangeService->recalculateEnrollmentTuition($enrollment);
            } else {
                $newTotal = $previousTotal;
            }

            return EnrollmentTransferLog::query()->create([
                'student_enrollment_id' => $enrollment->id,
                'student_id' => $enrollment->student_id,
                'transfer_type' => EnrollmentTransferLog::TypeProgramShift,
                'from_course_id' => $fromCourseId,
                'to_course_id' => $targetCourse->id,
                'from_section' => null,
                'to_section' => $targetSection,
                'credited_subjects_count' => count($preview['credited_subjects']),
                'reassigned_classes_count' => $reassignedClasses,
                'unmatched_subjects_count' => max(0, count($preview['added_subjects']) - $reassignedClasses),
                'previous_tuition_total' => $previousTotal,
                'new_tuition_total' => $newTotal,
                'fee_delta' => round($newTotal - $previousTotal, 2),
                'details' => [
                    'credited' => $preview['credited_subjects'],
                    'added' => $preview['added_subjects'],
                    'dropped' => $preview['dropped_subjects'],
                ],
                'reason' => mb_trim($reason),
                'performed_by' => $actor->id,
            ]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function serialize(EnrollmentTransferLog $log): array
    {
        return [
            'id' => $log->id,
            'transfer_type' => $log->transfer_type,
            'from_course' => $log->fromCourse?->code,
            'to_course' => $log->toCourse?->code,
            'from_section' => $log->from_section,
            'to_section' => $log->to_section,
            'credited_subjects_count' => (int) $log->credited_subjects_count,
            'reassigned_classes_count' => (int) $log->reassigned_classes_count,
            'unmatched_subjects_count' => (int) $log->unmatched_subjects_count,
            'previous_tuition_total' => round((float) $log->previous_tuition_total, 2),
            'new_tuition_total' => round((float) $log->new_tuition_total, 2),
            'fee_delta' => round((float) $log->fee_delta, 2),
            'reason' => $log->reason,
            'performed_by' => $log->performedBy?->name ?? 'Administrator',
            'created_at' => $log->created_at?->toIso8601String(),
        ];
    }

    /**
     * @param  Collection<int, Classes>  $classes
     */
    private function findClassForSubject(
        Collection $classes,
        Subject $subject,
        ?string $preferredSection = null,
        bool $forceOverload = false,
    ): ?Classes {
        $needle = mb_strtolower(mb_trim((string) $subject->code));

        $matches = $classes->filter(function (Classes $class) use ($subject, $needle): bool {
            $ids = array_map(intval(...), (array) ($class->subject_ids ?? []));
            if (in_array((int) $subject->id, $ids, true)) {
                return true;
            }

            $codes = array_map(
                fn (string $c): string => mb_strtolower(mb_trim($c)),
                explode(',', (string) $class->subject_code),
            );

            return in_array($needle, $codes, true);
        });

        if ($preferredSection !== null && mb_trim($preferredSection) !== '') {
            $preferred = $matches->first(function (Classes $class) use ($preferredSection, $forceOverload): bool {
                $sameSection = mb_strtolower(mb_trim((string) $class->section)) === mb_strtolower(mb_trim($preferredSection));
                $max = (int) ($class->maximum_slots ?? 0);
                $hasSeat = $forceOverload || $max <= 0 || (int) ($class->class_enrollments_count ?? 0) < $max;

                return $sameSection && $hasSeat;
            });
            if ($preferred instanceof Classes) {
                return $preferred;
            }
        }

        return $matches
            ->filter(function (Classes $class) use ($forceOverload): bool {
                $max = (int) ($class->maximum_slots ?? 0);

                return $forceOverload || $max <= 0 || (int) ($class->class_enrollments_count ?? 0) < $max;
            })
            ->sortBy('class_enrollments_count')
            ->first();
    }
}
