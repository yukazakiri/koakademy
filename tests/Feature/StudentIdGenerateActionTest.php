<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

it('generates correct student ID for College type', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create some existing students to test ID generation
    Student::create([
        'first_name' => 'Existing',
        'last_name' => 'College1',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200001,
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    Student::create([
        'first_name' => 'Existing',
        'last_name' => 'College2',
        'gender' => 'female',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200002,
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    // Test the generateNextId method
    $nextId = Student::generateNextId(StudentType::College);

    // Should find the highest ID + 1 (200003)
    expect($nextId)->toBe(200003);
    expect($nextId)->toBeGreaterThanOrEqual(200000);
    expect($nextId)->toBeLessThanOrEqual(299999);
});

it('generates correct student ID for TESDA type', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create some existing TESDA students
    Student::create([
        'first_name' => 'Existing',
        'last_name' => 'TESDA1',
        'gender' => 'male',
        'birth_date' => '1995-01-01',
        'age' => 29,
        'student_id' => 200010,
        'student_type' => StudentType::TESDA,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    $nextId = Student::generateNextId(StudentType::TESDA);

    expect($nextId)->toBe(200011);
    expect(mb_strlen((string) $nextId))->toBe(6);
    expect($nextId)->toBeGreaterThanOrEqual(200000);
});

it('generates correct student ID for DHRT type', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create some existing DHRT students
    Student::create([
        'first_name' => 'Existing',
        'last_name' => 'DHRT1',
        'gender' => 'male',
        'birth_date' => '1990-01-01',
        'age' => 34,
        'student_id' => 200050,
        'student_type' => StudentType::DHRT,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    Student::create([
        'first_name' => 'Existing',
        'last_name' => 'DHRT2',
        'gender' => 'female',
        'birth_date' => '1990-01-01',
        'age' => 34,
        'student_id' => 200051,
        'student_type' => StudentType::DHRT,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    $nextId = Student::generateNextId(StudentType::DHRT);

    expect($nextId)->toBe(200052);
    expect(mb_strlen((string) $nextId))->toBe(6);
});

it('handles mixed student types sharing same prefix correctly', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create students with different types but same prefix (2)
    Student::create([
        'first_name' => 'College',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200100,
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    Student::create([
        'first_name' => 'TESDA',
        'last_name' => 'Student',
        'gender' => 'female',
        'birth_date' => '1995-01-01',
        'age' => 29,
        'student_id' => 200101,
        'student_type' => StudentType::TESDA,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    Student::create([
        'first_name' => 'DHRT',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '1990-01-01',
        'age' => 34,
        'student_id' => 200102,
        'student_type' => StudentType::DHRT,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    // Test generation for each type - should all find the highest ID + 1
    $collegeId = Student::generateNextId(StudentType::College);
    $tesdaId = Student::generateNextId(StudentType::TESDA);
    $dhrtId = Student::generateNextId(StudentType::DHRT);

    expect($collegeId)->toBe(200103);
    expect($tesdaId)->toBe(200103);
    expect($dhrtId)->toBe(200103);

    // All should be unique 6-digit numbers starting with 2
    expect(mb_strlen((string) $collegeId))->toBe(6);
    expect(mb_strlen((string) $tesdaId))->toBe(6);
    expect(mb_strlen((string) $dhrtId))->toBe(6);
});

it('finds gaps in ID sequence', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create students with gaps in ID sequence
    Student::create([
        'first_name' => 'Student',
        'last_name' => 'One',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200001,
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    Student::create([
        'first_name' => 'Student',
        'last_name' => 'Three',
        'gender' => 'female',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200003, // Gap: 200002 is missing
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    $nextId = Student::generateNextId(StudentType::College);

    // Should find the highest ID + 1 (200004)
    expect($nextId)->toBe(200004);
});

it('handles edge case with no existing students', function () {
    // Clear any existing students for this test
    DB::table('students')->where('student_type', StudentType::College->value)->delete();

    $nextId = Student::generateNextId(StudentType::College);

    // Should start from the beginning of the range
    expect($nextId)->toBe(200000);
    expect(mb_strlen((string) $nextId))->toBe(6);
});

it('verifies no duplicate IDs are generated', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create multiple students and verify all generated IDs are unique
    $generatedIds = [];

    for ($i = 0; $i < 5; $i++) {
        $id = Student::generateNextId(StudentType::College);
        $generatedIds[] = $id;

        // Create a student with this ID to simulate the generation
        Student::create([
            'first_name' => "Student{$i}",
            'last_name' => 'Test',
            'gender' => 'male',
            'birth_date' => '2000-01-01',
            'age' => 24,
            'student_id' => $id,
            'student_type' => StudentType::College,
            'academic_year' => 1,
            'status' => 'enrolled',
        ]);
    }

    // All IDs should be unique
    expect(array_unique($generatedIds))->toHaveCount(count($generatedIds));

    // All should be 6-digit numbers starting with 2
    foreach ($generatedIds as $id) {
        expect(mb_strlen((string) $id))->toBe(6);
        expect($id)->toBeGreaterThanOrEqual(200000);
        expect($id)->toBeLessThanOrEqual(299999);
    }
});
