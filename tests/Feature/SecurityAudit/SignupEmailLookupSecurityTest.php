<?php

declare(strict_types=1);

use App\Enums\StudentType;
use App\Models\Faculty;
use App\Models\Student;
use Illuminate\Support\Facades\Cache;

it('returns no personal details for public signup lookups', function (string $path, string $type): void {
    if ($type === 'student') {
        Student::factory()->create([
            'email' => 'private.student@example.com',
            'student_type' => StudentType::College,
        ]);
    } else {
        Faculty::factory()->create([
            'email' => 'private.faculty@example.com',
            'faculty_id_number' => 'FAC-PRIVATE',
            'position' => 'Associate Professor',
        ]);
    }

    $this->postJson($path, ['email' => "private.{$type}@example.com"])
        ->assertOk()
        ->assertExactJson(['found' => true, 'type' => $type]);
})->with([
    'web student' => ['/signup/email-lookup', 'student'],
    'web faculty' => ['/signup/email-lookup', 'faculty'],
    'api student' => ['/api/v1/auth/signup/email-lookup', 'student'],
    'api faculty' => ['/api/v1/auth/signup/email-lookup', 'faculty'],
]);

it('shares a per-IP rate limit across both lookup endpoints', function (string $firstPath, string $secondPath): void {
    Cache::flush();

    for ($attempt = 0; $attempt < 10; $attempt++) {
        $this->postJson($firstPath, ['email' => "unknown{$attempt}@example.com"])->assertOk();
    }

    $this->postJson($secondPath, ['email' => 'another@example.com'])->assertTooManyRequests();
})->with([
    'web then api' => ['/signup/email-lookup', '/api/v1/auth/signup/email-lookup'],
    'api then web' => ['/api/v1/auth/signup/email-lookup', '/signup/email-lookup'],
]);

it('throttles signup otp and signup attempts independently by email and ip', function (string $endpoint, array $payload): void {
    Cache::flush();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->postJson($endpoint, $payload);
    }

    $this->postJson($endpoint, $payload)->assertTooManyRequests();
})->with([
    'web otp' => ['/signup/send-otp', ['email' => 'rate.limit@example.com', 'user_type' => 'student']],
    'api otp' => ['/api/v1/auth/signup/send-otp', ['email' => 'rate.limit@example.com', 'user_type' => 'student']],
    'web signup' => ['/signup', ['email' => 'rate.limit@example.com']],
    'api signup' => ['/api/v1/auth/signup', ['email' => 'rate.limit@example.com']],
]);
