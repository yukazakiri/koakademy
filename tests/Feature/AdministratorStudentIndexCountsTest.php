<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\withoutVite;

beforeEach(function (): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);
    Cache::flush();
});

it('uses the paginator total as the global student total on the unfiltered students index', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->count(21)->create();

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 20)
            ->where('students.total', 21)
            ->where('stats.total_students', 21)
            ->where('adminSidebarCounts.students', 21)
        );
});

it('keeps the global student total when students index filters are active', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->count(3)->create([
        'student_type' => StudentType::College->value,
    ]);
    Student::factory()->count(2)->create([
        'student_type' => StudentType::SeniorHighSchool->value,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students?type=college'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 3)
            ->where('students.total', 3)
            ->where('stats.total_students', 5)
            ->where('adminSidebarCounts.students', 5)
        );
});

it('keeps the global student total when filters return no matching students', function (): void {
    GeneralSetting::factory()->create([
        'semester' => 2,
        'school_starting_date' => '2024-08-01',
        'school_ending_date' => '2025-05-31',
        'enable_clearance_check' => true,
    ]);

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Student::factory()->count(4)->create([
        'student_type' => StudentType::SeniorHighSchool->value,
    ]);

    actingAs($user)
        ->get(portalUrlForAdministrators('/administrators/students?type=college'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/students/index', false)
            ->has('students.data', 0)
            ->where('students.total', 0)
            ->where('stats.total_students', 4)
            ->where('adminSidebarCounts.students', 4)
        );
});
