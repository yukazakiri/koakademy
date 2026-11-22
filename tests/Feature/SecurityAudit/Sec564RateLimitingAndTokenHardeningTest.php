<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\CalendarFeedService;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

it('attaches the api rate limiter middleware and returns rate limit headers on API responses', function (): void {
    GeneralSetting::factory()->create();

    $response = $this->getJson('/api/v1/public/settings');

    $response->assertOk()
        ->assertHeader('X-RateLimit-Limit')
        ->assertHeader('X-RateLimit-Remaining');
});

it('blocks students from enumerating other students via POST /api/students/verify', function (): void {
    $school = School::factory()->create();
    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'school_id' => $school->id,
    ]);
    Student::factory()->create([
        'student_id' => 20250099,
        'email' => 'victim@student.example',
        'school_id' => $school->id,
    ]);

    Sanctum::actingAs($studentUser, [config('api.abilities.read'), config('api.abilities.write')]);

    $this->postJson('/api/students/verify', [
        'student_id' => '20250099',
        'email' => 'victim@student.example',
    ])->assertForbidden();
});

it('throttles public id-card verification and calendar feed routes', function (): void {
    $idCardRoute = Route::getRoutes()->getByName('id-card.verify');
    $calendarRoute = Route::getRoutes()->getByName('calendar.feed');

    expect($idCardRoute?->gatherMiddleware())->toContain('throttle:30,1')
        ->and($calendarRoute?->gatherMiddleware())->toContain('throttle:30,1');
});

it('rejects suspended users on the calendar feed endpoint and invalidates old tokens on rotation', function (): void {
    $user = User::factory()->create([
        'role' => UserRole::Instructor,
    ]);

    $service = app(CalendarFeedService::class);
    $firstToken = $service->generateToken($user);

    $this->get("/calendar/feed/{$user->id}/{$firstToken}.ics")
        ->assertOk();

    // Rotating the token immediately invalidates the previously leaked URL
    $rotatedToken = $service->rotateToken($user->fresh());
    expect($rotatedToken)->not->toBe($firstToken);

    $this->get("/calendar/feed/{$user->id}/{$firstToken}.ics")
        ->assertForbidden();

    $this->get("/calendar/feed/{$user->id}/{$rotatedToken}.ics")
        ->assertOk();

    // Suspending the user revokes calendar feed access immediately
    $user->forceFill(['suspended_at' => now()])->save();

    $this->get("/calendar/feed/{$user->id}/{$rotatedToken}.ics")
        ->assertForbidden();
});
