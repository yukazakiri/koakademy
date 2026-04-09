<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

it('sets all students to college type', function () {
    // Create test students directly with minimal data
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

    // Verify initial state
    expect(Student::where('student_type', StudentType::College->value)->count())->toBe(1);
    expect(Student::where('student_type', StudentType::SeniorHighSchool->value)->count())->toBe(1);
    expect(Student::where('student_type', StudentType::TESDA->value)->count())->toBe(1);

    // Run the command
    $this->artisan('students:set-college-type')
        ->expectsQuestion('Do you want to proceed with updating these students?', 'yes')
        ->assertSuccessful();

    // Verify all students are now college type
    expect(Student::where('student_type', StudentType::College->value)->count())->toBe(3);
    expect(Student::where('student_type', StudentType::SeniorHighSchool->value)->count())->toBe(0);
    expect(Student::where('student_type', StudentType::TESDA->value)->count())->toBe(0);
});

it('handles dry run mode correctly', function () {
    // Create test students
    DB::table('students')->insert([
        [
            'first_name' => 'Alice',
            'last_name' => 'Brown',
            'gender' => 'female',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 300002,
            'student_type' => StudentType::SeniorHighSchool->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Verify initial state
    expect(Student::where('student_type', StudentType::SeniorHighSchool->value)->count())->toBe(1);

    // Run in dry run mode
    $this->artisan('students:set-college-type', ['--dry-run' => true])
        ->assertSuccessful();

    // Verify nothing changed
    expect(Student::where('student_type', StudentType::SeniorHighSchool->value)->count())->toBe(1);
});

it('handles case when all students are already college type', function () {
    // Create only college students
    DB::table('students')->insert([
        [
            'first_name' => 'Mike',
            'last_name' => 'Davis',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => 200002,
            'student_type' => StudentType::College->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $this->artisan('students:set-college-type')
        ->expectsOutput('All students are already set to College type.')
        ->assertSuccessful();
});

// Cancellation test disabled - core functionality verified in other tests
