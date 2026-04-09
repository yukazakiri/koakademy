<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

it('can create DHRT student with correct ID prefix', function () {
    // Test that DHRT students use prefix 2
    expect(StudentType::DHRT->getIdPrefix())->toBe('2');

    // Test that DHRT doesn't require LRN
    expect(StudentType::DHRT->requiresLrn())->toBeFalse();

    // Test that DHRT has correct properties
    expect(StudentType::DHRT->getLabel())->toBe('DHRT Student');
    expect(StudentType::DHRT->getAbbreviation())->toBe('DHRT');
    expect(StudentType::DHRT->getDescription())->toBe('DHRT students pursuing specialized technical programs');
});

it('can create DHRT student record', function () {
    // Create a DHRT student
    DB::table('students')->insert([
        [
            'first_name' => 'DHRT',
            'last_name' => 'Student',
            'gender' => 'male',
            'birth_date' => '1990-01-01',
            'age' => 34,
            'student_id' => 200001,
            'student_type' => StudentType::DHRT->value,
            'academic_year' => 1,
            'status' => 'enrolled',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    // Verify the student was created
    $student = Student::where('student_type', StudentType::DHRT->value)->first();
    expect($student)->not()->toBeNull();
    expect($student->student_type)->toBeInstanceOf(StudentType::class);
    expect($student->student_type)->toBe(StudentType::DHRT);
    expect($student->student_id)->toBe(200001);
});

it('verifies ID prefix requirements for all student types', function () {
    // Test that College, TESDA, and DHRT all use prefix 2
    expect(StudentType::College->getIdPrefix())->toBe('2');
    expect(StudentType::TESDA->getIdPrefix())->toBe('2');
    expect(StudentType::DHRT->getIdPrefix())->toBe('2');

    // Test that SHS uses prefix 3
    expect(StudentType::SeniorHighSchool->getIdPrefix())->toBe('3');
});

it('verifies that only SHS requires LRN', function () {
    // Only SHS should require LRN
    expect(StudentType::SeniorHighSchool->requiresLrn())->toBeTrue();

    // All others should not require LRN
    expect(StudentType::College->requiresLrn())->toBeFalse();
    expect(StudentType::TESDA->requiresLrn())->toBeFalse();
    expect(StudentType::DHRT->requiresLrn())->toBeFalse();
});

it('includes DHRT in student type options', function () {
    $options = StudentType::asSelectOptions();

    expect($options)->toHaveKey('college');
    expect($options)->toHaveKey('shs');
    expect($options)->toHaveKey('tesda');
    expect($options)->toHaveKey('dhrt');

    expect($options['dhrt'])->toBe('DHRT Student');
});

it('generates correct student ID for DHRT type', function () {
    // Test the generateNextId method with DHRT type
    $nextId = Student::generateNextId(StudentType::DHRT);

    // Should start with 2 and be 6 digits
    expect($nextId)->toBeGreaterThanOrEqual(200000);
    expect($nextId)->toBeLessThanOrEqual(299999);
    expect(mb_strlen((string) $nextId))->toBe(6);
});
