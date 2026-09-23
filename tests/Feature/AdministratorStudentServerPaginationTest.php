<?php

declare(strict_types=1);

use App\Enums\EmploymentStatus;
use App\Enums\ScholarshipType;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentClearance;
use App\Models\User;
use App\Services\TenantContext;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    Cache::flush();

    $this->school = School::factory()->create();
    app(TenantContext::class)->setCurrentSchool($this->school);

    GeneralSetting::factory()->create([
        'school_id' => $this->school->id,
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $this->user = User::factory()->create([
        'role' => UserRole::Admin,
        'school_id' => $this->school->id,
    ]);
});

it('paginates students on the server and navigates between pages', function (): void {
    Student::factory()->count(25)->create([
        'school_id' => $this->school->id,
    ]);

    // Page 1 with per_page = 10
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?page=1&per_page=10'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 10)
            ->where('students.total', 25)
            ->where('students.current_page', 1)
            ->where('students.last_page', 3)
            ->where('students.per_page', 10)
            ->where('students.from', 1)
            ->where('students.to', 10)
        );

    // Page 2 with per_page = 10
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?page=2&per_page=10'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 10)
            ->where('students.total', 25)
            ->where('students.current_page', 2)
            ->where('students.last_page', 3)
            ->where('students.from', 11)
            ->where('students.to', 20)
        );

    // Page 3 with per_page = 10
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?page=3&per_page=10'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 5)
            ->where('students.total', 25)
            ->where('students.current_page', 3)
            ->where('students.last_page', 3)
            ->where('students.from', 21)
            ->where('students.to', 25)
        );
});

it('sorts students on the server by name and student ID in both directions', function (): void {
    $alice = Student::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => 10001,
        'first_name' => 'Alice',
        'last_name' => 'Adams',
    ]);
    $charlie = Student::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => 10003,
        'first_name' => 'Charlie',
        'last_name' => 'Clark',
    ]);
    $bob = Student::factory()->create([
        'school_id' => $this->school->id,
        'student_id' => 10002,
        'first_name' => 'Bob',
        'last_name' => 'Baker',
    ]);

    // Name ascending
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?sort=name&direction=asc'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->where('students.data.0.id', $alice->id)
            ->where('students.data.1.id', $bob->id)
            ->where('students.data.2.id', $charlie->id)
        );

    // Name descending
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?sort=name&direction=desc'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->where('students.data.0.id', $charlie->id)
            ->where('students.data.1.id', $bob->id)
            ->where('students.data.2.id', $alice->id)
        );

    // Student ID ascending
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?sort=student_id&direction=asc'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->where('students.data.0.id', $alice->id)
            ->where('students.data.1.id', $bob->id)
            ->where('students.data.2.id', $charlie->id)
        );
});

it('filters students on the server by scholarship, employment, and indigenous status', function (): void {
    $indigenousScholar = Student::factory()->create([
        'school_id' => $this->school->id,
        'is_indigenous_person' => true,
        'scholarship_type' => ScholarshipType::TES->value,
        'employment_status' => EmploymentStatus::Employed->value,
    ]);
    $regularStudent = Student::factory()->create([
        'school_id' => $this->school->id,
        'is_indigenous_person' => false,
        'scholarship_type' => null,
        'employment_status' => EmploymentStatus::Unemployed->value,
    ]);

    // Filter by indigenous status
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?is_indigenous_person=yes'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $indigenousScholar->id)
        );

    // Filter by scholarship
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?scholarship_type='.urlencode(ScholarshipType::TES->value)))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $indigenousScholar->id)
        );

    // Filter by employment
    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?employment_status='.urlencode(EmploymentStatus::Unemployed->value)))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $regularStudent->id)
        );
});

it('filters students on the server by clearance status', function (): void {
    $clearedStudent = Student::factory()->create(['school_id' => $this->school->id]);
    $pendingStudent = Student::factory()->create(['school_id' => $this->school->id]);

    StudentClearance::query()->create([
        'student_id' => $clearedStudent->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'is_cleared' => true,
    ]);

    StudentClearance::query()->create([
        'student_id' => $pendingStudent->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'is_cleared' => false,
    ]);

    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?previous_semester_cleared=true'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $clearedStudent->id)
        );

    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?previous_semester_cleared=false'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 1)
            ->where('students.data.0.id', $pendingStudent->id)
        );
});
