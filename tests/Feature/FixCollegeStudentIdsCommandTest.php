<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Models\Student;
use Illuminate\Support\Facades\Artisan;

it('can analyze college students with 7 or 8 digit IDs', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create test students with 7-digit IDs
    Student::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065721, // 7 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    Student::create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'gender' => 'female',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065722, // 7 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    Student::create([
        'first_name' => 'Bob',
        'last_name' => 'Johnson',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065723, // 7 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Create a student with proper 6-digit ID
    Student::create([
        'first_name' => 'Alice',
        'last_name' => 'Wilson',
        'gender' => 'female',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 206001, // 6 digits - should not be affected
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run the command in dry-run mode
    $exitCode = Artisan::call('students:fix-college-ids', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Found 3 college students with 7 or 8 digit IDs');
    expect($output)->toContain('DRY RUN MODE');
    expect($output)->toContain('Conversion examples (sequential):');
    expect($output)->toContain('2065721 → 206002'); // Starts from 206002 (after existing 206001)
    expect($output)->toContain('2065722 → 206003');
    expect($output)->toContain('2065723 → 206004');
});

it('can convert 7-digit college student IDs to 6-digit format', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create test students with 7-digit IDs that convert to different 6-digit IDs
    Student::create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065721, // 7 digits → 206572
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    Student::create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'gender' => 'female',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065733, // 7 digits → 206573 (different)
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run the command to actually fix the IDs
    $exitCode = Artisan::call('students:fix-college-ids');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Successfully converted: 2 students');
    expect($output)->toContain('All college student IDs are now properly formatted');

    // Verify the IDs were converted correctly (sequential starting from next available)
    $john = Student::where('first_name', 'John')->first();
    $jane = Student::where('first_name', 'Jane')->first();

    expect($john->student_id)->toBe(206000);
    expect($jane->student_id)->toBe(206001);
    expect(mb_strlen((string) $john->student_id))->toBe(6);
    expect(mb_strlen((string) $jane->student_id))->toBe(6);
});

it('can convert 8-digit college student IDs to 6-digit format', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create test student with 8-digit ID
    Student::create([
        'first_name' => 'Test',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 20657210, // 8 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run the command to fix the ID
    $exitCode = Artisan::call('students:fix-college-ids');

    expect($exitCode)->toBe(0);

    // Verify the ID was converted correctly (sequential starting from 206000)
    $student = Student::where('first_name', 'Test')->first();
    expect($student->student_id)->toBe(206000);
    expect(mb_strlen((string) $student->student_id))->toBe(6);
});

it('handles students with already correct 6-digit IDs', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create student with proper 6-digit ID
    Student::create([
        'first_name' => 'Good',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 206001, // 6 digits - correct
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run the command
    $exitCode = Artisan::call('students:fix-college-ids');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('No college students with 7 or 8 digit IDs found');

    // Verify the ID was not changed
    $student = Student::where('first_name', 'Good')->first();
    expect($student->student_id)->toBe(206001);
});

it('does not affect non-college students', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create TESDA student with 7-digit ID
    Student::create([
        'first_name' => 'TESDA',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065721, // 7 digits
        'student_type' => StudentType::TESDA,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run the command
    $exitCode = Artisan::call('students:fix-college-ids');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('No college students with 7 or 8 digit IDs found');

    // Verify the TESDA student ID was not changed
    $student = Student::where('first_name', 'TESDA')->first();
    expect($student->student_id)->toBe(2065721);
});

it('shows proper conversion examples in dry-run mode', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create students with different ID lengths
    Student::create([
        'first_name' => 'Seven',
        'last_name' => 'Digit',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065721, // 7 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    Student::create([
        'first_name' => 'Eight',
        'last_name' => 'Digit',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 20657210, // 8 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run dry-run mode
    $exitCode = Artisan::call('students:fix-college-ids', ['--dry-run' => true]);

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Conversion examples (sequential):');
    expect($output)->toContain('2065721 → 206000'); // Will start from 206000 in test environment
    expect($output)->toContain('20657210 → 206001');
    expect($output)->toContain('DRY RUN MODE');
});

it('handles sequential assignment without conflicts', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create students with IDs that would have conflicted with the old approach
    Student::create([
        'first_name' => 'Student',
        'last_name' => 'One',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065721, // 7 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    Student::create([
        'first_name' => 'Student',
        'last_name' => 'Two',
        'gender' => 'female',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 2065722, // 7 digits
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Run the command
    $exitCode = Artisan::call('students:fix-college-ids');

    expect($exitCode)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('Successfully converted: 2 students');
    expect($output)->toContain('All college student IDs are now properly formatted');

    // Verify both students got sequential IDs
    $student1 = Student::where('first_name', 'Student')->where('last_name', 'One')->first();
    $student2 = Student::where('first_name', 'Student')->where('last_name', 'Two')->first();

    expect($student1->student_id)->toBe(206000);
    expect($student2->student_id)->toBe(206001);
    expect($student1->student_id)->not->toBe($student2->student_id);
});
