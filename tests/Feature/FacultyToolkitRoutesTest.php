<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Features\Toggles\FacultyAssessments;
use App\Features\Toggles\FacultyAtRiskAlerts;
use App\Features\Toggles\FacultyInbox;
use App\Features\Toggles\FacultyInsights;
use App\Features\Toggles\FacultyOfficeHours;
use App\Features\Toggles\FacultyRequestsApprovals;
use App\Models\Faculty;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Pennant\Feature;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

it('serves faculty toolkit sidebar pages', function (string $path, string $title, string $feature): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    Feature::activateForEveryone($feature);

    $user = User::factory()->create([
        'role' => UserRole::Instructor,
        'faculty_id_number' => 'FAC-TEST-001',
    ]);

    actingAs($user);

    if ($feature === FacultyAtRiskAlerts::class) {
        Faculty::factory()->create(['email' => $user->email, 'faculty_id_number' => $user->faculty_id_number]);

        get($path)->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('faculty/at-risk-alerts/index')
        );

        return;
    }

    get($path)
        ->assertOk()
        ->assertInertia(fn (Assert $page): Assert => $page
            ->component('faculty/toolkit/show')
            ->where('toolkit.title', $title)
            ->has('toolkit.highlights')
            ->has('user')
        );
})->with([
    'at-risk alerts' => ['/faculty/at-risk-alerts', 'At-Risk Alerts', FacultyAtRiskAlerts::class],
    'assessments' => ['/faculty/assessments', 'Assessments', FacultyAssessments::class],
    'inbox' => ['/faculty/inbox', 'Inbox', FacultyInbox::class],
    'office hours' => ['/faculty/office-hours', 'Office Hours', FacultyOfficeHours::class],
    'requests' => ['/faculty/requests', 'Requests & Approvals', FacultyRequestsApprovals::class],
    'insights' => ['/faculty/insights', 'Insights', FacultyInsights::class],
]);

it('registers named routes for faculty toolkit links', function (): void {
    expect(route('faculty.at-risk-alerts', absolute: false))->toBe('/faculty/at-risk-alerts')
        ->and(route('faculty.assessments', absolute: false))->toBe('/faculty/assessments')
        ->and(route('faculty.inbox', absolute: false))->toBe('/faculty/inbox')
        ->and(route('faculty.office-hours', absolute: false))->toBe('/faculty/office-hours')
        ->and(route('faculty.requests', absolute: false))->toBe('/faculty/requests')
        ->and(route('faculty.insights', absolute: false))->toBe('/faculty/insights');
});
