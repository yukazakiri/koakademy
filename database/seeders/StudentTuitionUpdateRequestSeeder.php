<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\StudentTuitionUpdateRequest;
use App\Models\StudentTuitionUpdateRequestEvent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seed realistic sample tuition update requests for a local or demo account.
 *
 * Run with: php artisan db:seed --class=StudentTuitionUpdateRequestSeeder
 */
final class StudentTuitionUpdateRequestSeeder extends Seeder
{
    public function run(): void
    {
        $student = Student::query()->whereNotNull('user_id')->orderBy('id')->first();
        $studentUser = $student instanceof Student
            ? User::query()->find($student->user_id) ?? User::query()->where('email', $student->email)->first()
            : null;

        if (! $student instanceof Student || ! $studentUser instanceof User) {
            $this->command?->warn('No student with a linked user account was found. Seed students first.');

            return;
        }

        $period = StudentEnrollment::query()
            ->where('student_id', $student->id)
            ->orderByDesc('school_year')
            ->orderByDesc('semester')
            ->first();

        $tuition = StudentTuition::query()
            ->where('student_id', $student->id)
            ->when(
                $period instanceof StudentEnrollment,
                fn ($query) => $query->where('school_year', $period->school_year)
                    ->where('semester', $period->semester),
            )
            ->orderByDesc('school_year')
            ->orderByDesc('semester')
            ->first();

        if (! $period instanceof StudentEnrollment && ! $tuition instanceof StudentTuition) {
            $this->command?->warn('The selected student has no enrollment or tuition period. Seed academic records first.');

            return;
        }

        $schoolYear = (string) ($period?->school_year ?? $tuition?->school_year);
        $semester = (int) ($period?->semester ?? $tuition?->semester ?? 1);
        $enrollment = $period;
        $tuition = $tuition ?? StudentTuition::query()
            ->where('student_id', $student->id)
            ->where('school_year', $schoolYear)
            ->where('semester', $semester)
            ->first();

        $financeUser = User::query()
            ->where('id', '!=', $studentUser->id)
            ->orderBy('id')
            ->first();
        $financeUser ??= User::query()->firstOrCreate(
            ['email' => 'tuition-finance-demo@example.test'],
            [
                'name' => 'Tuition Demo Finance Reviewer',
                'role' => 'accounting_officer',
                'password' => Hash::make(str()->random(48)),
            ],
        );
        $inReviewStatus = $financeUser instanceof User
            ? StudentTuitionUpdateRequest::StatusInReview
            : StudentTuitionUpdateRequest::StatusPending;
        $resolvedStatus = $financeUser instanceof User
            ? StudentTuitionUpdateRequest::StatusResolved
            : StudentTuitionUpdateRequest::StatusPending;

        $examples = [
            [
                'concern_type' => StudentTuitionUpdateRequest::ConcernMissingPayment,
                'receipt_number' => 'DEMO-OR-2026-001',
                'details' => 'I paid my downpayment at the cashier, but it is not reflected in my tuition record. Please verify the official receipt and payment date.',
                'status' => StudentTuitionUpdateRequest::StatusPending,
                'resolution_note' => null,
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'resolved_at' => null,
                'event' => 'submitted',
                'note' => null,
            ],
            [
                'concern_type' => StudentTuitionUpdateRequest::ConcernDiscount,
                'receipt_number' => null,
                'details' => 'My approved scholarship discount is not shown on this semester’s tuition assessment. Please check whether it has been applied.',
                'status' => $inReviewStatus,
                'resolution_note' => null,
                'reviewed_by_user_id' => $financeUser?->id,
                'reviewed_at' => now()->subDay(),
                'resolved_at' => null,
                'event' => $inReviewStatus === StudentTuitionUpdateRequest::StatusInReview ? 'claimed' : 'submitted',
                'note' => $inReviewStatus === StudentTuitionUpdateRequest::StatusInReview
                    ? 'Finance is checking the scholarship approval and assessment.'
                    : null,
            ],
            [
                'concern_type' => StudentTuitionUpdateRequest::ConcernSubjectChange,
                'receipt_number' => null,
                'details' => 'A recently removed subject still appears in my tuition assessment. Please verify my enrollment and the amount charged.',
                'status' => $resolvedStatus,
                'resolution_note' => $resolvedStatus === StudentTuitionUpdateRequest::StatusResolved
                    ? 'We verified your enrollment, removed the dropped subject charge, and refreshed the assessment.'
                    : null,
                'reviewed_by_user_id' => $financeUser?->id,
                'reviewed_at' => $resolvedStatus === StudentTuitionUpdateRequest::StatusResolved ? now()->subDays(4) : null,
                'resolved_at' => $resolvedStatus === StudentTuitionUpdateRequest::StatusResolved ? now()->subDays(3) : null,
                'event' => $resolvedStatus === StudentTuitionUpdateRequest::StatusResolved ? 'resolved_with_adjustment' : 'submitted',
                'note' => $resolvedStatus === StudentTuitionUpdateRequest::StatusResolved
                    ? 'The assessment was updated after checking the enrollment record.'
                    : null,
            ],
        ];

        foreach ($examples as $example) {
            $request = StudentTuitionUpdateRequest::query()->firstOrCreate(
                [
                    'student_id' => $student->id,
                    'school_year' => $schoolYear,
                    'semester' => $semester,
                    'concern_type' => $example['concern_type'],
                ],
                [
                    'submitted_by_user_id' => $studentUser->id,
                    'student_enrollment_id' => $enrollment?->id,
                    'student_tuition_id' => $tuition?->id,
                    'receipt_number' => $example['receipt_number'],
                    'details' => $example['details'],
                    'status' => $example['status'],
                    'reviewed_by_user_id' => $example['reviewed_by_user_id'],
                    'reviewed_at' => $example['reviewed_at'],
                    'resolution_note' => $example['resolution_note'],
                    'resolved_at' => $example['resolved_at'],
                    'open_key' => in_array($example['status'], StudentTuitionUpdateRequest::openStatuses(), true)
                        ? hash('sha256', implode('|', [$student->id, $schoolYear, $semester, $example['concern_type']]))
                        : null,
                    'created_at' => match ($example['status']) {
                        StudentTuitionUpdateRequest::StatusPending => now()->subHours(2),
                        StudentTuitionUpdateRequest::StatusInReview => now()->subDays(2),
                        default => now()->subDays(5),
                    },
                ],
            );

            StudentTuitionUpdateRequestEvent::query()->firstOrCreate(
                [
                    'student_tuition_update_request_id' => $request->id,
                    'event' => $example['event'],
                ],
                [
                    'actor_user_id' => $example['reviewed_by_user_id'] ?? $studentUser->id,
                    'from_status' => $example['event'] === 'claimed' ? StudentTuitionUpdateRequest::StatusPending : null,
                    'to_status' => $example['status'],
                    'note' => $example['note'] ?? $example['resolution_note'],
                    'created_at' => $example['reviewed_at'] ?? $request->created_at,
                ],
            );
        }

        $this->command?->info("Seeded sample tuition update requests for student {$student->id} ({$schoolYear}, semester {$semester}).");
    }
}
