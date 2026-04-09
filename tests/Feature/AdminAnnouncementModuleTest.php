<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;
use Modules\Announcement\Models\Announcement;
use Spatie\Permission\Models\Permission;

function ensureAnnouncementModuleColumns(): void
{
    Schema::table('announcements', function (Blueprint $table): void {
        if (! Schema::hasColumn('announcements', 'created_by')) {
            $table->unsignedBigInteger('created_by')->nullable();
        }

        if (! Schema::hasColumn('announcements', 'is_global')) {
            $table->boolean('is_global')->default(true);
        }

        if (! Schema::hasColumn('announcements', 'class_id')) {
            $table->unsignedBigInteger('class_id')->nullable();
        }

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

test('administrator announcements page uses the module controller and page', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    ensureAnnouncementModuleColumns();

    Permission::findOrCreate('view_announcements', 'web');

    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->givePermissionTo('view_announcements');

    Announcement::query()->create([
        'title' => 'System Update',
        'content' => 'Announcements are now owned by the module.',
        'type' => 'update',
        'priority' => 'high',
        'display_mode' => 'banner',
        'requires_acknowledgment' => false,
        'created_by' => $user->id,
        'is_global' => true,
        'is_active' => true,
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('administrators.announcements.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Announcement/Index')
            ->has('announcements.data', 1)
            ->where('announcements.data.0.title', 'System Update'));
});

test('administrator announcements page allows admin role without seeded announcement permissions', function (): void {
    config(['inertia.testing.ensure_pages_exist' => false]);
    ensureAnnouncementModuleColumns();

    $user = User::factory()->create(['role' => UserRole::Admin]);

    Announcement::query()->create([
        'title' => 'Migration Notice',
        'content' => 'The module should remain accessible after migration.',
        'type' => 'info',
        'priority' => 'medium',
        'display_mode' => 'banner',
        'requires_acknowledgment' => false,
        'created_by' => $user->id,
        'is_global' => true,
        'is_active' => true,
        'status' => 'published',
        'published_at' => now()->subMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('administrators.announcements.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Announcement/Index')
            ->has('announcements.data', 1)
            ->where('announcements.data.0.title', 'Migration Notice'));
});
