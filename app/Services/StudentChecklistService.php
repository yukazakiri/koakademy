<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;

final class StudentChecklistService
{
    public function __construct(
        private ?GradingSystemService $gradingSystem = null,
        private ?GradeEvaluationService $gradeEvaluator = null,
    ) {
        $this->gradingSystem ??= app(GradingSystemService::class);
        $this->gradeEvaluator ??= app(GradeEvaluationService::class);
    }

    /**
     * Analyze a student's complete academic checklist and evaluate grades against curriculum requirements.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function analyze(Student $student, array $options = []): array
    {
        $school = $student->school ?? app(TenantContext::class)->getCurrentSchool();
        $gradingConfig = $this->gradingSystem->getConfig($school);

        $course = $student->Course;
        if (! $course instanceof Course) {
            return [
                'error' => true,
                'message' => 'Student has no degree program/course assigned.',
                'student' => [
                    'id' => $student->id,
                    'student_number' => (string) $student->student_id,
                    'name' => $student->full_name,
                ],
            ];
        }

        $filterYear = isset($options['year_level']) ? (int) $options['year_level'] : null;
        $filterSemester = isset($options['semester']) ? (int) $options['semester'] : null;
        $statusFilter = isset($options['status']) ? mb_strtolower((string) $options['status']) : null;

        // 1. Retrieve all curriculum subjects for the student's degree program
        $subjectsQuery = Subject::query()
            ->where('course_id', $course->id)
            ->orderBy('academic_year')
            ->orderBy('semester')
            ->orderBy('code');

        if ($filterYear !== null) {
            $subjectsQuery->where('academic_year', $filterYear);
        }
        if ($filterSemester !== null) {
            $subjectsQuery->where('semester', $filterSemester);
        }

        $allCurriculumSubjects = $subjectsQuery->get();

        // 2. Retrieve student's subject enrollments
        $subjectEnrollments = SubjectEnrollment::query()
            ->where('student_id', $student->id)
            ->with(['subject', 'class'])
            ->get();

        $enrolledBySubjectId = $subjectEnrollments
            ->filter(fn (SubjectEnrollment $se): bool => $se->classification !== SubjectEnrolledEnum::NON_CREDITED->value)
            ->groupBy('subject_id');

        $nonCredited = $subjectEnrollments
            ->filter(fn (SubjectEnrollment $se): bool => $se->classification === SubjectEnrolledEnum::NON_CREDITED->value);

        // 3. Process checklist subjects
        $totalCurriculumUnits = 0;
        $completedUnits = 0;
        $inProgressUnits = 0;
        $failedUnits = 0;
        $remainingUnits = 0;

        $totalCurriculumSubjects = 0;
        $completedSubjectsCount = 0;
        $inProgressSubjectsCount = 0;
        $failedSubjectsCount = 0;
        $deficientSubjectsCount = 0;

        $gwaWeightedSum = 0.0;
        $gwaUnitsDivisor = 0.0;

        $checklistItems = [];
        $deficiencies = [];
        $failedRetakesNeeded = [];
        $prerequisiteBlockers = [];

        // Build quick lookup for passed subjects
        $passedSubjectCodes = [];
        foreach ($subjectEnrollments as $se) {
            if ($this->isSubjectPassed($se, $gradingConfig)) {
                $code = $se->subject?->code ?? $se->external_subject_code;
                if ($code) {
                    $passedSubjectCodes[mb_strtoupper(mb_trim($code))] = true;
                }
            }
        }

        foreach ($allCurriculumSubjects as $subject) {
            $totalCurriculumSubjects++;
            $units = (int) $subject->units;
            $totalCurriculumUnits += $units;

            $enrollments = $enrolledBySubjectId->get($subject->id, collect());
            $bestEnrollment = $this->resolveBestEnrollment($enrollments, $gradingConfig);

            $status = 'Not Completed';
            $gradeDisplay = null;
            $isCompleted = false;
            $isInProgress = false;
            $isFailed = false;

            if ($bestEnrollment instanceof SubjectEnrollment) {
                if ($bestEnrollment->grade !== null || $bestEnrollment->grade_symbol !== null) {
                    $outcome = $bestEnrollment->grade_outcome;
                    $zeroIsDropped = (bool) ($gradingConfig['zero_is_dropped'] ?? false);
                    $isDropped = in_array($outcome, ['withdrawn', 'dropped'], true)
                        || ($zeroIsDropped && (float) $bestEnrollment->grade === 0.0);

                    if ($isDropped) {
                        $status = 'Dropped';
                    } elseif ($outcome === 'pass' || $this->gradingSystem->isPassingGrade($bestEnrollment->grade, $gradingConfig)) {
                        $status = 'Completed';
                        $isCompleted = true;
                    } else {
                        $status = 'Failed';
                        $isFailed = true;
                    }

                    $gradeDisplay = $bestEnrollment->grade_symbol ?? (is_numeric($bestEnrollment->grade) ? (string) $bestEnrollment->grade : null);

                    // Contribute to GWA if numeric grade exists
                    if (is_numeric($bestEnrollment->grade) && ($isCompleted || ($isFailed && ($gradingConfig['include_failed_in_gwa'] ?? true)))) {
                        $numGrade = (float) $bestEnrollment->grade;
                        $gwaWeightedSum += $numGrade * $units;
                        $gwaUnitsDivisor += $units;
                    }
                } else {
                    $status = 'In Progress';
                    $isInProgress = true;
                }
            }

            if ($isCompleted) {
                $completedUnits += $units;
                $completedSubjectsCount++;
            } elseif ($isInProgress) {
                $inProgressUnits += $units;
                $inProgressSubjectsCount++;
            } elseif ($isFailed) {
                $failedUnits += $units;
                $failedSubjectsCount++;
                $remainingUnits += $units;
                $failedRetakesNeeded[] = [
                    'code' => $subject->code,
                    'title' => $subject->title,
                    'units' => $units,
                    'last_grade' => $gradeDisplay,
                    'term' => $bestEnrollment?->school_year.' Sem '.$bestEnrollment?->semester,
                ];
            } else {
                $deficientSubjectsCount++;
                $remainingUnits += $units;
                $deficiencies[] = [
                    'code' => $subject->code,
                    'title' => $subject->title,
                    'units' => $units,
                    'year_level' => $subject->academic_year,
                    'semester' => $subject->semester,
                    'prerequisites' => $subject->pre_riquisite,
                ];
            }

            // Check if student is blocked by unmet prerequisite
            $prereqString = (string) $subject->pre_riquisite;
            $prereqClean = mb_strtoupper(mb_trim($prereqString));
            $prereqSatisfied = true;
            $missingPrereq = null;

            if ($prereqClean !== '' && $prereqClean !== 'NONE' && $prereqClean !== 'N/A') {
                if (! isset($passedSubjectCodes[$prereqClean])) {
                    $prereqSatisfied = false;
                    $missingPrereq = $prereqClean;
                    if (! $isCompleted && ! $isInProgress) {
                        $prerequisiteBlockers[] = [
                            'subject_code' => $subject->code,
                            'subject_title' => $subject->title,
                            'unmet_prerequisite' => $missingPrereq,
                            'scheduled_year' => $subject->academic_year,
                            'scheduled_semester' => $subject->semester,
                        ];
                    }
                }
            }

            // Filter check
            // Filter check: treat 'all' or empty string as unfiltered
            if ($statusFilter !== null && $statusFilter !== '' && $statusFilter !== 'all') {
                $normalizedStatus = mb_strtolower(str_replace(' ', '_', $status));
                if ($statusFilter === 'deficient' && ! in_array($status, ['Not Completed', 'Failed', 'Dropped'], true)) {
                    continue;
                }
                if ($statusFilter !== 'deficient' && $statusFilter !== $normalizedStatus) {
                    continue;
                }
            }

            $checklistItems[] = [
                'id' => $subject->id,
                'code' => $subject->code,
                'title' => $subject->title,
                'units' => $units,
                'year_level' => $subject->academic_year,
                'semester' => $subject->semester,
                'prerequisite' => $subject->pre_riquisite,
                'prerequisite_satisfied' => $prereqSatisfied,
                'missing_prerequisite' => $missingPrereq,
                'status' => $status,
                'grade' => $gradeDisplay,
                'grade_outcome' => $bestEnrollment?->grade_outcome,
                'is_completed' => $isCompleted,
                'is_enrolled' => $isInProgress,
                'term_taken' => $bestEnrollment ? ($bestEnrollment->school_year.' Sem '.$bestEnrollment->semester) : null,
                'attempts_count' => $enrollments->count(),
            ];
        }

        $cumulativeGwa = $gwaUnitsDivisor > 0
            ? round($gwaWeightedSum / $gwaUnitsDivisor, (int) ($gradingConfig['decimal_places'] ?? 2))
            : null;

        $progressPercentage = $totalCurriculumUnits > 0
            ? round(($completedUnits / $totalCurriculumUnits) * 100, 1)
            : 0.0;

        // Determine Academic Standing
        $standing = 'Good Standing';
        if ($failedSubjectsCount >= 3) {
            $standing = 'Academic Probation';
        } elseif ($failedSubjectsCount >= 1) {
            $standing = 'Academic Warning';
        } elseif ($progressPercentage >= 85.0 && count($deficiencies) <= 4) {
            $standing = 'Graduating Candidate';
        } elseif ($cumulativeGwa !== null) {
            $isPoint = ($gradingConfig['direction'] ?? 'higher_is_better') === 'lower_is_better';
            if ($isPoint && $cumulativeGwa <= 1.45 && $failedSubjectsCount === 0) {
                $standing = "Dean's Honor List Candidate";
            } elseif (! $isPoint && $cumulativeGwa >= 90.0 && $failedSubjectsCount === 0) {
                $standing = "Dean's Honor List Candidate";
            }
        }

        return [
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'year_level' => $student->academic_year,
                'status' => $student->status,
                'program' => [
                    'id' => $course->id,
                    'code' => $course->code,
                    'title' => $course->title,
                    'total_catalog_units' => $course->units,
                ],
            ],
            'academic_summary' => [
                'progress_percentage' => $progressPercentage,
                'cumulative_gwa' => $cumulativeGwa,
                'academic_standing' => $standing,
                'total_curriculum_units' => $totalCurriculumUnits,
                'completed_units' => $completedUnits,
                'in_progress_units' => $inProgressUnits,
                'remaining_units' => $remainingUnits,
                'failed_units' => $failedUnits,
                'total_curriculum_subjects' => $totalCurriculumSubjects,
                'completed_subjects_count' => $completedSubjectsCount,
                'in_progress_subjects_count' => $inProgressSubjectsCount,
                'deficient_subjects_count' => $deficientSubjectsCount,
                'failed_subjects_count' => $failedSubjectsCount,
                'non_credited_transferee_count' => $nonCredited->count(),
            ],
            'deficiencies' => $deficiencies,
            'failed_retakes_needed' => $failedRetakesNeeded,
            'prerequisite_blockers' => $prerequisiteBlockers,
            'checklist_items_count' => count($checklistItems),
            'checklist' => $checklistItems,
        ];
    }

    private function resolveBestEnrollment(\Illuminate\Support\Collection $enrollments, array $gradingConfig): ?SubjectEnrollment
    {
        if ($enrollments->isEmpty()) {
            return null;
        }

        $strategy = $gradingConfig['retake_strategy'] ?? 'latest';
        $graded = $enrollments->filter(fn ($e): bool => $e->grade !== null || $e->grade_symbol !== null);

        if ($strategy === 'highest' && $graded->isNotEmpty()) {
            $isLowerBetter = ($gradingConfig['direction'] ?? 'higher_is_better') === 'lower_is_better';

            return $isLowerBetter
                ? $graded->sortBy(fn ($e): float => is_numeric($e->grade) ? (float) $e->grade : INF)->first()
                : $graded->sortByDesc(fn ($e): float => is_numeric($e->grade) ? (float) $e->grade : -INF)->first();
        }

        if ($strategy === 'first') {
            return $graded->first() ?? $enrollments->first();
        }

        // Default 'latest'
        return $enrollments->sortByDesc('id')->firstWhere(fn ($e): bool => $e->grade !== null || $e->grade_symbol !== null)
            ?? $enrollments->sortByDesc('id')->first();
    }

    private function isSubjectPassed(SubjectEnrollment $se, array $gradingConfig): bool
    {
        if ($se->grade_outcome === 'pass' || $se->grade_outcome === 'passed') {
            return true;
        }

        if ($se->grade !== null && $this->gradingSystem->isPassingGrade($se->grade, $gradingConfig)) {
            return true;
        }

        return false;
    }
}
