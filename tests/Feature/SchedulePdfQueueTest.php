<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Jobs\GenerateTimetablePdfJob;
use App\Models\Faculty;
use App\Models\User;
use Illuminate\Support\Facades\Bus;

it('queues schedule PDF export from downloads route', function (): void {
    Bus::fake();

    $user = User::factory()->create([
        'role' => UserRole::Instructor->value,
        'email' => 'faculty-schedule@example.com',
        'email_verified_at' => now(),
    ]);

    $faculty = Faculty::factory()->createOne([
        'email' => $user->email,
    ]);

    $response = $this->actingAs($user)
        ->getJson(portalUrlForAdministrators('/download/schedule?type=timetable'));

    $response->assertAccepted()
        ->assertJsonPath('message', 'Schedule PDF export queued. You will be notified when your file is ready.');

    Bus::assertDispatched(GenerateTimetablePdfJob::class);
});
