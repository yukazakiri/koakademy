<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Announcement\Models\Announcement;

function ensureAnnouncementPortalColumns(): void
{
    Schema::table('announcements', function (Blueprint $table): void {
        if (! Schema::hasColumn('announcements', 'status')) {
            $table->string('status')->default('draft');
        }

        if (! Schema::hasColumn('announcements', 'published_at')) {
            $table->timestamp('published_at')->nullable();
        }

        if (! Schema::hasColumn('announcements', 'expires_at')) {
            $table->timestamp('expires_at')->nullable();
        }

        if (! Schema::hasColumn('announcements', 'display_mode')) {
            $table->string('display_mode')->default('banner');
        }

        if (! Schema::hasColumn('announcements', 'requires_acknowledgment')) {
            $table->boolean('requires_acknowledgment')->default(false);
        }

        if (! Schema::hasColumn('announcements', 'is_active')) {
            $table->boolean('is_active')->default(true);
        }

        if (! Schema::hasColumn('announcements', 'starts_at')) {
            $table->timestamp('starts_at')->nullable();
        }

        if (! Schema::hasColumn('announcements', 'ends_at')) {
            $table->timestamp('ends_at')->nullable();
        }
    });
}

test('student announcements route renders the module page', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    ensureAnnouncementPortalColumns();

    $user = User::factory()->create(['role' => UserRole::Student]);

    Announcement::query()->create([
        'title' => 'Enrollment Reminder',
        'content' => 'Portal announcements now come from the module.',
        'type' => 'info',
        'priority' => 'medium',
        'display_mode' => 'banner',
        'requires_acknowledgment' => false,
        'is_active' => true,
        'status' => 'published',
        'published_at' => now()->subHour(),
        'starts_at' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('student.announcements.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Announcement/PublicIndex')
            ->has('announcements', 1)
            ->where('announcements.0.title', 'Enrollment Reminder'));
});
