<?php

declare(strict_types=1);

use App\Enums\SubjectEnrolledEnum;
use App\Models\Course;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectEnrollment;
use App\Models\User;
use App\Services\StudentChecklistService;
use Database\Seeders\ClassEnrollmentSeeder;
use Database\Seeders\ClassSeeder;
use Database\Seeders\CourseSeeder;
use Database\Seeders\FacultySeeder;
use Database\Seeders\GeneralSettingSeeder;
use Database\Seeders\RolesSeeder;
use Database\Seeders\RoomSeeder;
use Database\Seeders\ScheduleSeeder;
use Database\Seeders\SchoolDepartmentSeeder;
use Database\Seeders\StudentEnrollmentCurrentSemesterSeeder;
use Database\Seeders\StudentEnrollmentSeeder;
use Database\Seeders\StudentRelatedTablesSeeder;
use Database\Seeders\StudentSeeder;
use Database\Seeders\SubjectEnrollmentSeeder;
use Database\Seeders\SubjectSeeder;
use Database\Seeders\UserSeeder;

use function Pest\Laravel\actingAs;

it('seeds a complete 4-year 2-semester curriculum for all 12 courses with valid prerequisites and no factory garbage', function (): void {
    $this->seed([
        GeneralSettingSeeder::class,
        SchoolDepartmentSeeder::class,
        CourseSeeder::class,
        SubjectSeeder::class,
    ]);

    $courses = Course::withoutSchoolScope()->orderBy('code')->get();

    expect($courses)->toHaveCount(12);

    foreach ($courses as $course) {
        $subjects = Subject::withoutSchoolScope()
            ->where('course_id', $course->id)
            ->get();

        // Every program has 40 subjects spanning Years 1..4 and Semesters 1..2 (5 per semester)
        expect($subjects)->toHaveCount(40, "Course {$course->code} should have 40 curriculum subjects")
            ->and($subjects->pluck('academic_year')->unique()->sort()->values()->all())->toBe([1, 2, 3, 4])
            ->and($subjects->pluck('semester')->unique()->sort()->values()->all())->toBe([1, 2])
            ->and((int) $course->units)->toBe((int) $subjects->sum('units'));

        $subjectIdsInCourse = $subjects->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ([1, 2, 3, 4] as $year) {
            foreach ([1, 2] as $semester) {
                $semesterSubjects = $subjects->where('academic_year', $year)->where('semester', $semester);
                expect($semesterSubjects)->toHaveCount(5, "Course {$course->code} Y{$year}S{$semester} should have 5 subjects");
            }
        }

        foreach ($subjects as $subject) {
            expect($subject->academic_year)->not->toBeNull()
                ->and($subject->classification)->toBe(SubjectEnrolledEnum::INTERNAL)
                ->and($subject->is_credited)->toBeTrue()
                ->and($subject->units)->toBe($subject->lecture + $subject->laboratory)
                ->and($subject->code)->not->toMatch('/^(HIST|SCI|PE|IT|MATH|ENG)101\d{3,}$/');

            if ($subject->pre_riquisite !== null) {
                expect($subject->pre_riquisite)->toBeArray()->not->toBeEmpty();
                foreach ($subject->pre_riquisite as $prereqId) {
                    expect($prereqId)->toBeInt()
                        ->and(in_array($prereqId, $subjectIdsInCourse, true))->toBeTrue(
                            "Prerequisite {$prereqId} on {$course->code} {$subject->code} must belong to the same course",
                        );
                }
            }
        }
    }
});

it('seeds realistic student checklists, passing historical grades, and current class schedules across all programs', function (): void {
    $this->seed([
        GeneralSettingSeeder::class,
        SchoolDepartmentSeeder::class,
        RolesSeeder::class,
        UserSeeder::class,
        CourseSeeder::class,
        RoomSeeder::class,
        FacultySeeder::class,
        SubjectSeeder::class,
        StudentRelatedTablesSeeder::class,
        StudentSeeder::class,
        ClassSeeder::class,
        ScheduleSeeder::class,
        StudentEnrollmentSeeder::class,
        StudentEnrollmentCurrentSemesterSeeder::class,
        SubjectEnrollmentSeeder::class,
        ClassEnrollmentSeeder::class,
    ]);

    // Ensure StudentEnrollmentCurrentSemesterSeeder did not inject any random factory subjects
    expect(Subject::withoutSchoolScope()->count())->toBe(12 * 40);

    // Demo student has a linked Student record, completed Y1/Y2 grades, and current Y3S1 classes with schedules
    $demoStudent = Student::withoutSchoolScope()
        ->where('email', 'john.student@student.koakademy.edu')
        ->first();

    expect($demoStudent)->not->toBeNull()
        ->and((int) $demoStudent?->academic_year)->toBe(3);

    $analysis = app(StudentChecklistService::class)->analyze($demoStudent);

    expect($analysis['error'] ?? false)->toBeFalse()
        ->and($analysis['academic_summary']['total_curriculum_subjects'])->toBe(40)
        ->and($analysis['academic_summary']['completed_subjects_count'])->toBe(20)
        ->and($analysis['academic_summary']['in_progress_subjects_count'])->toBe(5)
        ->and($analysis['academic_summary']['failed_subjects_count'])->toBe(0)
        ->and($analysis['academic_summary']['cumulative_gwa'])->toBeGreaterThanOrEqual(75.0);

    // Student portal view also sees published historical grades via GradeRelease snapshots
    $studentViewAnalysis = app(StudentChecklistService::class)->analyze($demoStudent, [], studentView: true);
    expect($studentViewAnalysis['academic_summary']['completed_subjects_count'])->toBe(20);

    // All graded subject enrollments use the 0-100 scale and pass
    $gradedEnrollments = SubjectEnrollment::withoutSchoolScope()->whereNotNull('grade')->get();
    expect($gradedEnrollments)->not->toBeEmpty();
    foreach ($gradedEnrollments as $se) {
        expect((float) $se->grade)->toBeBetween(75.0, 100.0)
            ->and($se->grade_outcome)->toBe('pass');
    }

    // Administrator student show page renders all 4 years and 2 semesters per year plus current class schedules
    $admin = User::query()->where('email', 'admin@koakademy.edu')->firstOrFail();

    actingAs($admin)
        ->get(route('administrators.students.show', $demoStudent))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/students/show')
            ->has('student.checklist', 4)
            ->has('student.checklist.0.semesters', 2)
            ->has('student.checklist.1.semesters', 2)
            ->has('student.checklist.2.semesters', 2)
            ->has('student.checklist.3.semesters', 2)
            ->has('student.current_enrolled_classes', 5),
        );
});
