<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\SubjectEnrollment;
use Illuminate\Database\Seeder;

final class StudentTuitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $enrollments = StudentEnrollment::all();

        foreach ($enrollments as $enrollment) {
            $student = Student::query()->find($enrollment->student_id);
            if (! $student) {
                continue;
            }

            $course = $student->Course;
            if (! $course) {
                continue;
            }

            // Get all subject enrollments for this enrollment
            $subjectEnrollments = SubjectEnrollment::query()->where('enrollment_id', $enrollment->id)->get();

            // Calculate total fees
            $totalLectures = $subjectEnrollments->sum('lecture_fee');
            $totalLaboratory = $subjectEnrollments->sum('laboratory_fee');
            $totalTuition = $totalLectures + $totalLaboratory;

            // Get miscellaneous fees from course
            $miscellaneousFees = $course->getMiscellaneousFee();

            // Calculate overall tuition
            $overallTuition = $totalTuition + $miscellaneousFees;

            // Apply discount (10% for some students)
            $discount = in_array($student->id, [2021001, 2021003, 2024001]) ? 10 : 0;
            $discountAmount = $overallTuition * ($discount / 100);
            $finalTuition = $overallTuition - $discountAmount;

            // Calculate balance based on downpayment
            $downpayment = $enrollment->downpayment;
            $totalBalance = max(0, $finalTuition - $downpayment);

            // Determine status
            $status = match (true) {
                $totalBalance <= 0 => 'fully_paid',
                $downpayment > 0 => 'partially_paid',
                default => 'pending'
            };

            StudentTuition::query()->create([
                'total_tuition' => $totalTuition,
                'total_balance' => $totalBalance,
                'total_lectures' => $totalLectures,
                'total_laboratory' => $totalLaboratory,
                'total_miscelaneous_fees' => $miscellaneousFees,
                'status' => $status,
                'semester' => $enrollment->semester,
                'school_year' => $enrollment->school_year,
                'academic_year' => $enrollment->academic_year,
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'discount' => $discount,
                'downpayment' => $downpayment,
                'overall_tuition' => $finalTuition,
                'paid' => (int) $downpayment,
            ]);
        }

        // Create some additional payment scenarios for testing
        $specialCases = [
            // Fully paid student
            ['student_id' => 2021001, 'additional_payment' => 15000],
            // Overpaid student (for testing)
            ['student_id' => 2021003, 'additional_payment' => 20000],
            // Partial payment student
            ['student_id' => 2024001, 'additional_payment' => 8000],
        ];

        foreach ($specialCases as $specialCase) {
            $tuition = StudentTuition::query()->where('student_id', $specialCase['student_id'])
                ->where('school_year', '2024-2025')
                ->where('semester', 1)
                ->first();

            if ($tuition) {
                $newPaid = $tuition->paid + $specialCase['additional_payment'];
                $newBalance = max(0, $tuition->overall_tuition - $newPaid);

                $tuition->update([
                    'paid' => $newPaid,
                    'total_balance' => $newBalance,
                    'status' => $newBalance <= 0 ? 'fully_paid' : 'partially_paid',
                ]);
            }
        }

        $this->command->info('Student tuitions seeded successfully!');
    }
}
