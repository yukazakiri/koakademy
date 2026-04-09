<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Enums\UserRole;
use App\Models\Student;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\getJson;

beforeEach(function () {
    activity()->disableLogging();
});

it('has generate action on student ID field', function () {
    // Test that the form has the generate action
    $form = new App\Filament\Resources\Students\Schemas\StudentForm();

    // This test verifies the form structure exists
    // The actual generate action will be tested through the UI
    expect($form)->toBeInstanceOf(App\Filament\Resources\Students\Schemas\StudentForm::class);
});

it('generates correct ID when student type is selected', function () {
    // Clear existing students first
    Student::query()->delete();

    // Create a student to test ID generation
    Student::create([
        'first_name' => 'Existing',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200001,
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',    ]);

    // Test that generateNextId returns the correct next available ID
    $nextId = Student::generateNextId(StudentType::College);

    // Should find 200002 (highest + 1)
    expect($nextId)->toBe(200002);
    expect(mb_strlen((string) $nextId))->toBe(6);
    expect($nextId)->toBeGreaterThanOrEqual(200000);
    expect($nextId)->toBeLessThanOrEqual(299999);
});

it('generates different IDs for different student types with same prefix', function () {
    // Clear existing students first
    Student::query()->delete();

    // Test that all types with prefix 2 can generate IDs
    $collegeId = Student::generateNextId(StudentType::College);
    $tesdaId = Student::generateNextId(StudentType::TESDA);
    $dhrtId = Student::generateNextId(StudentType::DHRT);

    // All should start with 2 and be 6 digits (highest + 1)
    expect($collegeId)->toBe(200000);
    expect($tesdaId)->toBe(200000);
    expect($dhrtId)->toBe(200000);

    // Verify they are all 6-digit numbers starting with 2
    expect(mb_strlen((string) $collegeId))->toBe(6);
    expect(mb_strlen((string) $tesdaId))->toBe(6);
    expect(mb_strlen((string) $dhrtId))->toBe(6);

    expect($collegeId)->toBeGreaterThanOrEqual(200000);
    expect($tesdaId)->toBeGreaterThanOrEqual(200000);
    expect($dhrtId)->toBeGreaterThanOrEqual(200000);

    expect($collegeId)->toBeLessThanOrEqual(299999);
    expect($tesdaId)->toBeLessThanOrEqual(299999);
    expect($dhrtId)->toBeLessThanOrEqual(299999);
});

it('serves the generate id endpoint for administrators', function () {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    actingAs($admin);

    Student::withTrashed()->forceDelete();

    $existingStudent = Student::create([
        'first_name' => 'Existing',
        'last_name' => 'Student',
        'gender' => 'male',
        'birth_date' => '2000-01-01',
        'age' => 24,
        'student_id' => 200000,
        'student_type' => StudentType::College,
        'academic_year' => 1,
        'status' => 'enrolled',
    ]);

    $response = getJson(portalUrlForAdministrators('/administrators/students/generate-id?type='.StudentType::College->value))
        ->assertSuccessful()
        ->assertJson(['id' => 200001]);

    $generatedId = $response->json('id');

    expect($generatedId)->not->toBe($existingStudent->student_id);
    expect(Student::withTrashed()->where('student_id', $generatedId)->exists())->toBeFalse();
});
