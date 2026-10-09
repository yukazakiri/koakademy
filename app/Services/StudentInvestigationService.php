<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentEnrollment;
use App\Models\StudentTransaction;
use App\Models\StudentTuition;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use Illuminate\Support\Facades\DB;

final class StudentInvestigationService
{
    public function __construct(
        private ?EnrollmentBillingService $billingService = null,
        private ?GeneralSettingsService $settingsService = null,
        private ?StudentChecklistService $checklistService = null,
    ) {
        $this->billingService ??= app(EnrollmentBillingService::class);
        $this->settingsService ??= app(GeneralSettingsService::class);
        $this->checklistService ??= app(StudentChecklistService::class);
    }

    /**
     * Investigate student enrollment records for mistakes, prerequisite violations, and inconsistencies.
     *
     * @return array<string, mixed>
     */
    public function investigateEnrollment(Student $student, ?string $schoolYear = null, ?int $semester = null): array
    {
        $findings = [];

        $enrollmentsQuery = StudentEnrollment::withTrashed()
            ->where(function ($q) use ($student): void {
                $q->where('student_id', (string) $student->id)
                    ->orWhere('student_id', (string) $student->student_id);
            });

        if (filled($schoolYear)) {
            $enrollmentsQuery->where('school_year', $schoolYear);
        }
        if ($semester !== null) {
            $enrollmentsQuery->where('semester', $semester);
        }

        $enrollments = $enrollmentsQuery->get();

        // 1. Check for student-level status mismatches
        if (in_array($student->status, ['dropped', 'cancelled', 'withdrawn', 'on_leave'], true)) {
            $activeSubjectCount = SubjectEnrollment::query()->where('student_id', $student->id)->count();
            $activeClassCount = ClassEnrollment::query()->where('student_id', $student->id)->count();
            if ($activeSubjectCount > 0 || $activeClassCount > 0) {
                $findings[] = [
                    'category' => 'status_mismatch',
                    'severity' => 'critical',
                    'record_type' => 'student',
                    'record_id' => $student->id,
                    'issue' => "Student profile is marked '{$student->status}', but still has {$activeSubjectCount} subject enrollments and {$activeClassCount} class enrollments assigned.",
                    'evidence' => [
                        'student_status' => $student->status,
                        'subject_enrollments_count' => $activeSubjectCount,
                        'class_enrollments_count' => $activeClassCount,
                    ],
                    'suggested_action' => 'Drop or archive remaining subject and class enrollments, or restore student status.',
                ];
            }
        }

        // 2. Analyze each enrollment record
        foreach ($enrollments as $enrollment) {
            $subjects = SubjectEnrollment::query()
                ->where('enrollment_id', $enrollment->id)
                ->with(['subject', 'class'])
                ->get();

            // A. Check for soft-deleted enrollment with active subjects
            if ($enrollment->trashed() && $subjects->isNotEmpty()) {
                $findings[] = [
                    'category' => 'orphaned_record',
                    'severity' => 'critical',
                    'record_type' => 'student_enrollment',
                    'record_id' => $enrollment->id,
                    'issue' => "Enrollment #{$enrollment->id} is soft-deleted, but has {$subjects->count()} subject enrollment records linked to it.",
                    'evidence' => ['enrolled_subjects' => $subjects->pluck('id')->all()],
                    'suggested_action' => 'Delete or reassign the linked subject enrollments, or restore enrollment #'.$enrollment->id.'.',
                ];
            }

            // B. Check for active enrollment with zero subjects
            if (! $enrollment->trashed() && $enrollment->status === 'enrolled' && $subjects->isEmpty()) {
                $findings[] = [
                    'category' => 'unit_load',
                    'severity' => 'warning',
                    'record_type' => 'student_enrollment',
                    'record_id' => $enrollment->id,
                    'issue' => "Enrollment #{$enrollment->id} ({$enrollment->school_year} Sem {$enrollment->semester}) is marked 'enrolled' but has 0 subjects registered.",
                    'evidence' => ['status' => $enrollment->status, 'subjects_count' => 0],
                    'suggested_action' => 'Add subjects via ManageSubjectEnrollmentTool or update status to pending/cancelled.',
                ];
            }

            // C. Check total unit load
            $totalUnits = 0;
            foreach ($subjects as $se) {
                $subjUnits = $se->subject?->units ?? 0;
                $totalUnits += (int) $subjUnits;
            }
            if ($totalUnits > 26 && ! $enrollment->trashed()) {
                $findings[] = [
                    'category' => 'unit_overload',
                    'severity' => 'warning',
                    'record_type' => 'student_enrollment',
                    'record_id' => $enrollment->id,
                    'issue' => "Total enrolled units ({$totalUnits} units) exceed standard maximum term load (26 units).",
                    'evidence' => ['enrolled_units' => $totalUnits, 'threshold' => 26],
                    'suggested_action' => 'Verify dean overload permit or adjust subject enrollments.',
                ];
            }

            // D. Check for duplicate subjects within same enrollment
            $seenSubjectCodes = [];
            foreach ($subjects as $se) {
                $code = $se->subject?->code ?? $se->external_subject_code;
                if ($code) {
                    if (isset($seenSubjectCodes[$code])) {
                        $findings[] = [
                            'category' => 'duplicate_enrollment',
                            'severity' => 'critical',
                            'record_type' => 'subject_enrollment',
                            'record_id' => $se->id,
                            'issue' => "Duplicate enrollment detected for subject {$code} in the same term ({$enrollment->school_year} Sem {$enrollment->semester}).",
                            'evidence' => ['subject_code' => $code, 'duplicate_ids' => [$seenSubjectCodes[$code], $se->id]],
                            'suggested_action' => "Drop duplicate subject enrollment #{$se->id}.",
                        ];
                    } else {
                        $seenSubjectCodes[$code] = $se->id;
                    }
                }
            }

            // E. Prerequisite checks
            foreach ($subjects as $se) {
                $subject = $se->subject;
                if ($subject instanceof Subject && filled($subject->pre_riquisite)) {
                    $prereqCode = mb_strtoupper(mb_trim((string) $subject->pre_riquisite));
                    if ($prereqCode !== 'NONE' && $prereqCode !== 'N/A' && $prereqCode !== '') {
                        $prereqPassed = SubjectEnrollment::query()
                            ->where('student_id', $student->id)
                            ->where('id', '!=', $se->id)
                            ->where(function ($q) use ($prereqCode): void {
                                $q->whereHas('subject', fn ($subQuery) => $subQuery->where('code', $prereqCode))
                                    ->orWhere('external_subject_code', $prereqCode);
                            })
                            ->where(function ($q): void {
                                $q->where('grade_outcome', 'passed')
                                    ->orWhere(function ($q2): void {
                                        $q2->whereNotNull('grade')->where('grade', '<=', 3.0)->where('grade', '>', 0);
                                    });
                            })
                            ->exists();

                        if (! $prereqPassed) {
                            $findings[] = [
                                'category' => 'prerequisite_violation',
                                'severity' => 'critical',
                                'record_type' => 'subject_enrollment',
                                'record_id' => $se->id,
                                'issue' => "Student enrolled in {$subject->code} without passing required prerequisite {$prereqCode}.",
                                'evidence' => [
                                    'subject' => $subject->code,
                                    'required_prerequisite' => $prereqCode,
                                    'term' => "{$enrollment->school_year} Sem {$enrollment->semester}",
                                ],
                                'suggested_action' => "Drop {$subject->code} or verify crediting of {$prereqCode}.",
                            ];
                        }
                    }
                }
            }

            // F. Schedule clashes between assigned class sections
            $enrolledClasses = $subjects->filter(fn ($s) => $s->class instanceof Classes)->map(fn ($s) => $s->class);
            $classesArray = $enrolledClasses->values()->all();
            for ($i = 0; $i < count($classesArray); $i++) {
                for ($j = $i + 1; $j < count($classesArray); $j++) {
                    $c1 = $classesArray[$i];
                    $c2 = $classesArray[$j];

                    // Check day and time overlap
                    $overlap = $this->classesOverlap($c1, $c2);
                    if ($overlap) {
                        $findings[] = [
                            'category' => 'schedule_clash',
                            'severity' => 'critical',
                            'record_type' => 'classes',
                            'record_id' => $c1->id,
                            'issue' => "Schedule conflict detected between class #{$c1->id} ({$c1->subject_code} Sec {$c1->section}) and class #{$c2->id} ({$c2->subject_code} Sec {$c2->section}).",
                            'evidence' => [
                                'class_1' => "{$c1->subject_code} ({$c1->day} {$c1->start_time}-{$c1->end_time})",
                                'class_2' => "{$c2->subject_code} ({$c2->day} {$c2->start_time}-{$c2->end_time})",
                            ],
                            'suggested_action' => 'Transfer one subject to an alternate non-conflicting section.',
                        ];
                    }
                }
            }
        }

        // 3. Check for orphaned subject enrollments (subject enrollment exists without student_enrollment)
        $orphanedSubjects = SubjectEnrollment::query()
            ->where('student_id', $student->id)
            ->where(function ($q): void {
                $q->whereNull('enrollment_id')
                    ->orWhere('enrollment_id', 0)
                    ->orWhereNotExists(function ($subQ): void {
                        $subQ->select(DB::raw(1))
                            ->from('student_enrollment')
                            ->whereColumn('student_enrollment.id', 'subject_enrollments.enrollment_id');
                    });
            })
            ->get();

        foreach ($orphanedSubjects as $orphaned) {
            $findings[] = [
                'category' => 'orphaned_record',
                'severity' => 'critical',
                'record_type' => 'subject_enrollment',
                'record_id' => $orphaned->id,
                'issue' => "Subject enrollment #{$orphaned->id} has no valid parent student enrollment record.",
                'evidence' => ['enrollment_id' => $orphaned->enrollment_id],
                'suggested_action' => "Link to an active enrollment record or delete orphaned subject enrollment #{$orphaned->id}.",
            ];
        }

        // 4. Clearance holds check for active student
        $currentSy = (string) ($schoolYear ?: $this->settingsService->getCurrentSchoolYearString());
        $currentSem = (int) ($semester ?: $this->settingsService->getCurrentSemester());

        $clearance = StudentClearance::query()
            ->where('student_id', $student->id)
            ->where('academic_year', $currentSy)
            ->where('semester', $currentSem)
            ->first();

        if ($student->status === 'enrolled' && $clearance instanceof StudentClearance && ! $clearance->is_cleared) {
            $findings[] = [
                'category' => 'clearance_hold',
                'severity' => 'warning',
                'record_type' => 'student_clearance',
                'record_id' => $clearance->id,
                'issue' => "Student is marked enrolled for {$currentSy} Sem {$currentSem}, but has an unresolved clearance hold ({$clearance->remarks}).",
                'evidence' => ['is_cleared' => false, 'remarks' => $clearance->remarks],
                'suggested_action' => 'Resolve clearance hold with the registrar/department.',
            ];
        }

        $criticalCount = count(array_filter($findings, fn ($f) => $f['severity'] === 'critical'));
        $warningCount = count(array_filter($findings, fn ($f) => $f['severity'] === 'warning'));

        return [
            'investigation_type' => 'enrollment_records_audit',
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
                'status' => $student->status,
                'program' => $student->Course?->code ?? 'N/A',
            ],
            'scope' => [
                'school_year' => $schoolYear ?: 'All periods',
                'semester' => $semester ?: 'All semesters',
                'total_enrollments_audited' => $enrollments->count(),
            ],
            'issues_detected' => count($findings),
            'critical_issues' => $criticalCount,
            'warnings' => $warningCount,
            'status' => count($findings) === 0 ? 'clean' : ($criticalCount > 0 ? 'critical_attention_required' : 'warnings_found'),
            'findings' => $findings,
            'summary' => count($findings) === 0
                ? 'No enrollment discrepancies or policy violations found.'
                : sprintf('Found %d issue(s) (%d critical, %d warning) in enrollment records.', count($findings), $criticalCount, $warningCount),
        ];
    }

    /**
     * Investigate student tuition assessments, transactions, and balance calculations for mistakes.
     *
     * @return array<string, mixed>
     */
    public function investigateFinances(Student $student, ?string $schoolYear = null, ?int $semester = null): array
    {
        $findings = [];
        $totalVariance = 0.0;

        $tuitionsQuery = StudentTuition::withTrashed()
            ->where(function ($q) use ($student): void {
                $q->where('student_id', $student->id)
                    ->orWhere('student_id', $student->student_id);
            });

        if (filled($schoolYear)) {
            $tuitionsQuery->where('school_year', $schoolYear);
        }
        if ($semester !== null) {
            $tuitionsQuery->where('semester', $semester);
        }

        $tuitions = $tuitionsQuery->with(['enrollment', 'installments'])->get();

        foreach ($tuitions as $tuition) {
            $enrollment = $tuition->enrollment;

            // 1. Balance math discrepancy check: overall_tuition - totalPaid != total_balance
            $actualPaid = $this->billingService->totalPaid($tuition);
            $expectedBalance = round(max(0.0, (float) $tuition->overall_tuition - $actualPaid), 2);
            $storedBalance = round((float) $tuition->total_balance, 2);

            $balanceDiff = round(abs($expectedBalance - $storedBalance), 2);
            if ($balanceDiff > 0.05) {
                $totalVariance += $balanceDiff;
                $findings[] = [
                    'category' => 'balance_desynchronization',
                    'severity' => 'critical',
                    'record_type' => 'student_tuition',
                    'record_id' => $tuition->id,
                    'term' => "{$tuition->school_year} Sem {$tuition->semester}",
                    'issue' => "Stored balance (₱{$storedBalance}) does not match calculated balance (₱{$expectedBalance} = ₱{$tuition->overall_tuition} assessed - ₱{$actualPaid} paid).",
                    'evidence' => [
                        'overall_tuition' => (float) $tuition->overall_tuition,
                        'total_paid' => $actualPaid,
                        'stored_balance' => $storedBalance,
                        'expected_balance' => $expectedBalance,
                        'variance' => $balanceDiff,
                    ],
                    'suggested_action' => "Run EnrollmentBillingService::syncTuitionBalance() on tuition #{$tuition->id}.",
                ];
            }

            // 2. Status flag mismatch
            $expectedStatus = $this->billingService->paymentStatus($tuition, $actualPaid);
            if (mb_strtolower((string) $tuition->status) !== mb_strtolower($expectedStatus)) {
                $findings[] = [
                    'category' => 'payment_status_mismatch',
                    'severity' => 'warning',
                    'record_type' => 'student_tuition',
                    'record_id' => $tuition->id,
                    'term' => "{$tuition->school_year} Sem {$tuition->semester}",
                    'issue' => "Tuition status is marked '{$tuition->status}', but calculated payment position is '{$expectedStatus}'.",
                    'evidence' => [
                        'current_status' => $tuition->status,
                        'expected_status' => $expectedStatus,
                        'total_paid' => $actualPaid,
                        'balance_due' => $expectedBalance,
                    ],
                    'suggested_action' => "Synchronize payment status to '{$expectedStatus}'.",
                ];
            }

            // 3. Tuition calculation vs Enrolled Subject units
            if ($enrollment instanceof StudentEnrollment) {
                $subjects = SubjectEnrollment::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('exclude_from_tuition', false)
                    ->with('subject')
                    ->get();

                $expectedLecturesFee = 0.0;
                $expectedLabFee = 0.0;

                foreach ($subjects as $se) {
                    $expectedLecturesFee += (float) $se->lecture_fee;
                    $expectedLabFee += (float) $se->laboratory_fee;
                }

                $lectureDiff = round(abs((float) $tuition->total_lectures - $expectedLecturesFee), 2);
                $labDiff = round(abs((float) $tuition->total_laboratory - $expectedLabFee), 2);

                if ($lectureDiff > 1.0 && $subjects->isNotEmpty() && $expectedLecturesFee > 0) {
                    $findings[] = [
                        'category' => 'fee_assessment_mismatch',
                        'severity' => 'warning',
                        'record_type' => 'student_tuition',
                        'record_id' => $tuition->id,
                        'term' => "{$tuition->school_year} Sem {$tuition->semester}",
                        'issue' => "Assessed lecture total (₱{$tuition->total_lectures}) differs from enrolled subject lecture fees sum (₱{$expectedLecturesFee}).",
                        'evidence' => [
                            'stored_lectures' => (float) $tuition->total_lectures,
                            'enrolled_subjects_lecture_sum' => $expectedLecturesFee,
                            'variance' => $lectureDiff,
                        ],
                        'suggested_action' => "Recalculate enrollment tuition using recalculateEnrollmentTuition(#{$enrollment->id}).",
                    ];
                }

                if ($labDiff > 1.0 && $subjects->isNotEmpty() && $expectedLabFee > 0) {
                    $findings[] = [
                        'category' => 'fee_assessment_mismatch',
                        'severity' => 'warning',
                        'record_type' => 'student_tuition',
                        'record_id' => $tuition->id,
                        'term' => "{$tuition->school_year} Sem {$tuition->semester}",
                        'issue' => "Assessed lab fee (₱{$tuition->total_laboratory}) differs from enrolled subject lab fees sum (₱{$expectedLabFee}).",
                        'evidence' => [
                            'stored_lab' => (float) $tuition->total_laboratory,
                            'enrolled_subjects_lab_sum' => $expectedLabFee,
                            'variance' => $labDiff,
                        ],
                        'suggested_action' => "Recalculate enrollment tuition using recalculateEnrollmentTuition(#{$enrollment->id}).",
                    ];
                }
            }

            // 4. Overpayment check
            if ($actualPaid > (float) $tuition->overall_tuition && (float) $tuition->overall_tuition > 0) {
                $overpaidAmount = round($actualPaid - (float) $tuition->overall_tuition, 2);
                $findings[] = [
                    'category' => 'overpayment_detected',
                    'severity' => 'info',
                    'record_type' => 'student_tuition',
                    'record_id' => $tuition->id,
                    'term' => "{$tuition->school_year} Sem {$tuition->semester}",
                    'issue' => "Account has an overpayment credit of ₱{$overpaidAmount}.",
                    'evidence' => [
                        'overall_tuition' => (float) $tuition->overall_tuition,
                        'total_paid' => $actualPaid,
                        'credit_balance' => $overpaidAmount,
                    ],
                    'suggested_action' => 'Apply credit balance to subsequent terms or issue refund according to school policy.',
                ];
            }

            // 5. Installment schedule totals check
            if ($tuition->installments->isNotEmpty()) {
                $installmentSum = (float) $tuition->installments->sum('amount');
                $instDiff = round(abs((float) $tuition->overall_tuition - $installmentSum), 2);
                if ($instDiff > 1.0 && (float) $tuition->overall_tuition > 0) {
                    $findings[] = [
                        'category' => 'installment_schedule_error',
                        'severity' => 'warning',
                        'record_type' => 'student_tuition',
                        'record_id' => $tuition->id,
                        'term' => "{$tuition->school_year} Sem {$tuition->semester}",
                        'issue' => "Sum of installment breakdown amounts (₱{$installmentSum}) does not equal overall tuition (₱{$tuition->overall_tuition}).",
                        'evidence' => [
                            'overall_tuition' => (float) $tuition->overall_tuition,
                            'installments_sum' => $installmentSum,
                            'variance' => $instDiff,
                        ],
                        'suggested_action' => 'Regenerate installment schedule for tuition #'.$tuition->id.'.',
                    ];
                }
            }
        }

        // 6. Check for duplicate transactions or unallocated payments
        $transactions = StudentTransaction::query()
            ->where('student_id', $student->id)
            ->with('transaction')
            ->get();

        $seenAmounts = [];
        foreach ($transactions as $st) {
            $key = $st->amount.'-'.$st->created_at?->format('Y-m-d H:i');
            if (isset($seenAmounts[$key])) {
                $findings[] = [
                    'category' => 'potential_duplicate_transaction',
                    'severity' => 'warning',
                    'record_type' => 'student_transaction',
                    'record_id' => $st->id,
                    'issue' => "Possible duplicate payment transaction of ₱{$st->amount} recorded at {$st->created_at?->toIso8601String()}.",
                    'evidence' => [
                        'amount' => (float) $st->amount,
                        'first_transaction_id' => $seenAmounts[$key],
                        'second_transaction_id' => $st->id,
                    ],
                    'suggested_action' => 'Inspect cashier receipts to verify if payment was double-encoded.',
                ];
            } else {
                $seenAmounts[$key] = $st->id;
            }
        }

        // 7. Check for orphaned student transactions (enrollment_id deleted)
        $orphanedTransactions = StudentTransaction::query()
            ->where('student_id', $student->id)
            ->whereNotNull('student_enrollment_id')
            ->whereNotExists(function ($subQ): void {
                $subQ->select(DB::raw(1))
                    ->from('student_enrollment')
                    ->whereColumn('student_enrollment.id', 'student_transactions.student_enrollment_id');
            })
            ->get();

        foreach ($orphanedTransactions as $orphanedTx) {
            $findings[] = [
                'category' => 'orphaned_transaction',
                'severity' => 'warning',
                'record_type' => 'student_transaction',
                'record_id' => $orphanedTx->id,
                'issue' => "Transaction #{$orphanedTx->id} (₱{$orphanedTx->amount}) is linked to non-existent enrollment #{$orphanedTx->student_enrollment_id}.",
                'evidence' => ['amount' => (float) $orphanedTx->amount, 'enrollment_id' => $orphanedTx->student_enrollment_id],
                'suggested_action' => 'Reassign transaction to current active enrollment record.',
            ];
        }

        $criticalCount = count(array_filter($findings, fn ($f) => $f['severity'] === 'critical'));
        $warningCount = count(array_filter($findings, fn ($f) => $f['severity'] === 'warning'));

        return [
            'investigation_type' => 'financial_ledger_audit',
            'student' => [
                'id' => $student->id,
                'student_number' => (string) $student->student_id,
                'name' => $student->full_name,
            ],
            'scope' => [
                'school_year' => $schoolYear ?: 'All periods',
                'semester' => $semester ?: 'All semesters',
                'total_tuition_ledgers_audited' => $tuitions->count(),
            ],
            'discrepancies_found' => count($findings),
            'critical_discrepancies' => $criticalCount,
            'warnings' => $warningCount,
            'net_balance_variance' => $totalVariance,
            'status' => count($findings) === 0 ? 'clean' : ($criticalCount > 0 ? 'discrepancies_detected' : 'warnings_detected'),
            'findings' => $findings,
            'summary' => count($findings) === 0
                ? 'All tuition ledgers, payment allocations, and balances are mathematically consistent.'
                : sprintf('Found %d financial discrepancy item(s) with net variance of ₱%s.', count($findings), number_format($totalVariance, 2)),
        ];
    }

    private function classesOverlap(Classes $c1, Classes $c2): bool
    {
        // Check if both classes meet on the same day
        $day1 = mb_strtoupper(mb_trim((string) $c1->day));
        $day2 = mb_strtoupper(mb_trim((string) $c2->day));

        if ($day1 === '' || $day2 === '' || $day1 !== $day2) {
            return false;
        }

        if (blank($c1->start_time) || blank($c1->end_time) || blank($c2->start_time) || blank($c2->end_time)) {
            return false;
        }

        $start1 = strtotime((string) $c1->start_time);
        $end1 = strtotime((string) $c1->end_time);
        $start2 = strtotime((string) $c2->start_time);
        $end2 = strtotime((string) $c2->end_time);

        if (! $start1 || ! $end1 || ! $start2 || ! $end2) {
            return false;
        }

        // Two intervals overlap if start1 < end2 and start2 < end1
        return $start1 < $end2 && $start2 < $end1;
    }
}
