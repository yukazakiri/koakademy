<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CalendarFeedService;

beforeEach(function (): void {
    $this->user = User::factory()->create([
        'role' => UserRole::Instructor,
        'email' => 'faculty.calendar@example.com',
        'faculty_id_number' => 'FAC-TEST-001',
    ]);
});

it('provides calendar feed data in profile page inertia response', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get('/faculty/profile');

    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->has('calendar_feed')
        ->has('calendar_feed.feed_url')
        ->has('calendar_feed.webcal_url')
        ->has('calendar_feed.download_url')
    );
});

it('serves valid iCalendar RFC 5545 format for valid token', function (): void {
    $service = app(CalendarFeedService::class);
    $token = $service->generateToken($this->user);

    $response = $this->get("/calendar/feed/{$this->user->id}/{$token}.ics");

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $response->assertHeader('Content-Disposition', 'inline; filename="koakademy-schedule.ics"');

    $content = $response->getContent();
    expect($content)->toContain('BEGIN:VCALENDAR')
        ->toContain('VERSION:2.0')
        ->toContain('PRODID:-//KoAkademy//Timetable Schedule Feed//EN')
        ->toContain('END:VCALENDAR');
});

it('rejects calendar feed with invalid token', function (): void {
    $response = $this->get("/calendar/feed/{$this->user->id}/invalid-token-12345.ics");

    $response->assertForbidden();
});

it('allows authenticated users to download their ics schedule file', function (): void {
    $response = $this
        ->actingAs($this->user)
        ->get('/calendar/download');

    $response->assertSuccessful();
    $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
    $response->assertHeader('Content-Disposition', 'attachment; filename="koakademy-schedule.ics"');

    $content = $response->getContent();
    expect($content)->toContain('BEGIN:VCALENDAR')
        ->toContain('END:VCALENDAR');
});

it('redirects unauthenticated download requests to login', function (): void {
    $response = $this->get('/calendar/download');

    $response->assertRedirect('/login');
});
