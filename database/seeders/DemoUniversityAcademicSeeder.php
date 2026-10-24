<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DemoUniversityAcademicSeeder extends Seeder
{
    private const YEAR = '2026 - 2027';

    private const SCHOOL_ID = 8;

    private const SEMESTER = 1;

    private string $now = '';

    private int $cashierId = 1;

    /** @var list<int> */
    private array $adminIds = [];

    /** @var array<int, object> */
    private array $classBySubject = [];

    /** @var array<string, array<int, object>> */
    private array $subjectsByCourseYear = [];

    /** @var array<string, string> */
    private array $facultyNames = [];

    /** @var array<int, object> */
    private array $discounts = [];

    private ?int $gradingVersionId = null;

    /** @var array<int, array<string, mixed>> */
    private array $tuitionPlans = [];

    /** @var array<string, int> */
    private array $feeScheduleIds = [];

    public function run(): void
    {
        $this->now = now()->toDateTimeString();
        $this->cashierId = (int) (DB::table('users')->where('role', 'cashier')->value('id') ?? 1);
        $this->adminIds = DB::table('users')->whereIn('role', ['super_admin', 'admin', 'developer'])->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->discounts = DB::table('enrollment_discounts')->get()->all();
        $this->gradingVersionId = DB::table('grading_policy_versions')->where('state', 'published')->value('id');
        $this->facultyNames = DB::table('faculty')->pluck('full_name', 'id')->all();

        $classes = DB::table('classes')->where('school_year', self::YEAR)->where('semester', self::SEMESTER)->get();
        foreach ($classes as $class) {
            $this->classBySubject[$class->subject_id] = $class;
        }

        $subjects = DB::table('subject')
            ->where('code', 'like', 'CUR-%')
            ->where('semester', self::SEMESTER)
            ->get();
        foreach ($subjects as $subject) {
            $this->subjectsByCourseYear[$subject->course_id.'|'.$subject->academic_year][] = $subject;
        }

        $feeSchedules = DB::table('fee_schedule_versions')->where('school_year', self::YEAR)->get();
        foreach ($feeSchedules as $schedule) {
            $this->feeScheduleIds[$schedule->course_id.'|'.$schedule->semester] = $schedule->id;
        }

        $this->seedEnrollments();
        $this->seedTuition();
        $this->seedTransactions();
        $this->seedStudentServices();
        $this->seedEnrollmentWorkflow();
        $this->seedAssessmentExports();
        $this->seedTuitionRequestsAndAdjustments();

        $this->command?->info('Academic data ready: '.DB::table('student_enrollment')->count().' enrollments, '.DB::table('subject_enrollments')->count().' subject enrollments, '.DB::table('transactions')->count().' transactions.');
    }

    private function seedEnrollments(): void
    {
        $this->command?->info('Enrolling students in the current semester...');

        $students = DB::table('students')
            ->whereNull('deleted_at')
            ->whereIn('status', ['enrolled', 'on_leave'])
            ->whereIn('student_type', ['college', 'tesda'])
            ->whereNotNull('course_id')
            ->orderBy('id')
            ->get();

        $statuses = array_merge(
            array_fill(0, (int) round($students->count() * 0.78), 'Enrolled'),
            array_fill(0, (int) round($students->count() * 0.10), 'Verified By Cashier'),
            array_fill(0, (int) round($students->count() * 0.07), 'Verified By Dept Head'),
            array_fill(0, (int) round($students->count() * 0.05), 'Pending'),
        );
        shuffle($statuses);

        $subjectEnrollmentRows = [];
        $classEnrollmentRows = [];

        foreach ($students as $index => $student) {
            $year = max(1, min(4, (int) $student->academic_year));
            $subjects = $this->subjectsByCourseYear[$student->course_id.'|'.$year] ?? [];
            if ($subjects === []) {
                continue;
            }

            $enrollmentId = DB::table('student_enrollment')->insertGetId([
                'student_id' => $student->id,
                'course_id' => $student->course_id,
                'status' => $statuses[$index % count($statuses)],
                'semester' => self::SEMESTER,
                'academic_year' => $year,
                'school_year' => self::YEAR,
                'downpayment' => 5000,
                'remarks' => 'Regular enrollment for academic year '.self::YEAR,
                'school_id' => self::SCHOOL_ID,
                'enrollment_policy_snapshot_id' => DB::table('enrollment_policy_snapshots')->value('id'),
                'workflow_runtime' => 'legacy',
                'submission_channel' => 'administrator',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $lectureUnits = 0;
            $laboratoryUnits = 0;
            $lectureFees = 0;
            $laboratoryFees = 0;

            $gradeMode = $index % 10;
            $prelim = $this->grade($index, 1);
            $midterm = $gradeMode < 7 ? $this->grade($index, 2) : null;
            $finals = $gradeMode < 3 ? $this->grade($index, 3) : null;

            foreach ($subjects as $subject) {
                $class = $this->classBySubject[$subject->id] ?? null;
                if (! $class) {
                    continue;
                }

                $lectureFee = (float) $subject->lecture * 550;
                $laboratoryFee = (float) $subject->laboratory * 750;
                $lectureUnits += (int) $subject->lecture;
                $laboratoryUnits += (int) $subject->laboratory;
                $lectureFees += $lectureFee;
                $laboratoryFees += $laboratoryFee;

                $availableGrades = array_values(array_filter([$prelim, $midterm, $finals], fn ($grade) => $grade !== null));
                $average = $availableGrades === [] ? null : round(array_sum($availableGrades) / count($availableGrades), 2);
                $outcome = $average === null ? null : ($average >= 75 ? 'pass' : 'fail');
                $symbol = $average === null ? null : ($average >= 75 ? 'P' : 'F');
                $qualityPoints = $average === null ? null : ($average >= 75 ? max(1.0, round(5.0 - (($average - 75) / 25) * 4.0, 2)) : 5.0);
                $components = json_encode([
                    'prelim' => $prelim,
                    'midterm' => $midterm,
                    'final' => $finals,
                    'average' => $average,
                ]);

                $subjectEnrollmentRows[] = [
                    'subject_id' => $subject->id,
                    'class_id' => $class->id,
                    'grade' => $finals ?? $midterm ?? $prelim,
                    'instructor' => $this->facultyNames[$class->faculty_id] ?? null,
                    'student_id' => $student->id,
                    'academic_year' => $year,
                    'school_year' => self::YEAR,
                    'semester' => self::SEMESTER,
                    'enrollment_id' => $enrollmentId,
                    'remarks' => 'Enrolled through the registrar workspace',
                    'classification' => 'internal',
                    'school_name' => 'KoAkademy Demo University',
                    'is_credited' => 0,
                    'section' => 'A',
                    'is_modular' => 0,
                    'lecture_fee' => $lectureFee,
                    'laboratory_fee' => $laboratoryFee,
                    'enrolled_lecture_units' => $subject->lecture,
                    'enrolled_laboratory_units' => $subject->laboratory,
                    'school_id' => self::SCHOOL_ID,
                    'grading_policy_version_id' => $this->gradingVersionId,
                    'grade_symbol' => $symbol,
                    'grade_outcome' => $outcome,
                    'grade_quality_points' => $qualityPoints,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];

                $classEnrollmentRows[] = [
                    'class_id' => $class->id,
                    'student_id' => $student->id,
                    'completion_date' => null,
                    'status' => 1,
                    'remarks' => null,
                    'prelim_grade' => $prelim,
                    'midterm_grade' => $midterm,
                    'finals_grade' => $finals,
                    'total_average' => $average,
                    'is_grades_finalized' => 0,
                    'is_grades_verified' => 0,
                    'verified_by' => null,
                    'verified_at' => null,
                    'verification_notes' => null,
                    'is_prelim_submitted' => $prelim !== null ? 1 : 0,
                    'is_midterms_submitted' => $midterm !== null ? 1 : 0,
                    'is_finals_submitted' => $finals !== null ? 1 : 0,
                    'school_id' => self::SCHOOL_ID,
                    'grading_policy_version_id' => $this->gradingVersionId,
                    'grading_components' => $components,
                    'grade_symbol' => $symbol,
                    'grade_outcome' => $outcome,
                    'grade_quality_points' => $qualityPoints,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }

            $gross = $lectureFees + $laboratoryFees + 3800;
            $discountPercent = $this->discountForStudent($student, $index);
            $net = round($gross * (1 - $discountPercent / 100), 2);

            $this->tuitionPlans[$enrollmentId] = [
                'student' => $student,
                'year' => $year,
                'lecture_units' => $lectureUnits,
                'laboratory_units' => $laboratoryUnits,
                'lecture_fees' => $lectureFees,
                'laboratory_fees' => $laboratoryFees,
                'miscellaneous' => 3800.0,
                'gross' => $gross,
                'discount_percent' => $discountPercent,
                'total' => $net,
                'status' => $index % 100 < 38 ? 'fully_paid' : ($index % 100 < 75 ? 'partially_paid' : 'pending'),
            ];
        }

        foreach (array_chunk($subjectEnrollmentRows, 200) as $chunk) {
            DB::table('subject_enrollments')->insert($chunk);
        }
        foreach (array_chunk($classEnrollmentRows, 200) as $chunk) {
            DB::table('class_enrollments')->insert($chunk);
        }
    }

    private function grade(int $seed, int $component): float
    {
        $value = 70 + (($seed * 37 + $component * 53) % 30);
        if ($seed % 13 === 0) {
            $value = 65 + (($seed + $component * 3) % 10);
        }

        return (float) $value;
    }

    private function discountForStudent(object $student, int $index): int
    {
        if ($student->scholarship_type !== 'none' && $student->scholarship_type !== null) {
            return $index % 2 === 0 ? 100 : 50;
        }

        return match ($index % 20) {
            0 => 5,
            1 => 10,
            2 => 15,
            3 => 20,
            default => 0,
        };
    }

    private function seedTuition(): void
    {
        $this->command?->info('Generating tuition assessments and installments...');

        $installmentRows = [];
        $revisionUpdates = [];

        foreach ($this->tuitionPlans as $enrollmentId => $plan) {
            $student = $plan['student'];
            $total = (float) $plan['total'];
            $downpayment = min(5000, $total);
            $status = $plan['status'];
            $paid = match ($status) {
                'fully_paid' => $total,
                'partially_paid' => round($downpayment + ($total - $downpayment) * (0.25 + (($enrollmentId % 40) / 100)), 2),
                default => 0.0,
            };
            $paid = min($paid, $total);
            $balance = round(max(0, $total - $paid), 2);

            $discountId = null;
            if ($plan['discount_percent'] > 0 && $this->discounts !== []) {
                $discountId = $this->discounts[$enrollmentId % count($this->discounts)]->id;
            }

            $tuitionId = DB::table('student_tuition')->insertGetId([
                'total_tuition' => $total,
                'total_balance' => $balance,
                'total_lectures' => $plan['lecture_fees'],
                'total_laboratory' => $plan['laboratory_fees'],
                'total_miscelaneous_fees' => $plan['miscellaneous'],
                'status' => $status,
                'semester' => self::SEMESTER,
                'school_year' => self::YEAR,
                'academic_year' => $plan['year'],
                'student_id' => $student->id,
                'enrollment_id' => $enrollmentId,
                'discount' => $plan['discount_percent'],
                'discount_id' => $discountId,
                'downpayment' => $downpayment,
                'overall_tuition' => $plan['gross'],
                'paid' => $paid,
                'gross_lecture' => $plan['lecture_fees'],
                'assessment_adjustment' => 0,
                'needs_finance_review' => 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $plan['tuition_id'] = $tuitionId;
            $plan['paid'] = $paid;
            $plan['downpayment'] = $downpayment;
            $plan['balance'] = $balance;
            $this->tuitionPlans[$enrollmentId] = $plan;

            $terms = ['Prelim', 'Midterm', 'Semi-Final', 'Final'];
            $installmentAmount = round(max(0, $total - $downpayment) / 4, 2);
            foreach ($terms as $sequence => $term) {
                $installmentRows[] = [
                    'student_tuition_id' => $tuitionId,
                    'term' => $term,
                    'sequence' => $sequence + 1,
                    'percentage' => 25,
                    'amount' => $installmentAmount,
                    'source' => 'generated',
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }

            $feeScheduleId = $this->feeScheduleIds[$student->course_id.'|'.self::SEMESTER] ?? null;
            $revisionId = DB::table('assessment_revisions')->insertGetId([
                'public_id' => (string) Str::uuid(),
                'student_tuition_id' => $tuitionId,
                'student_enrollment_id' => $enrollmentId,
                'fee_schedule_version_id' => $feeScheduleId,
                'actor_user_id' => $this->cashierId,
                'revision_number' => 1,
                'calculation_method' => 'reconciled_assessment',
                'status' => 'approved',
                'source' => 'workspace',
                'reason' => 'Initial assessment for the first semester of '.self::YEAR,
                'gross_lecture' => $plan['lecture_fees'],
                'discount_percentage' => $plan['discount_percent'],
                'discount_amount' => round($plan['gross'] * $plan['discount_percent'] / 100, 2),
                'discounted_lecture' => round($plan['lecture_fees'] * (1 - $plan['discount_percent'] / 100), 2),
                'laboratory' => $plan['laboratory_fees'],
                'modular' => 0,
                'total_tuition' => $total,
                'miscellaneous' => $plan['miscellaneous'],
                'additional_fees' => 0,
                'assessment_adjustment' => 0,
                'overall_tuition' => $total,
                'required_downpayment' => $downpayment,
                'opening_paid' => 0,
                'verified_paid' => $paid,
                'total_paid' => $paid,
                'balance_due' => $balance,
                'credit' => 0,
                'installments' => json_encode(array_map(fn ($term, $sequence) => [
                    'term' => $term,
                    'sequence' => $sequence + 1,
                    'percentage' => 25,
                    'amount' => $installmentAmount,
                ], $terms, array_keys($terms))),
                'calculation_inputs' => json_encode([
                    'lecture_units' => $plan['lecture_units'],
                    'laboratory_units' => $plan['laboratory_units'],
                    'lecture_rate' => 550,
                    'laboratory_rate' => 750,
                ]),
                'fingerprint' => hash('sha256', $student->student_id.'|'.$total.'|'.$enrollmentId),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            DB::table('student_tuition')->where('id', $tuitionId)->update(['active_revision_id' => $revisionId]);

            $additionalFees = [
                ['fee_name' => 'Library Fee', 'amount' => 500],
                ['fee_name' => 'Laboratory Fee', 'amount' => 750],
                ['fee_name' => 'Student ID Fee', 'amount' => 250],
            ];
            foreach ($additionalFees as $fee) {
                DB::table('additional_fees')->insert([
                    'enrollment_id' => $enrollmentId,
                    'fee_name' => $fee['fee_name'],
                    'amount' => $fee['amount'],
                    'is_separate_transaction' => 0,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }

        foreach (array_chunk($installmentRows, 300) as $chunk) {
            DB::table('student_tuition_installments')->insert($chunk);
        }
    }

    private function seedTransactions(): void
    {
        $this->command?->info('Recording payments, receipts, and statements of account...');

        $methods = ['Cash', 'GCash', 'Maya', 'Bank Transfer', 'Credit Card', 'Online Payment'];
        $txCounter = 0;
        $documentCounter = 0;
        $soaCounter = 0;

        foreach ($this->tuitionPlans as $enrollmentId => $plan) {
            $paid = (float) $plan['paid'];
            if ($paid <= 0) {
                continue;
            }

            $student = $plan['student'];
            $tuitionId = $plan['tuition_id'];
            $downpayment = (float) $plan['downpayment'];
            $payments = [];
            if ($paid >= $downpayment && $paid > $downpayment) {
                $payments[] = $downpayment;
                $payments[] = round($paid - $downpayment, 2);
            } else {
                $payments[] = $paid;
            }

            foreach ($payments as $paymentIndex => $amount) {
                if ($amount <= 0) {
                    continue;
                }

                $txCounter++;
                $date = Carbon::create(2026, 8, 5)->addDays(($txCounter * 3) % 75);
                $method = $methods[$txCounter % count($methods)];
                $transactionId = DB::table('transactions')->insertGetId([
                    'description' => 'Tuition payment for '.$student->first_name.' '.$student->last_name.' ('.self::YEAR.')',
                    'status' => 'completed',
                    'transaction_date' => $date->toDateTimeString(),
                    'transaction_number' => 'TXN-2026-'.str_pad((string) $txCounter, 6, '0', STR_PAD_LEFT),
                    'invoicenumber' => 'INV-2026-'.str_pad((string) $txCounter, 6, '0', STR_PAD_LEFT),
                    'settlements' => json_encode(['tuition_fee' => $amount]),
                    'transaction_type' => $paymentIndex === 0 ? 'enrollment_payment' : 'partial_payment',
                    'payment_method' => $method,
                    'user_id' => $this->cashierId,
                    'created_at' => $date->toDateTimeString(),
                    'updated_at' => $date->toDateTimeString(),
                ]);

                $studentTransactionId = DB::table('student_transactions')->insertGetId([
                    'student_id' => $student->id,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'status' => 'completed',
                    'student_enrollment_id' => $enrollmentId,
                    'created_at' => $date->toDateTimeString(),
                    'updated_at' => $date->toDateTimeString(),
                ]);

                DB::table('admin_transactions')->insert([
                    'admin_id' => $this->cashierId,
                    'transaction_id' => $transactionId,
                    'amount' => $amount,
                    'type' => 'payment',
                    'description' => 'Payment received from '.$student->first_name.' '.$student->last_name,
                    'status' => 'completed',
                    'created_at' => $date->toDateTimeString(),
                    'updated_at' => $date->toDateTimeString(),
                ]);

                DB::table('payment_allocations')->insert([
                    'transaction_id' => $transactionId,
                    'student_transaction_id' => $studentTransactionId,
                    'student_id' => $student->id,
                    'student_enrollment_id' => $enrollmentId,
                    'student_tuition_id' => $tuitionId,
                    'charge_category' => 'tuition',
                    'target_type' => 'assessment',
                    'amount' => $amount,
                    'notes' => 'Automatically allocated to the tuition assessment.',
                    'created_at' => $date->toDateTimeString(),
                    'updated_at' => $date->toDateTimeString(),
                ]);

                if ($txCounter % 5 !== 0) {
                    $documentCounter++;
                    $documentNumber = 'ERCP-2026-'.str_pad((string) $documentCounter, 6, '0', STR_PAD_LEFT);
                    $token = Str::random(40);
                    $issuanceId = DB::table('financial_document_issuances')->insertGetId([
                        'uuid' => (string) Str::uuid(),
                        'type' => 'receipt',
                        'student_id' => $student->id,
                        'transaction_id' => $transactionId,
                        'enrollment_id' => $enrollmentId,
                        'tuition_id' => $tuitionId,
                        'issued_by' => $this->cashierId,
                        'document_number' => $documentNumber,
                        'recipient' => $student->email,
                        'status' => 'sent',
                        'snapshot' => json_encode([
                            'student' => $student->first_name.' '.$student->last_name,
                            'student_id' => $student->student_id,
                            'amount' => $amount,
                            'transaction_number' => 'TXN-2026-'.str_pad((string) $txCounter, 6, '0', STR_PAD_LEFT),
                            'school_year' => self::YEAR,
                        ]),
                        'integrity_signature' => hash('sha256', $documentNumber.'|'.$amount.'|'.$student->student_id),
                        'verification_token' => $token,
                        'verification_token_hash' => hash('sha256', $token),
                        'disk' => 'local',
                        'pdf_path' => 'financial-documents/'.$documentNumber.'.pdf',
                        'pdf_checksum' => hash('sha256', 'pdf-'.$documentNumber),
                        'issued_at' => $date->toDateTimeString(),
                        'queued_at' => $date->toDateTimeString(),
                        'sent_at' => $date->copy()->addMinutes(2)->toDateTimeString(),
                        'created_at' => $date->toDateTimeString(),
                        'updated_at' => $date->toDateTimeString(),
                    ]);

                    DB::table('financial_document_deliveries')->insert([
                        'uuid' => (string) Str::uuid(),
                        'financial_document_issuance_id' => $issuanceId,
                        'recipient' => $student->email,
                        'status' => 'sent',
                        'attempts' => 1,
                        'queued_at' => $date->toDateTimeString(),
                        'sent_at' => $date->copy()->addMinutes(2)->toDateTimeString(),
                        'created_at' => $date->toDateTimeString(),
                        'updated_at' => $date->toDateTimeString(),
                    ]);
                }
            }

            if ($txCounter % 4 === 0) {
                $soaCounter++;
                $documentNumber = 'SOA-2026-'.str_pad((string) $soaCounter, 6, '0', STR_PAD_LEFT);
                $token = Str::random(40);
                DB::table('statement_of_account_issuances')->insert([
                    'uuid' => (string) Str::uuid(),
                    'student_id' => $student->id,
                    'enrollment_id' => $enrollmentId,
                    'tuition_id' => $tuitionId,
                    'issued_by' => $this->cashierId,
                    'document_number' => $documentNumber,
                    'verification_token_hash' => hash('sha256', $token),
                    'integrity_signature' => hash('sha256', $documentNumber.'|soa|'.$student->student_id),
                    'snapshot' => json_encode([
                        'student' => $student->first_name.' '.$student->last_name,
                        'total_tuition' => $plan['total'],
                        'paid' => $plan['paid'],
                        'balance' => $plan['balance'],
                    ]),
                    'status' => 'sent',
                    'disk' => 'local',
                    'pdf_path' => 'statements/'.$documentNumber.'.pdf',
                    'pdf_checksum' => hash('sha256', 'soa-'.$documentNumber),
                    'issued_at' => $this->now,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }
        }
    }

    private function seedStudentServices(): void
    {
        $this->command?->info('Creating clearances, status records, and student logs...');
        $students = DB::table('students')->whereNull('deleted_at')->orderBy('id')->get();
        $clearanceRows = [];
        $statusRows = [];
        $clearanceUpdates = [];

        foreach ($students as $index => $student) {
            $isCleared = $student->status === 'enrolled' && $index % 4 !== 0;
            $clearanceRows[] = [
                'student_id' => $student->id,
                'academic_year' => self::YEAR,
                'semester' => self::SEMESTER,
                'is_cleared' => $isCleared ? 1 : 0,
                'cleared_by' => $isCleared ? 'cashier@koakademy.edu' : null,
                'cleared_at' => $isCleared ? $this->now : null,
                'remarks' => $isCleared ? 'Cleared for the first semester.' : 'Pending completion of financial and academic requirements.',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            $statusRows[] = [
                'student_id' => $student->id,
                'academic_year' => self::YEAR,
                'semester' => self::SEMESTER,
                'status' => $student->status,
                'school_id' => self::SCHOOL_ID,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            $clearanceUpdates[] = ['id' => $student->id, 'clearance_status' => $isCleared ? 'cleared' : 'pending'];
        }

        foreach (array_chunk($clearanceRows, 200) as $chunk) {
            DB::table('student_clearances')->insert($chunk);
        }
        foreach (array_chunk($statusRows, 200) as $chunk) {
            DB::table('student_statuses')->insert($chunk);
        }
        foreach ($clearanceUpdates as $clearanceUpdate) {
            DB::table('students')->where('id', $clearanceUpdate['id'])->update(['clearance_status' => $clearanceUpdate['clearance_status']]);
        }

        $logs = [];
        $reason = ['Corrected birth date entry', 'Updated middle name', 'Corrected student number', 'Resolved duplicate record', 'Updated guardian information'];
        for ($i = 0; $i < 30; $i++) {
            $student = $students[$i % $students->count()];
            $logs[] = [
                'old_student_id' => (string) ($student->student_id - 1),
                'new_student_id' => (string) $student->student_id,
                'student_name' => $student->first_name.' '.$student->last_name,
                'changed_by' => 'registrar@koakademy.edu',
                'affected_records' => json_encode(['students', 'student_enrollment', 'student_tuition']),
                'backup_data' => json_encode(['student_id' => $student->student_id, 'name' => $student->first_name.' '.$student->last_name]),
                'reason' => $reason[$i % count($reason)],
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('student_id_change_logs')->insert($logs);

        $applicants = $students->whereIn('status', ['applicant', 'rejected', 'withdrawn'])->take(15);
        $reasons = [
            ['incomplete_documents', 'Incomplete or missing documents'],
            ['requirements_not_met', 'Admission requirements not met'],
            ['program_at_capacity', 'Program is at full capacity'],
            ['not_eligible', 'Not eligible for the program'],
            ['unverifiable_information', 'Information could not be verified'],
            ['duplicate_application', 'Duplicate application'],
            ['applicant_withdrew', 'Applicant withdrew / lost interest'],
            ['did_not_complete_process', 'Did not complete the application process'],
            ['other', 'Other reason'],
        ];
        $cancellations = [];
        foreach ($applicants as $index => $student) {
            $reason = $reasons[$index % count($reasons)];
            $cancellations[] = [
                'student_id' => $student->id,
                'reason_code' => $reason[0],
                'reason_label' => $reason[1],
                'note' => 'Application cancelled during the admissions review.',
                'email_subject' => 'Application cancellation notice',
                'email_body' => 'Your application has been cancelled. You may reapply in the next admission cycle.',
                'email_sent_at' => $this->now,
                'cancelled_by' => $this->cashierId,
                'cancelled_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        if ($cancellations !== []) {
            DB::table('applicant_cancellations')->insert($cancellations);
        }
    }

    private function seedEnrollmentWorkflow(): void
    {
        $enrollments = DB::table('student_enrollment')->orderBy('id')->get();
        $requirements = [];
        $events = [];

        $definitions = [
            ['key' => 'documents', 'label' => 'Complete admission documents'],
            ['key' => 'payment', 'label' => 'Settle the required downpayment'],
            ['key' => 'medical', 'label' => 'Submit the medical clearance'],
            ['key' => 'advisory', 'label' => 'Attend the academic advising session'],
        ];

        foreach ($enrollments as $index => $enrollment) {
            foreach ($definitions as $definitionIndex => $definition) {
                $status = ($index + $definitionIndex) % 5 === 0 ? 'pending' : (($index + $definitionIndex) % 7 === 0 ? 'waived' : 'verified');
                $requirements[] = [
                    'student_enrollment_id' => $enrollment->id,
                    'enrollment_policy_snapshot_id' => $enrollment->enrollment_policy_snapshot_id,
                    'requirement_key' => $definition['key'],
                    'label' => $definition['label'],
                    'description' => $definition['label'].' for the first semester.',
                    'enforcement_step_key' => 'registrar_review',
                    'is_required' => 1,
                    'status' => $status,
                    'evidence_path' => $status === 'verified' ? 'enrollment-documents/'.$enrollment->student_id.'/'.$definition['key'].'.pdf' : null,
                    'evidence' => $status === 'verified' ? json_encode(['verified' => true, 'source' => 'seeder']) : null,
                    'verified_by' => $status === 'verified' ? $this->cashierId : null,
                    'verified_at' => $status === 'verified' ? $this->now : null,
                    'waived_by' => $status === 'waived' ? $this->cashierId : null,
                    'waived_at' => $status === 'waived' ? $this->now : null,
                    'waiver_reason' => $status === 'waived' ? 'Requirement waived by the registrar.' : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }

            $events[] = [
                'student_enrollment_id' => $enrollment->id,
                'enrollment_policy_snapshot_id' => $enrollment->enrollment_policy_snapshot_id,
                'actor_id' => $this->cashierId,
                'event_type' => 'initialized',
                'from_step_key' => null,
                'to_step_key' => 'registrar_review',
                'status' => 'completed',
                'terminal_outcome' => null,
                'reason' => 'Enrollment submitted from the registrar workspace.',
                'result' => json_encode(['requirements' => 4]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];

            $events[] = [
                'student_enrollment_id' => $enrollment->id,
                'enrollment_policy_snapshot_id' => $enrollment->enrollment_policy_snapshot_id,
                'actor_id' => $this->cashierId,
                'event_type' => 'transition_succeeded',
                'from_step_key' => 'registrar_review',
                'to_step_key' => 'enrolled',
                'status' => 'completed',
                'terminal_outcome' => 'enrolled',
                'reason' => 'Requirements reviewed and the enrollment was finalized.',
                'result' => json_encode(['status' => $enrollment->status]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        foreach (array_chunk($requirements, 300) as $chunk) {
            DB::table('enrollment_requirements')->insert($chunk);
        }
        foreach (array_chunk($events, 300) as $chunk) {
            DB::table('enrollment_workflow_events')->insert($chunk);
        }
    }

    private function seedAssessmentExports(): void
    {
        $enrollments = DB::table('student_enrollment')->pluck('id')->all();
        if ($enrollments === []) {
            return;
        }

        for ($i = 0; $i < 6; $i++) {
            $exportId = (string) Str::uuid();
            $status = $i < 4 ? 'completed' : 'processing';
            $total = 15;
            DB::table('assessment_exports')->insert([
                'id' => $exportId,
                'user_id' => $this->cashierId,
                'school_id' => self::SCHOOL_ID,
                'status' => $status,
                'stage' => $status === 'completed' ? 'ready' : 'rendering',
                'filters' => json_encode(['school_year' => self::YEAR, 'semester' => self::SEMESTER, 'batch' => $i + 1]),
                'total_count' => $total,
                'processed_count' => $status === 'completed' ? $total : 8,
                'completed_count' => $status === 'completed' ? $total : 8,
                'skipped_count' => 0,
                'failed_count' => 0,
                'merged_parts' => $status === 'completed' ? 1 : 0,
                'total_parts' => 1,
                'percentage' => $status === 'completed' ? 100 : 53,
                'message' => 'Assessment export batch '.($i + 1),
                'output_disk' => $status === 'completed' ? 'local' : null,
                'output_path' => $status === 'completed' ? 'exports/assessment-'.($i + 1).'.pdf' : null,
                'output_name' => 'assessment-batch-'.($i + 1).'.pdf',
                'started_at' => $this->now,
                'completed_at' => $status === 'completed' ? $this->now : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $items = [];
            for ($j = 0; $j < $total; $j++) {
                $items[] = [
                    'assessment_export_id' => $exportId,
                    'school_id' => self::SCHOOL_ID,
                    'enrollment_id' => $enrollments[($i * 15 + $j) % count($enrollments)],
                    'sequence' => $j + 1,
                    'status' => $status === 'completed' ? 'completed' : 'pending',
                    'attempts' => $status === 'completed' ? 1 : 0,
                    'artifact_disk' => $status === 'completed' ? 'local' : null,
                    'artifact_path' => $status === 'completed' ? 'exports/assessment-'.($i + 1).'-'.$j.'.pdf' : null,
                    'page_count' => $status === 'completed' ? 2 : null,
                    'byte_size' => $status === 'completed' ? 180000 : null,
                    'checksum' => $status === 'completed' ? hash('sha256', $exportId.'|'.$j) : null,
                    'started_at' => $this->now,
                    'completed_at' => $status === 'completed' ? $this->now : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
            DB::table('assessment_export_items')->insert($items);
        }
    }

    private function seedTuitionRequestsAndAdjustments(): void
    {
        $plans = array_slice($this->tuitionPlans, 0, 80, true);
        $requests = [];
        $requestEvents = [];
        $requestIndex = 0;

        foreach ($plans as $enrollmentId => $plan) {
            $student = $plan['student'];
            $userId = (int) ($student->user_id ?? $this->cashierId);
            $status = ['pending', 'in_review', 'resolved', 'rejected'][$requestIndex % 4];
            $openKey = 'tuition-request-'.$requestIndex.'-'.$enrollmentId;
            $requests[] = [
                'submitted_by_user_id' => $userId,
                'student_id' => $student->id,
                'student_enrollment_id' => $enrollmentId,
                'student_tuition_id' => $plan['tuition_id'],
                'school_year' => self::YEAR,
                'semester' => self::SEMESTER,
                'concern_type' => ['missing_payment', 'discount', 'subject_change', 'other'][$requestIndex % 4],
                'receipt_number' => 'ERCP-2026-'.str_pad((string) ($requestIndex + 1), 6, '0', STR_PAD_LEFT),
                'details' => 'Please review the assessment and payment posting for this semester.',
                'status' => $status,
                'reviewed_by_user_id' => $status === 'pending' ? null : $this->cashierId,
                'reviewed_at' => $status === 'pending' ? null : $this->now,
                'resolution_note' => $status === 'resolved' ? 'Assessment corrected and payment posted.' : null,
                'open_key' => $openKey,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
            $requestIndex++;
        }

        if ($requests !== []) {
            DB::table('student_tuition_update_requests')->insert($requests);
            $savedRequests = DB::table('student_tuition_update_requests')->orderBy('id')->get();
            foreach ($savedRequests as $index => $request) {
                foreach ([['submitted', null, 'pending'], ['reviewed', 'pending', $request->status], ['resolved', 'in_review', 'resolved']] as $eventIndex => $event) {
                    if ($event[2] === 'pending' && $eventIndex > 0) {
                        continue;
                    }
                    if ($index % 4 === 0 && $eventIndex > 0) {
                        continue;
                    }
                    $requestEvents[] = [
                        'student_tuition_update_request_id' => $request->id,
                        'actor_user_id' => $eventIndex === 0 ? $request->submitted_by_user_id : $this->cashierId,
                        'event' => $event[0],
                        'from_status' => $event[1],
                        'to_status' => $event[2],
                        'note' => 'Status updated by the finance office.',
                        'metadata' => json_encode(['source' => 'demo']),
                        'created_at' => $this->now,
                    ];
                }
            }
            DB::table('student_tuition_update_request_events')->insert($requestEvents);
        }

        $batches = [];
        for ($i = 0; $i < 8; $i++) {
            $batches[] = [
                'public_id' => (string) Str::uuid(),
                'actor_user_id' => $this->cashierId,
                'source' => 'workspace',
                'status' => 'completed',
                'recorded_count' => 5,
                'duplicate_count' => 0,
                'rejected_count' => 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('tuition_adjustment_batches')->insert($batches);

        $batchIds = DB::table('tuition_adjustment_batches')->pluck('id')->all();
        $tuitionIds = array_values(array_map(fn ($plan) => $plan['tuition_id'], $this->tuitionPlans));
        $enrollmentIds = array_keys($this->tuitionPlans);
        $adjustments = [];

        for ($i = 0; $i < 40; $i++) {
            $plan = $this->tuitionPlans[$enrollmentIds[$i % count($enrollmentIds)]];
            $before = ['total_tuition' => $plan['total'], 'paid' => $plan['paid']];
            $after = ['total_tuition' => round($plan['total'] - 500, 2), 'paid' => $plan['paid']];
            $adjustments[] = [
                'batch_id' => $batchIds[$i % count($batchIds)],
                'actor_user_id' => $this->cashierId,
                'student_enrollment_id' => $enrollmentIds[$i % count($enrollmentIds)],
                'student_tuition_id' => $tuitionIds[$i % count($tuitionIds)],
                'client_row_id' => 'adjustment-row-'.$i,
                'idempotency_key' => 'adjustment-'.Str::uuid(),
                'source' => 'workspace',
                'reason' => 'Approved tuition adjustment for scholarship and miscellaneous fees.',
                'before_snapshot' => json_encode($before),
                'after_snapshot' => json_encode($after),
                'configuration_snapshot' => json_encode(['discount' => 5, 'miscellaneous' => 3800]),
                'delivery_status' => 'delivered',
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('tuition_adjustments')->insert($adjustments);
    }
}
