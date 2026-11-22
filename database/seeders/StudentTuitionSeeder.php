<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentTuition;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Seeder;

final class StudentTuitionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = app(GeneralSettingsService::class);
        $currentSchoolYearVariants = $settings->getCurrentSchoolYearVariants();
        $currentSemester = $settings->getCurrentSemester();

        $enrollments = StudentEnrollment::withoutSchoolScope()->get();

        foreach ($enrollments as $enrollment) {
            $student = Student::withoutSchoolScope()->find($enrollment->student_id);
            if (! $student) {
                continue;
            }

            $course = $student->Course;
            if (! $course) {
                continue;
            }

            $subjectEnrollments = SubjectEnrollment::withoutSchoolScope()
                ->where('enrollment_id', $enrollment->id)
                ->get();

            $totalLectures = $subjectEnrollments->sum('lecture_fee');
            $totalLaboratory = $subjectEnrollments->sum('laboratory_fee');
            $totalTuition = $totalLectures + $totalLaboratory;

            $miscellaneousFees = $course->getMiscellaneousFee();
            $overallTuition = $totalTuition + $miscellaneousFees;

            $discount = in_array($student->id, [2021001, 2021003, 2024001], true) ? 10 : 0;
            $discountAmount = $overallTuition * ($discount / 100);
            $finalTuition = $overallTuition - $discountAmount;

            $downpayment = (float) $enrollment->downpayment;
            $isCompleted = $enrollment->status === 'Completed';
            $paid = $isCompleted ? (int) $finalTuition : (int) $downpayment;
            $totalBalance = $isCompleted ? 0 : max(0, $finalTuition - $downpayment);

            $status = match (true) {
                $totalBalance <= 0 => 'fully_paid',
                $downpayment > 0 => 'partially_paid',
                default => 'pending',
            };

            StudentTuition::query()->firstOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                ],
                [
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
                    'discount' => $discount,
                    'downpayment' => $downpayment,
                    'overall_tuition' => $finalTuition,
                    'paid' => $paid,
                ],
            );
        }

        $specialCases = [
            ['student_id' => 2021001, 'additional_payment' => 15000],
            ['student_id' => 2021003, 'additional_payment' => 20000],
            ['student_id' => 2024001, 'additional_payment' => 8000],
        ];

        foreach ($specialCases as $specialCase) {
            $tuition = StudentTuition::query()
                ->where('student_id', $specialCase['student_id'])
                ->whereIn('school_year', $currentSchoolYearVariants)
                ->where('semester', $currentSemester)
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

        $this->command?->info('Student tuitions seeded successfully!');
    }
}
