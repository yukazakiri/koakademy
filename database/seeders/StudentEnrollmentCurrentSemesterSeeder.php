<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollStat;
use App\Enums\StudentStatus;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\StudentStatusRecord;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class StudentEnrollmentCurrentSemesterSeeder extends Seeder
{
    /**
     * Seed current-semester and historical enrollments for all seeded students.
     *
     * Subject enrollments and class enrollments are handled by SubjectEnrollmentSeeder
     * and ClassEnrollmentSeeder right after this seeder runs.
     */
    public function run(): void
    {
        $school = School::query()->first();
        $schoolId = $school?->id ?? 1;

        $settingsService = app(GeneralSettingsService::class);
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();
        $currentStartYear = $settingsService->getCurrentSchoolYearStart();

        $this->logInfo("Seeding enrollments for: {$currentSchoolYear}, Semester {$currentSemester}");

        $students = Student::withoutSchoolScope()
            ->whereNotNull('course_id')
            ->orderBy('id')
            ->get();

        if ($students->isEmpty()) {
            $this->logInfo('No students found to enroll.');

            return;
        }

        $workflowStatuses = [
            EnrollStat::Pending->value,
            EnrollStat::VerifiedByDeptHead->value,
            EnrollStat::VerifiedByCashier->value,
        ];

        $createdCount = 0;

        DB::transaction(function () use (
            $students,
            $schoolId,
            $currentSemester,
            $currentSchoolYear,
            $currentStartYear,
            $workflowStatuses,
            &$createdCount,
        ): void {
            foreach ($students as $index => $student) {
                $studentStatus = $student->status instanceof StudentStatus
                    ? $student->status->value
                    : (string) ($student->status ?: StudentStatus::Enrolled->value);

                StudentStatusRecord::withoutSchoolScope()->firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'academic_year' => $currentSchoolYear,
                        'semester' => $currentSemester,
                    ],
                    [
                        'status' => $studentStatus,
                        'school_id' => $schoolId,
                    ],
                );

                // Skip students that already have enrollments from StudentEnrollmentSeeder
                $hasExistingEnrollments = StudentEnrollment::withoutSchoolScope()
                    ->where('student_id', (string) $student->id)
                    ->exists();

                if ($hasExistingEnrollments) {
                    continue;
                }

                // Applicants have not yet enrolled in subjects
                if ($studentStatus === StudentStatus::Applicant->value) {
                    continue;
                }

                $courseId = (int) $student->course_id;
                $academicYear = max(1, min(4, (int) ($student->academic_year ?: 1)));

                if ($studentStatus === StudentStatus::Graduated->value) {
                    // Graduated students have completed all 4 academic years (8 semesters)
                    for ($year = 1; $year <= 4; $year++) {
                        $yearsAgo = 5 - $year;
                        $histStart = $currentStartYear - $yearsAgo;
                        $historicalSchoolYear = $histStart.' - '.($histStart + 1);

                        for ($sem = 1; $sem <= 2; $sem++) {
                            StudentEnrollment::withoutSchoolScope()->firstOrCreate(
                                [
                                    'student_id' => (string) $student->id,
                                    'school_year' => $historicalSchoolYear,
                                    'semester' => $sem,
                                ],
                                [
                                    'course_id' => $courseId,
                                    'status' => 'Completed',
                                    'terminal_outcome' => 'completed',
                                    'academic_year' => $year,
                                    'downpayment' => 5000.00,
                                    'remarks' => sprintf('Completed Year %d Semester %d enrollment', $year, $sem),
                                    'school_id' => $schoolId,
                                ],
                            );
                        }
                    }

                    continue;
                }

                // Continuing enrolled students: seed completed enrollments for all prior years & semesters
                for ($year = 1; $year <= $academicYear; $year++) {
                    for ($sem = 1; $sem <= 2; $sem++) {
                        if ($year === $academicYear && $sem >= $currentSemester) {
                            break;
                        }

                        $yearsAgo = $academicYear - $year;
                        $histStart = $currentStartYear - $yearsAgo;
                        $historicalSchoolYear = $histStart.' - '.($histStart + 1);

                        StudentEnrollment::withoutSchoolScope()->firstOrCreate(
                            [
                                'student_id' => (string) $student->id,
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

                // Seed current-semester enrollment: demo student & ~80% are fully 'Enrolled', ~20% exercise workflow queues
                $isDemoStudent = $student->email === 'john.student@student.koakademy.edu';
                $enrollmentStatus = ($isDemoStudent || $index % 5 !== 4)
                    ? 'Enrolled'
                    : $workflowStatuses[$index % count($workflowStatuses)];

                $enrollment = StudentEnrollment::withoutSchoolScope()->firstOrCreate(
                    [
                        'student_id' => (string) $student->id,
                        'school_year' => $currentSchoolYear,
                        'semester' => $currentSemester,
                    ],
                    [
                        'course_id' => $courseId,
                        'status' => $enrollmentStatus,
                        'academic_year' => $academicYear,
                        'downpayment' => random_int(3500, 7500),
                        'remarks' => sprintf('Enrollment for Year %d Semester %d (%s)', $academicYear, $currentSemester, $currentSchoolYear),
                        'school_id' => $schoolId,
                    ],
                );

                if ($enrollment->wasRecentlyCreated) {
                    $createdCount++;
                }
            }
        });

        $this->logInfo("Successfully created {$createdCount} current-semester student enrollments!");
        $this->logInfo("Total enrollments for {$currentSchoolYear} Semester {$currentSemester}: ".
            StudentEnrollment::withoutSchoolScope()
                ->where('school_year', $currentSchoolYear)
                ->where('semester', $currentSemester)
                ->count());
    }

    private function logInfo(string $message): void
    {
        $this->command?->info($message);
    }
}
