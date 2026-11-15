<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\DocumentLocation;
use App\Models\GeneralSetting;
use App\Models\GradeReleaseClearance;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\StudentStatusRecord;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
});

it('shows current semester grade-release clearance status for listed students', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $studentCleared = Student::factory()->create([
        'first_name' => 'Aaron',
        'last_name' => 'Alpha',
    ]);
    $studentPending = Student::factory()->create([
        'first_name' => 'Betty',
        'last_name' => 'Beta',
    ]);
    $studentNoRecord = Student::factory()->create([
        'first_name' => 'Carl',
        'last_name' => 'Gamma',
    ]);

    GradeReleaseClearance::query()->create([
        'student_id' => $studentCleared->id,
        'school_year' => '2024 - 2025',
        'semester' => 2,
        'is_cleared' => true,
    ]);

    GradeReleaseClearance::query()->create([
        'student_id' => $studentPending->id,
        'school_year' => '2024 - 2025',
        'semester' => 2,
        'is_cleared' => false,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 3)
            ->where('students.data', function ($students) use ($studentCleared, $studentPending, $studentNoRecord): bool {
                return $students->pluck('previous_sem_clearance', 'id')->all() === [
                    $studentNoRecord->id => 'no_record',
                    $studentPending->id => 'not_cleared',
                    $studentCleared->id => 'cleared',
                ];
            })
        );
});

it('does not treat retired enrollment-clearance records as grade-release clearance', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => false,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $student = Student::factory()->create([
        'first_name' => 'Dina',
        'last_name' => 'Delta',
    ]);

    StudentClearance::query()->create([
        'student_id' => $student->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'is_cleared' => true,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 1)
            ->where('students.data.0.previous_sem_clearance', 'no_record')
        );
});

it('uses current semester status records for student list', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $studentEnrolled = Student::factory()->create([
        'first_name' => 'Evan',
        'last_name' => 'Epsilon',
        'status' => StudentStatus::Applicant,
    ]);
    $studentGraduated = Student::factory()->create([
        'first_name' => 'Faye',
        'last_name' => 'Zeta',
        'status' => StudentStatus::Applicant,
    ]);

    StudentStatusRecord::query()->create([
        'student_id' => $studentEnrolled->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'status' => StudentStatus::Enrolled,
    ]);

    StudentStatusRecord::query()->create([
        'student_id' => $studentGraduated->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'status' => StudentStatus::Graduated,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 2)
            ->where('students.data', function ($students) use ($studentEnrolled, $studentGraduated): bool {
                return $students->pluck('status', 'id')->all() === [
                    $studentGraduated->id => StudentStatus::Graduated->value,
                    $studentEnrolled->id => StudentStatus::Enrolled->value,
                ];
            })
        );
});

it('includes avatar urls for listed students', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    $documentLocation = DocumentLocation::query()->create([
        'picture_1x1' => '/storage/students/avatar-1.png',
    ]);

    $studentWithAvatar = Student::factory()->create([
        'first_name' => 'Ari',
        'last_name' => 'Aardvark',
        'document_location_id' => $documentLocation->id,
    ]);

    $studentWithoutAvatar = Student::factory()->create([
        'first_name' => 'Zoe',
        'last_name' => 'Zephyr',
        'document_location_id' => null,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 2)
            ->where('students.data', function ($students) use ($studentWithAvatar, $studentWithoutAvatar): bool {
                return $students->pluck('avatar_url', 'id')->all() === [
                    $studentWithoutAvatar->id => null,
                    $studentWithAvatar->id => '/storage/students/avatar-1.png',
                ];
            })
        );
});
