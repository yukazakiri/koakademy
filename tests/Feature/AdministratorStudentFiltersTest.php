<?php

declare(strict_types=1);

use App\Enums\StudentStatus;
use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Department;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\StudentStatusRecord;
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

it('provides the complete student dataset for client-side profile filters', function (): void {
    $technology = Department::factory()->forSchool($this->school)->create([
        'code' => 'TECH',
        'name' => 'Technology',
        'is_active' => false,
    ]);
    $business = Department::factory()->forSchool($this->school)->create([
        'code' => 'BUS',
        'name' => 'Business',
    ]);
    $informationTechnology = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $technology->id,
        'code' => 'BSIT',
        'title' => 'Information Technology',
        'is_active' => false,
    ]);
    $businessAdministration = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $business->id,
        'code' => 'BSBA',
        'title' => 'Business Administration',
    ]);
    $otherSchool = School::factory()->create();
    $otherDepartment = Department::factory()->forSchool($otherSchool)->create();
    Course::factory()->create([
        'school_id' => $otherSchool->id,
        'department_id' => $otherDepartment->id,
    ]);

    $technologyFirstYear = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $informationTechnology->id,
        'academic_year' => 1,
    ]);
    $technologySecondYear = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $informationTechnology->id,
        'academic_year' => 2,
    ]);
    $businessFirstYear = Student::factory()->create([
        'school_id' => $this->school->id,
        'institution_id' => $this->school->id,
        'course_id' => $businessAdministration->id,
        'academic_year' => 1,
    ]);

    actingAs($this->user)
        ->get(portalUrlForAdministrators("/administrators/students?course_id={$informationTechnology->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 3)
            ->where('students.data', fn ($students): bool => $students->pluck('id')->sort()->values()->all() === collect([
                $technologyFirstYear->id,
                $technologySecondYear->id,
                $businessFirstYear->id,
            ])->sort()->values()->all())
            ->where('filters.course_id', $informationTechnology->id)
        );

    actingAs($this->user)
        ->get(portalUrlForAdministrators("/administrators/students?department_id={$business->id}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 3)
            ->where('students.data', function ($students) use ($businessFirstYear, $businessAdministration, $business): bool {
                return $students->contains(
                    fn (array $student): bool => $student['id'] === $businessFirstYear->id
                        && $student['course_id'] === $businessAdministration->id
                        && $student['department_id'] === $business->id
                        && $student['year_level'] === 1
                );
            })
            ->where('filters.department_id', $business->id)
        );

    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?year_level=2'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 3)
            ->where('students.data', function ($students) use ($technologySecondYear): bool {
                return $students->contains(
                    fn (array $student): bool => $student['id'] === $technologySecondYear->id
                );
            })
            ->where('filters.year_level', 2)
            ->has('options.courses', 2)
            ->has('options.departments', 2)
            ->where('options.courses', fn ($courses): bool => $courses->contains(
                fn (array $course): bool => $course['value'] === (string) $informationTechnology->id
                    && $course['label'] === 'BSIT - Information Technology'
            ))
            ->where('options.departments', fn ($departments): bool => $departments->contains(
                fn (array $department): bool => $department['value'] === (string) $technology->id
                    && $department['label'] === 'TECH - Technology'
            ))
            ->where('options.year_levels.3.value', '4')
        );

    expect($technologyFirstYear->id)->not->toBe($businessFirstYear->id);
});

it('filters current enrollment using only the configured school year and semester', function (): void {
    $course = Course::factory()->create(['school_id' => $this->school->id]);
    $current = Student::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);
    $historical = Student::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);
    $applicant = Student::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);
    $withoutStatus = Student::factory()->create(['school_id' => $this->school->id, 'course_id' => $course->id]);

    StudentStatusRecord::query()->create([
        'school_id' => $this->school->id,
        'student_id' => $current->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'status' => StudentStatus::Enrolled,
    ]);
    StudentStatusRecord::query()->create([
        'school_id' => $this->school->id,
        'student_id' => $historical->id,
        'academic_year' => '2023 - 2024',
        'semester' => 2,
        'status' => StudentStatus::Enrolled,
    ]);
    StudentStatusRecord::query()->create([
        'school_id' => $this->school->id,
        'student_id' => $applicant->id,
        'academic_year' => '2024 - 2025',
        'semester' => 2,
        'status' => StudentStatus::Applicant,
    ]);

    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?current_enrollment=enrolled'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 4)
            ->where('students.data', function ($students) use ($current): bool {
                return $students->contains(
                    fn (array $student): bool => $student['id'] === $current->id
                        && $student['status'] === StudentStatus::Enrolled->value
                );
            })
            ->where('filters.current_enrollment', 'enrolled')
        );

    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?current_enrollment=not_enrolled'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 4)
            ->where('students.data', function ($students) use ($applicant, $current, $historical, $withoutStatus): bool {
                return $students->pluck('id')->sort()->values()->all() === collect([
                    $current->id,
                    $historical->id,
                    $applicant->id,
                    $withoutStatus->id,
                ])->sort()->values()->all();
            })
            ->where('filters.current_enrollment', 'not_enrolled')
        );
});

it('combines the new filters without changing global student totals', function (): void {
    $department = Department::factory()->forSchool($this->school)->create();
    $course = Course::factory()->create([
        'school_id' => $this->school->id,
        'department_id' => $department->id,
    ]);
    $matching = Student::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $course->id,
        'academic_year' => 3,
    ]);
    $wrongYear = Student::factory()->create([
        'school_id' => $this->school->id,
        'course_id' => $course->id,
        'academic_year' => 2,
    ]);

    foreach ([$matching, $wrongYear] as $student) {
        StudentStatusRecord::query()->create([
            'school_id' => $this->school->id,
            'student_id' => $student->id,
            'academic_year' => '2024 - 2025',
            'semester' => 2,
            'status' => StudentStatus::Enrolled,
        ]);
    }

    $query = http_build_query([
        'course_id' => $course->id,
        'department_id' => $department->id,
        'year_level' => 3,
        'current_enrollment' => 'enrolled',
    ]);

    actingAs($this->user)
        ->get(portalUrlForAdministrators("/administrators/students?{$query}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 2)
            ->where('students.data', function ($students) use ($matching, $wrongYear): bool {
                return $students->pluck('id')->sort()->values()->all() === collect([
                    $matching->id,
                    $wrongYear->id,
                ])->sort()->values()->all();
            })
            ->where('students.total', 2)
            ->where('stats.total_students', 2)
        );
});

it('ignores malformed relationship year and enrollment filter values', function (): void {
    Student::factory()->count(2)->create(['school_id' => $this->school->id]);

    actingAs($this->user)
        ->get(portalUrlForAdministrators('/administrators/students?course_id=-1&department_id=invalid&year_level=9&current_enrollment=unexpected'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('students.data', 2)
            ->where('filters.course_id', null)
            ->where('filters.department_id', null)
            ->where('filters.year_level', null)
            ->where('filters.current_enrollment', null)
        );
});
