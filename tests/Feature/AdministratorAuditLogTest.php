<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia;
use Spatie\Activitylog\Models\Activity;

it('shows audit logs for administrators', function (): void {
    if (! Schema::hasTable('activity_log')) {
        $this->markTestSkipped('Activity log table not available in tests.');
    }

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    activity()
        ->causedBy($admin)
        ->withProperties(['ip' => '127.0.0.1'])
        ->log('Viewed audit logs');

    $this->actingAs($admin)
        ->get(portalUrlForAdministrators('/administrators/audit-logs'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('administrators/audit-logs/index', false)
            ->has('logs.data')
            ->has('analytics')
            ->where('analytics.total', Activity::count())
        );
});
