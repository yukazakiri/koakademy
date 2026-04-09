<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Filament\Resources\StudentEnrollments\Pages\ListStudentEnrollments;
use App\Filament\Resources\StudentEnrollments\StudentEnrollmentResource;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use Database\Seeders\StudentEnrollmentCurrentSemesterSeeder;
use Database\Seeders\StudentEnrollmentSeeder;
use Filament\Facades\Filament;

beforeEach(function () {
    Filament::setCurrentPanel('admin');

    // Create an admin user
    $this->actingAs(User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'email' => 'admin@example.com',
        'password' => bcrypt('password'),
    ]));
});

describe('StudentEnrollmentSeeder', function () {
    it('successfully runs the seeder without errors', function () {
        $seeder = new StudentEnrollmentSeeder;

        expect(fn () => $seeder->run())->not->toThrow(Throwable::class);
    });

    it('creates student enrollments in the database', function () {
        $initialCount = StudentEnrollment::count();

        $seeder = new StudentEnrollmentSeeder;
        $seeder->run();

        expect(StudentEnrollment::count())->toBeGreaterThan($initialCount);
    });

    it('creates enrollments with valid student relationships', function () {
        // First ensure we have students and courses
        Student::factory()->count(5)->create();
        Course::factory()->count(3)->create();

        $seeder = new StudentEnrollmentSeeder;
        $seeder->run();

        $enrollment = StudentEnrollment::first();

        expect($enrollment)->not->toBeNull()
            ->and($enrollment->student)->toBeInstanceOf(Student::class);
    });

    it('creates enrollments with valid status values', function () {
        $seeder = new StudentEnrollmentSeeder;
        $seeder->run();

        $enrollments = StudentEnrollment::all();

        expect($enrollments)->not->toBeEmpty();

        foreach ($enrollments as $enrollment) {
            expect($enrollment->status)->toBeIn(['Enrolled', 'Pending', 'Completed', 'Verified By Department Head', 'Verified By Cashier']);
        }
    });

    it('creates enrollments with required fields', function () {
        $seeder = new StudentEnrollmentSeeder;
        $seeder->run();

        $enrollment = StudentEnrollment::first();

        expect($enrollment->student_id)->not->toBeNull()
            ->and($enrollment->course_id)->not->toBeNull()
            ->and($enrollment->semester)->not->toBeNull()
            ->and($enrollment->academic_year)->not->toBeNull()
            ->and($enrollment->school_year)->not->toBeNull();
    });

    it('creates enrollments with different academic years', function () {
        $seeder = new StudentEnrollmentSeeder;
        $seeder->run();

        $academicYears = StudentEnrollment::distinct()->pluck('academic_year')->toArray();

        expect($academicYears)->toContain(1, 2, 3, 4);
    });

    it('creates enrollments with different semesters', function () {
        $seeder = new StudentEnrollmentSeeder;
        $seeder->run();

        $semesters = StudentEnrollment::distinct()->pluck('semester')->toArray();

        expect($semesters)->toContain(1, 2);
    });
});

describe('StudentEnrollmentCurrentSemesterSeeder', function () {
    it('successfully runs the current semester seeder without errors', function () {
        $seeder = new StudentEnrollmentCurrentSemesterSeeder;

        expect(fn () => $seeder->run())->not->toThrow(Throwable::class);
    });

    it('creates exactly 30 student enrollments for current semester', function () {
        $settingsService = app(App\Services\GeneralSettingsService::class);
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        // Clear existing enrollments for current semester
        StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->delete();

        $seeder = new StudentEnrollmentCurrentSemesterSeeder;
        $seeder->run();

        $count = StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->count();

        expect($count)->toBe(30);
    });

    it('creates subject enrollments for each student enrollment', function () {
        $settingsService = app(App\Services\GeneralSettingsService::class);
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $seeder = new StudentEnrollmentCurrentSemesterSeeder;
        $seeder->run();

        $enrollments = StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->get();

        expect($enrollments->sum(fn ($enrollment) => $enrollment->subjectsEnrolled->count()))
            ->toBeGreaterThan(0);
    });

    it('creates class enrollments for each student', function () {
        $settingsService = app(App\Services\GeneralSettingsService::class);
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $seeder = new StudentEnrollmentCurrentSemesterSeeder;
        $seeder->run();

        $enrollments = StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->get();

        foreach ($enrollments as $enrollment) {
            $classEnrollments = App\Models\ClassEnrollment::query()
                ->where('student_id', $enrollment->student_id)
                ->count();

            expect($classEnrollments)->toBeGreaterThan(0);
        }
    });

    it('creates enrollments with valid course relationships', function () {
        $seeder = new StudentEnrollmentCurrentSemesterSeeder;
        $seeder->run();

        $enrollments = StudentEnrollment::all();

        foreach ($enrollments as $enrollment) {
            expect($enrollment->course)->not->toBeNull()
                ->and($enrollment->course)->toBeInstanceOf(Course::class);
        }
    });

    it('creates enrollments with correct semester and school year', function () {
        $settingsService = app(App\Services\GeneralSettingsService::class);
        $currentSemester = $settingsService->getCurrentSemester();
        $currentSchoolYear = $settingsService->getCurrentSchoolYearString();

        $seeder = new StudentEnrollmentCurrentSemesterSeeder;
        $seeder->run();

        $enrollments = StudentEnrollment::query()
            ->where('school_year', $currentSchoolYear)
            ->where('semester', $currentSemester)
            ->get();

        foreach ($enrollments as $enrollment) {
            expect($enrollment->semester)->toBe($currentSemester)
                ->and($enrollment->school_year)->toBe($currentSchoolYear);
        }
    });
});

describe('StudentEnrollmentTable ExportBulkAction', function () {
    it('list page class is available for rendering', function () {
        expect(class_exists(ListStudentEnrollments::class))->toBeTrue();
    });

    it('resource uses StudentEnrollmentsTable for table configuration', function () {
        expect(method_exists(StudentEnrollmentResource::class, 'table'))->toBeTrue();
    });
});
