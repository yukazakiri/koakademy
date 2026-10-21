<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Seeder;

final class StudentEnrollmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        // The seeded enrollments have to sit in the running academic period.
        // The class seeder puts its classes there too, and the class enrolment
        // seeder matches the two on the period — so a hard-coded school year
        // left the classes with no students at all.
        $settings = app(GeneralSettingsService::class);
        $currentSchoolYear = $settings->getCurrentSchoolYearString();
        $currentSemester = $settings->getCurrentSemester();
        $previousSchoolYearStart = $settings->getCurrentSchoolYearStart() - 1;
        $previousSchoolYear = $previousSchoolYearStart.' - '.($previousSchoolYearStart + 1);
        $otherSemester = $currentSemester === 1 ? 2 : 1;

        $enrollments = [
            // Third Year Students (Current Enrollment)
            [
                'student_id' => '2021001',
                'course_id' => 1, // BSIT
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 3,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5000.00,
                'remarks' => 'Regular enrollment for 3rd year BSIT',
            ],
            [
                'student_id' => '2021002',
                'course_id' => 4, // BSBA-MM
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 3,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4500.00,
                'remarks' => 'Regular enrollment for 3rd year BSBA-MM',
            ],
            [
                'student_id' => '2021003',
                'course_id' => 2, // BSHM
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 3,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4800.00,
                'remarks' => 'Regular enrollment for 3rd year BSHM',
            ],

            // First Year Students (Current Enrollment)
            [
                'student_id' => '2024001',
                'course_id' => 1, // BSIT
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 6000.00,
                'remarks' => 'New student enrollment for 1st year BSIT',
            ],
            [
                'student_id' => '2024002',
                'course_id' => 4, // BSBA-MM
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5500.00,
                'remarks' => 'New student enrollment for 1st year BSBA-MM',
            ],

            // Second Year Students (Current Enrollment)
            [
                'student_id' => '2023001',
                'course_id' => 2, // BSHM
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 2,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5200.00,
                'remarks' => 'Regular enrollment for 2nd year BSHM',
            ],

            // Fourth Year Students (Current Enrollment)
            [
                'student_id' => '2020001',
                'course_id' => 1, // BSIT
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 4,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4000.00,
                'remarks' => 'Final year enrollment for BSIT',
            ],

            // Transferee Student
            [
                'student_id' => '2024003',
                'course_id' => 6, // BSCS
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 2,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5800.00,
                'remarks' => 'Transferee student enrollment for 2nd year BSCS',
            ],

            // Additional Student
            [
                'student_id' => '2022001',
                'course_id' => 5, // BSBA-FM
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 2,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4700.00,
                'remarks' => 'Regular enrollment for 2nd year BSBA-FM',
            ],

            // Previous Semester Enrollments (for testing historical data)
            [
                'student_id' => '2021001',
                'course_id' => 1, // BSIT
                'status' => 'Completed',
                'semester' => $currentSemester === 2 ? 1 : 2,
                'academic_year' => 2,
                'school_year' => $previousSchoolYear,
                'downpayment' => 4800.00,
                'remarks' => 'Previous semester enrollment - completed',
            ],
            [
                'student_id' => '2021002',
                'course_id' => 4, // BSBA-MM
                'status' => 'Completed',
                'semester' => $currentSemester === 2 ? 1 : 2,
                'academic_year' => 2,
                'school_year' => $previousSchoolYear,
                'downpayment' => 4300.00,
                'remarks' => 'Previous semester enrollment - completed',
            ],

            // Pending Enrollments (for testing workflow)
            [
                'student_id' => '2024001',
                'course_id' => 1, // BSIT
                'status' => 'Pending',
                'semester' => $otherSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 0.00,
                'remarks' => 'Pre-enrollment for 2nd semester - pending approval',
            ],
            [
                'student_id' => '2024002',
                'course_id' => 4, // BSBA-MM
                'status' => 'Pending',
                'semester' => $otherSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 0.00,
                'remarks' => 'Pre-enrollment for 2nd semester - pending approval',
            ],
        ];

        /** @var \Illuminate\Support\Collection<int, int> $courseIds */
        $courseIds = collect($enrollments)
            ->pluck('course_id')
            ->unique()
            ->values();

        foreach ($courseIds as $courseId) {
            Course::query()->firstOrCreate(
                ['id' => $courseId],
                [
                    'code' => "COURSE-{$courseId}",
                    'title' => "Seeded Course {$courseId}",
                    'department' => 'General',
                    'year_level' => 1,
                    'semester' => 1,
                    'is_active' => true,
                ]
            );
        }

        foreach ($enrollments as $enrollment) {
            $studentId = (int) $enrollment['student_id'];

            if (! Student::query()->whereKey($studentId)->exists()) {
                Student::factory()->create([
                    'id' => $studentId,
                    'student_id' => $studentId,
                    'course_id' => $enrollment['course_id'],
                    'email' => "student{$studentId}@example.com",
                ]);
            }

            StudentEnrollment::query()->create(array_merge($enrollment, [
                'school_id' => $schoolId,
            ]));
        }

        $this->command->info('Student enrollments seeded successfully!');
    }
}
