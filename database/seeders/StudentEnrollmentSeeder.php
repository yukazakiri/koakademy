<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\StudentStatus;
use App\Models\Course;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;
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

        $settings = app(GeneralSettingsService::class);
        $currentSchoolYear = $settings->getCurrentSchoolYearString();
        $currentStartYear = $settings->getCurrentSchoolYearStart();
        $currentSemester = $settings->getCurrentSemester();
        $otherSemester = $currentSemester === 1 ? 2 : 1;

        $coursesByCode = Course::withoutSchoolScope()->pluck('id', 'code');

        $currentEnrollments = [
            // Third Year Students (Current Enrollment)
            [
                'student_id' => 2021001,
                'course_code' => 'BSIT',
                'course_id' => (int) ($coursesByCode['BSIT'] ?? 1),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 3,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5000.00,
                'remarks' => 'Regular enrollment for 3rd year BSIT',
            ],
            [
                'student_id' => 2021002,
                'course_code' => 'BSBA-MM',
                'course_id' => (int) ($coursesByCode['BSBA-MM'] ?? 4),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 3,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4500.00,
                'remarks' => 'Regular enrollment for 3rd year BSBA-MM',
            ],
            [
                'student_id' => 2021003,
                'course_code' => 'BSHM',
                'course_id' => (int) ($coursesByCode['BSHM'] ?? 2),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 3,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4800.00,
                'remarks' => 'Regular enrollment for 3rd year BSHM',
            ],

            // First Year Students (Current Enrollment)
            [
                'student_id' => 2024001,
                'course_code' => 'BSIT',
                'course_id' => (int) ($coursesByCode['BSIT'] ?? 1),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 6000.00,
                'remarks' => 'New student enrollment for 1st year BSIT',
            ],
            [
                'student_id' => 2024002,
                'course_code' => 'BSBA-MM',
                'course_id' => (int) ($coursesByCode['BSBA-MM'] ?? 4),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5500.00,
                'remarks' => 'New student enrollment for 1st year BSBA-MM',
            ],

            // Second Year Students (Current Enrollment)
            [
                'student_id' => 2023001,
                'course_code' => 'BSHM',
                'course_id' => (int) ($coursesByCode['BSHM'] ?? 2),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 2,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5200.00,
                'remarks' => 'Regular enrollment for 2nd year BSHM',
            ],

            // Fourth Year Students (Current Enrollment)
            [
                'student_id' => 2020001,
                'course_code' => 'BSIT',
                'course_id' => (int) ($coursesByCode['BSIT'] ?? 1),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 4,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4000.00,
                'remarks' => 'Final year enrollment for BSIT',
            ],

            // Transferee Student
            [
                'student_id' => 2024003,
                'course_code' => 'BSCS',
                'course_id' => (int) ($coursesByCode['BSCS'] ?? 6),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 2,
                'school_year' => $currentSchoolYear,
                'downpayment' => 5800.00,
                'remarks' => 'Transferee student enrollment for 2nd year BSCS',
            ],

            // Additional Student
            [
                'student_id' => 2022001,
                'course_code' => 'BSBA-FM',
                'course_id' => (int) ($coursesByCode['BSBA-FM'] ?? 5),
                'status' => 'Enrolled',
                'semester' => $currentSemester,
                'academic_year' => 2,
                'school_year' => $currentSchoolYear,
                'downpayment' => 4700.00,
                'remarks' => 'Regular enrollment for 2nd year BSBA-FM',
            ],
        ];

        $pendingEnrollments = [
            [
                'student_id' => 2024001,
                'course_id' => (int) ($coursesByCode['BSIT'] ?? 1),
                'status' => 'Pending',
                'semester' => $otherSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 0.00,
                'remarks' => 'Pre-enrollment for 2nd semester - pending approval',
            ],
            [
                'student_id' => 2024002,
                'course_id' => (int) ($coursesByCode['BSBA-MM'] ?? 4),
                'status' => 'Pending',
                'semester' => $otherSemester,
                'academic_year' => 1,
                'school_year' => $currentSchoolYear,
                'downpayment' => 0.00,
                'remarks' => 'Pre-enrollment for 2nd semester - pending approval',
            ],
        ];

        foreach ($currentEnrollments as $enrollment) {
            $courseId = (int) $enrollment['course_id'];

            if (! Course::withoutSchoolScope()->whereKey($courseId)->exists()) {
                $course = new Course([
                    'code' => $enrollment['course_code'],
                    'title' => 'Seeded Course '.$enrollment['course_code'],
                    'department' => 'General',
                    'year_level' => 4,
                    'semester' => 1,
                    'is_active' => true,
                    'school_id' => $schoolId,
                ]);
                $course->id = $courseId;
                $course->save();
            }

            $studentId = (int) $enrollment['student_id'];
            $student = Student::withoutSchoolScope()->find($studentId);

            if (! $student instanceof Student) {
                $student = Student::factory()->create([
                    'id' => $studentId,
                    'student_id' => $studentId,
                    'school_id' => $schoolId,
                    'institution_id' => $schoolId,
                    'student_type' => 'college',
                    'status' => StudentStatus::Enrolled->value,
                    'course_id' => $courseId,
                    'academic_year' => (int) $enrollment['academic_year'],
                    'email' => "student{$studentId}@student.koakademy.edu",
                ]);
            } else {
                $student->update([
                    'course_id' => $courseId,
                    'academic_year' => (int) $enrollment['academic_year'],
                    'status' => StudentStatus::Enrolled->value,
                ]);
            }

            StudentStatusRecord::withoutSchoolScope()->firstOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year' => $currentSchoolYear,
                    'semester' => $currentSemester,
                ],
                [
                    'status' => StudentStatus::Enrolled->value,
                    'school_id' => $schoolId,
                ],
            );

            // Seed completed historical enrollments for every prior academic year and semester
            $currentYearLevel = (int) $enrollment['academic_year'];
            for ($year = 1; $year <= $currentYearLevel; $year++) {
                for ($sem = 1; $sem <= 2; $sem++) {
                    if ($year === $currentYearLevel && $sem >= $currentSemester) {
                        break;
                    }

                    $yearsAgo = $currentYearLevel - $year;
                    $histStart = $currentStartYear - $yearsAgo;
                    $historicalSchoolYear = $histStart.' - '.($histStart + 1);

                    StudentEnrollment::withoutSchoolScope()->firstOrCreate(
                        [
                            'student_id' => (string) $studentId,
                            'school_year' => $historicalSchoolYear,
                            'semester' => $sem,
                        ],
                        [
                            'course_id' => $courseId,
                            'status' => 'Completed',
                            'terminal_outcome' => 'completed',
                            'academic_year' => $year,
                            'downpayment' => 4800.00,
                            'remarks' => sprintf('Completed Year %d Semester %d enrollment', $year, $sem),
                            'school_id' => $schoolId,
                        ],
                    );
                }
            }

            // Seed current semester enrollment
            StudentEnrollment::withoutSchoolScope()->firstOrCreate(
                [
                    'student_id' => (string) $studentId,
                    'school_year' => $enrollment['school_year'],
                    'semester' => $enrollment['semester'],
                ],
                [
                    'course_id' => $courseId,
                    'status' => $enrollment['status'],
                    'academic_year' => $currentYearLevel,
                    'downpayment' => $enrollment['downpayment'],
                    'remarks' => $enrollment['remarks'],
                    'school_id' => $schoolId,
                ],
            );
        }

        foreach ($pendingEnrollments as $pending) {
            StudentEnrollment::withoutSchoolScope()->firstOrCreate(
                [
                    'student_id' => (string) $pending['student_id'],
                    'school_year' => $pending['school_year'],
                    'semester' => $pending['semester'],
                ],
                [
                    'course_id' => $pending['course_id'],
                    'status' => $pending['status'],
                    'academic_year' => $pending['academic_year'],
                    'downpayment' => $pending['downpayment'],
                    'remarks' => $pending['remarks'],
                    'school_id' => $schoolId,
                ],
            );
        }

        $this->command?->info('Student enrollments seeded successfully!');
    }
}
