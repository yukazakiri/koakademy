<?php

declare(strict_types=1);

use App\Models\Student;
use App\Models\User;

it('verifies an existing student by student_id and email', function () {
    $user = User::factory()->create();
    $student = Student::factory()->create([
        'student_id' => 2024001,
        'email' => 'student@example.com',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'status' => 'enrolled',
        'student_type' => 'college',
    ]);

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/students/verify', [
            'student_id' => 2024001,
            'email' => 'student@example.com',
        ]);

    $response->assertOk()
        ->assertJson([
            'message' => 'Student verified successfully',
            'data' => [
                'exists' => true,
                'student_id' => 2024001,
                'email' => 'student@example.com',
                'full_name' => $student->full_name,
                'status' => 'enrolled',
                'student_type' => 'college',
            ],
        ]);
});

it('returns 404 when student does not exist', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/students/verify', [
            'student_id' => 9999999,
            'email' => 'nonexistent@example.com',
        ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Student not found',
            'data' => [
                'exists' => false,
                'student_id' => 9999999,
                'email' => 'nonexistent@example.com',
            ],
        ]);
});

it('returns validation error when student_id is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/students/verify', [
            'email' => 'student@example.com',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['student_id']);
});

it('returns validation error when email is missing', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/students/verify', [
            'student_id' => 2024001,
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('returns validation error when email is invalid', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/students/verify', [
            'student_id' => 2024001,
            'email' => 'invalid-email',
        ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('requires authentication', function () {
    $response = $this->postJson('/api/students/verify', [
        'student_id' => 2024001,
        'email' => 'student@example.com',
    ]);

    $response->assertUnauthorized();
});

it('returns 404 when student_id does not match email', function () {
    $user = User::factory()->create();
    Student::factory()->create([
        'student_id' => 2024001,
        'email' => 'student1@example.com',
    ]);
    Student::factory()->create([
        'student_id' => 2024002,
        'email' => 'student2@example.com',
    ]);

    // Try to verify with correct student_id but wrong email
    $response = $this->actingAs($user, 'sanctum')
        ->postJson('/api/students/verify', [
            'student_id' => 2024001,
            'email' => 'student2@example.com',
        ]);

    $response->assertNotFound()
        ->assertJson([
            'message' => 'Student not found',
            'data' => [
                'exists' => false,
            ],
        ]);
});
