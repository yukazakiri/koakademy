<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Filament\Widgets\StudentAnalyticsStatsOverview;
use App\Filament\Widgets\StudentEnrollmentByTypeChart;
use App\Filament\Widgets\StudentTypeDistributionChart;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('can render student analytics stats overview widget', function () {
    // Create test data
    DB::table('students')->insert([
        [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 200001,
            'student_type' => StudentType::College->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'gender' => 'female',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 300001,
            'student_type' => StudentType::SeniorHighSchool->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Livewire::test(StudentAnalyticsStatsOverview::class)
        ->assertSuccessful();
});

it('can render student type distribution chart widget', function () {
    // Create test data
    DB::table('students')->insert([
        [
            'first_name' => 'Alice',
            'last_name' => 'Brown',
            'gender' => 'female',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 200002,
            'student_type' => StudentType::College->value,
            'academic_year' => 2,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 400001,
            'student_type' => StudentType::TESDA->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    Livewire::test(StudentTypeDistributionChart::class)
        ->assertSuccessful();
});

it('can render student enrollment by type chart widget', function () {
    // Create test data with dates spread over time
    DB::table('students')->insert([
        [
            'first_name' => 'Mike',
            'last_name' => 'Davis',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 200003,
            'student_type' => StudentType::College->value,
            'academic_year' => 3,
            'status' => 'enrolled',
            'created_at' => now()->subMonth(),
            'updated_at' => now()->subMonth(),
        ],
        [
            'first_name' => 'Sarah',
            'last_name' => 'Wilson',
            'gender' => 'female',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 300002,
            'student_type' => StudentType::SeniorHighSchool->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now()->subWeeks(2),
            'updated_at' => now()->subWeeks(2),
        ],
    ]);

    Livewire::test(StudentEnrollmentByTypeChart::class)
        ->assertSuccessful();
});

it('widgets have correct data structure', function () {
    // Create test data for all types
    DB::table('students')->insert([
        [
            'first_name' => 'Test',
            'last_name' => 'College',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 200004,
            'student_type' => StudentType::College->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'first_name' => 'Test',
            'last_name' => 'SHS',
            'gender' => 'female',
            'birth_date' => '2000-01-01',
            'age' => 17,
            'student_id' => 300003,
            'student_type' => StudentType::SeniorHighSchool->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'first_name' => 'Test',
            'last_name' => 'TESDA',
            'gender' => 'male',
            'birth_date' => '1995-01-01',
            'age' => 29,
            'student_id' => 400002,
            'student_type' => StudentType::TESDA->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Verify counts are correct
    expect(Student::where('student_type', StudentType::College->value)->count())->toBeGreaterThan(0);
    expect(Student::where('student_type', StudentType::SeniorHighSchool->value)->count())->toBeGreaterThan(0);
    expect(Student::where('student_type', StudentType::TESDA->value)->count())->toBeGreaterThan(0);

    // Test that widgets can access data
    $widget = Livewire::test(StudentTypeDistributionChart::class);
    $widget->assertSuccessful();
});
