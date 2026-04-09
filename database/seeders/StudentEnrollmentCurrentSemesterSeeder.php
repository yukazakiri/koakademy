<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnrollStat;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Services\GeneralSettingsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class StudentEnrollmentCurrentSemesterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $faker = fake();

        // Get current academic period from settings
        $settingsService = app(GeneralSettingsService::class);
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $this->logInfo("Seeding enrollments for: {$currentSchoolYear}, Semester {$currentSemester}");

        // Ensure we have courses
        $courses = Course::query()->take(5)->get();
        if ($courses->isEmpty()) {
            $courses = Course::factory()->count(5)->create();
            $this->logInfo('Created 5 courses');
        }

        // Ensure we have subjects for each course
        foreach ($courses as $course) {
            $existingSubjects = Subject::query()->where('course_id', $course->id)->count();
            if ($existingSubjects < 3) {
                Subject::factory()->count(5)->create([
                    'course_id' => $course->id,
                    'semester' => $currentSemester,
                    'academic_year' => $faker->numberBetween(1, 4),
                ]);
            }
        }

        // Ensure we have classes for current semester
        $classesCount = Classes::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->count();

        if ($classesCount < 10) {
            foreach ($courses as $course) {
                $subjects = Subject::query()->where('course_id', $course->id)->take(3)->get();
                foreach ($subjects as $subject) {
                    // Get or create a faculty
                    $faculty = \App\Models\Faculty::query()->first();
                    if (! $faculty) {
                        $faculty = \App\Models\Faculty::factory()->create();
                    }

                    // Get or create a room
                    $room = \App\Models\Room::query()->first();
                    if (! $room) {
                        $room = \App\Models\Room::factory()->create();
                    }

                    Classes::factory()->create([
                        'subject_id' => $subject->id,
                        'subject_code' => $subject->code,
                        'faculty_id' => $faculty->id,
                        'school_year' => $currentSchoolYear,
                        'semester' => $currentSemester,
                        'academic_year' => $subject->academic_year,
                        'course_codes' => [(string) $course->id],
                        'room_id' => $room->id,
                    ]);
                }
            }
            $this->logInfo('Created classes for current semester');
        }

        // Create 30 students and their enrollments
        $statuses = [
            EnrollStat::Pending->value,
            EnrollStat::VerifiedByDeptHead->value,
            EnrollStat::VerifiedByCashier->value,
        ];

        $academicYears = [1, 2, 3, 4];

        DB::transaction(function () use ($courses, $currentSemester, $currentSchoolYear, $statuses, $academicYears, $faker): void {
            for ($i = 1; $i <= 30; $i++) {
                $course = $courses->random();

                // Create student with unique ID
                $studentId = 2024000 + $i;
                $student = Student::query()->where('id', $studentId)->first();

                if (! $student) {
                    $student = Student::factory()->create([
                        'id' => $studentId,
                        'student_id' => $studentId,
                        'first_name' => 'Student'.$i,
                        'last_name' => 'Test'.$i,
                        'email' => "student{$i}@test.com",
                        'course_id' => $course->id,
                    ]);
                }

                // Assign course and academic year
                $academicYear = $academicYears[array_rand($academicYears)];
                $status = $statuses[array_rand($statuses)];

                // Create student enrollment
                $enrollment = StudentEnrollment::query()->firstOrCreate(
                    [
                        'student_id' => $student->id,
                        'school_year' => $currentSchoolYear,
                        'semester' => $currentSemester,
                    ],
                    [
                        'course_id' => $course->id,
                        'status' => $status,
                        'academic_year' => $academicYear,
                        'downpayment' => random_int(3000, 8000),
                        'remarks' => "Seeded enrollment #{$i} for {$currentSchoolYear} semester {$currentSemester}",
                    ]
                );

                // Get available subjects for this course and academic year
                $availableSubjects = Subject::query()
                    ->where('course_id', $course->id)
                    ->where('academic_year', '<=', $academicYear)
                    ->where('semester', $currentSemester)
                    ->take(5)
                    ->get();

                // Create subject enrollments
                foreach ($availableSubjects as $subject) {
                    SubjectEnrollment::query()->firstOrCreate(
                        [
                            'enrollment_id' => $enrollment->id,
                            'subject_id' => $subject->id,
                        ],
                        [
                            'student_id' => $student->id,
                            'academic_year' => $academicYear,
                            'school_year' => $currentSchoolYear,
                            'semester' => $currentSemester,
                            'remarks' => 'Auto-enrolled via seeder',
                            'enrolled_lecture_units' => $subject->lecture,
                            'enrolled_laboratory_units' => $subject->laboratory,
                        ]
                    );
                }

                // Get classes for current semester that match the course
                $availableClasses = Classes::query()
                    ->where('school_year', $currentSchoolYear)
                    ->where('semester', $currentSemester)
                    ->whereJsonContains('course_codes', (string) $course->id)
                    ->take(3)
                    ->get();

                // Create class enrollments
                foreach ($availableClasses as $class) {
                    ClassEnrollment::query()->firstOrCreate(
                        [
                            'class_id' => $class->id,
                            'student_id' => $student->id,
                        ],
                        [
                            'status' => true,
                            'remarks' => 'Auto-enrolled via seeder',
                            'prelim_grade' => $faker->optional(0.3)->randomFloat(2, 70, 95),
                            'midterm_grade' => $faker->optional(0.3)->randomFloat(2, 70, 95),
                            'finals_grade' => $faker->optional(0.3)->randomFloat(2, 70, 95),
                        ]
                    );
                }
            }
        });

        $this->logInfo('Successfully created 30 student enrollments with subjects and classes!');
        $this->logInfo("Total enrollments for {$currentSchoolYear} Semester {$currentSemester}: ".
            StudentEnrollment::query()
                ->where('school_year', $currentSchoolYear)
                ->where('semester', $currentSemester)
                ->count());
    }

    private function logInfo(string $message): void
    {
        $this->command->info($message);
    }
}
