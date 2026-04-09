<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Filament\Widgets\RecentStudentRegistrationsTable;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

it('can render recent student registrations table with different student types', function () {
    // Create test data with various student types
    DB::table('students')->insert([
        [
            'first_name' => 'College',
            'last_name' => 'Student',
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
            'first_name' => 'SHS',
            'last_name' => 'Student',
            'gender' => 'female',
            'birth_date' => '2005-01-01',
            'age' => 19,
            'student_id' => 300001,
            'student_type' => StudentType::SeniorHighSchool->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ],
        [
            'first_name' => 'TESDA',
            'last_name' => 'Student',
            'gender' => 'male',
            'birth_date' => '1995-01-01',
            'age' => 29,
            'student_id' => 400001,
            'student_type' => StudentType::TESDA->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now()->subHours(2),
            'updated_at' => now()->subHours(2),
        ],
    ]);

    // Test that the table widget can render without errors
    $component = Livewire::test(RecentStudentRegistrationsTable::class);
    $component->assertSuccessful();
});

it('handles student type enum casting correctly in table columns', function () {
    // Create a student with enum type
    $student = Student::create([
        'first_name' => 'Test',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200002,
        'student_type' => StudentType::College,  // Pass enum directly
        'academic_year' => 2,
        'status' => 'enrolled',
    ]);

    // Verify the student was created with the correct type
    expect($student->student_type)->toBeInstanceOf(StudentType::class);
    expect($student->student_type)->toBe(StudentType::College);

    // Test that the table can handle the enum correctly
    $component = Livewire::test(RecentStudentRegistrationsTable::class);
    $component->assertSuccessful();

    // Check that our test student exists in the results
    $students = Student::latest('created_at')->limit(20)->get();
    expect($students->pluck('id'))->toContain($student->id);
});
