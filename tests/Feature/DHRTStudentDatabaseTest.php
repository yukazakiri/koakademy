<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

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

it('generates correct student ID for DHRT type', function () {
    // Test the generateNextId method with DHRT type
    $nextId = Student::generateNextId(StudentType::DHRT);

    // Should start with 2 and be 6 digits
    expect($nextId)->toBeGreaterThanOrEqual(200000);
    expect($nextId)->toBeLessThanOrEqual(299999);
    expect(mb_strlen((string) $nextId))->toBe(6);
});
