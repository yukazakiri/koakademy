<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Notifications\TestDatabaseNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia;

function createInboxNotification(
    User $user,
    string $title,
    ?Carbon $readAt = null,
    ?Carbon $createdAt = null,
): DatabaseNotification {
    $notification = $user->notifications()->create([
        'id' => (string) Str::uuid(),
        'type' => TestDatabaseNotification::class,
        'data' => [
            'title' => $title,
            'message' => "Message for {$title}",
            'icon' => 'heroicon-o-bell',
            'type' => 'info',
            'actions' => [
                [
                    'name' => 'view',
                    'label' => 'View details',
                    'url' => '/details',
                    'color' => 'primary',
                    'icon' => null,
                    'shouldOpenInNewTab' => false,
                ],
            ],
        ],
        'read_at' => $readAt,
    ]);

    if ($createdAt instanceof Carbon) {
        $notification->forceFill([
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ])->saveQuietly();
    }

    return $notification;
}

it('requires authentication for every notification inbox', function (string $path): void {
    $this->get(portalUrlForAdministrators($path))
        ->assertRedirect('/login');
})->with([
    'student' => '/student/notifications/inbox',
    'faculty' => '/faculty/notifications/inbox',
    'administrator' => '/administrators/notifications/inbox',
]);

it('shows the personal notification inbox to each supported portal role', function (UserRole $role, string $path, array $attributes): void {
    $user = User::factory()->create([
        'role' => $role,
        ...$attributes,
    ]);

    createInboxNotification($user, 'Welcome notification');

    $this->actingAs($user)
        ->get(portalUrlForAdministrators($path))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('notifications/index', false)
            ->where('filters.status', 'all')
            ->where('notificationFeed.total', 1)
            ->where('notificationFeed.data.0.title', 'Welcome notification')
            ->where('notificationFeed.data.0.actions.0.label', 'View details')
        );
})->with([
    'student' => [UserRole::Student, '/student/notifications/inbox', []],
    'faculty' => [UserRole::Instructor, '/faculty/notifications/inbox', ['faculty_id_number' => 'FAC-001']],
    'administrator' => [UserRole::Admin, '/administrators/notifications/inbox', []],
]);

it('rejects users from another portal role', function (UserRole $role, string $path, array $attributes): void {
    $user = User::factory()->create([
        'role' => $role,
        ...$attributes,
    ]);

    $this->actingAs($user)
        ->get(portalUrlForAdministrators($path))
        ->assertForbidden();
})->with([
    'student from faculty inbox' => [UserRole::Student, '/faculty/notifications/inbox', []],
    'faculty from administrator inbox' => [UserRole::Instructor, '/administrators/notifications/inbox', ['faculty_id_number' => 'FAC-002']],
    'administrator from student inbox' => [UserRole::Admin, '/student/notifications/inbox', []],
]);

it('paginates only the authenticated users notifications newest first', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $otherUser = User::factory()->create(['role' => UserRole::Student]);

    foreach (range(1, 21) as $index) {
        createInboxNotification(
            $user,
            "Notification {$index}",
            createdAt: now()->subMinutes(21 - $index),
        );
    }

    createInboxNotification($otherUser, 'Private notification', createdAt: now()->addMinute());

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/student/notifications/inbox'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notificationFeed.total', 21)
            ->where('notificationFeed.per_page', 20)
            ->has('notificationFeed.data', 20)
            ->where('notificationFeed.data.0.title', 'Notification 21')
            ->missing('notificationFeed.data.20')
        );

    $this->actingAs($user)
        ->get(portalUrlForAdministrators('/student/notifications/inbox?page=2'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notificationFeed.current_page', 2)
            ->has('notificationFeed.data', 1)
            ->where('notificationFeed.data.0.title', 'Notification 1')
        );
});

it('rejects unsupported notification filters', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $inboxUrl = portalUrlForAdministrators('/student/notifications/inbox');

    $this->actingAs($user)
        ->from($inboxUrl)
        ->get("{$inboxUrl}?status=archived")
        ->assertRedirect($inboxUrl)
        ->assertSessionHasErrors('status');
});

it('filters the notification inbox by unread and read status', function (string $status, string $visibleTitle, string $hiddenTitle): void {
    $user = User::factory()->create(['role' => UserRole::Student]);

    createInboxNotification($user, 'Unread notification');
    createInboxNotification($user, 'Read notification', now());

    $this->actingAs($user)
        ->get(portalUrlForAdministrators("/student/notifications/inbox?status={$status}"))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('filters.status', $status)
            ->where('notificationFeed.total', 1)
            ->where('notificationFeed.data.0.title', $visibleTitle)
            ->where('notificationFeed.data.0.readAt', $status === 'read' ? fn ($value): bool => is_string($value) : null)
            ->where('notificationFeed.data', fn ($notifications): bool => collect($notifications)->doesntContain('title', $hiddenTitle))
        );
})->with([
    'unread' => ['unread', 'Unread notification', 'Read notification'],
    'read' => ['read', 'Read notification', 'Unread notification'],
]);

it('marks and deletes only notifications owned by the authenticated user', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $otherUser = User::factory()->create(['role' => UserRole::Student]);
    $ownedNotification = createInboxNotification($user, 'Owned notification');
    $otherNotification = createInboxNotification($otherUser, 'Other notification');

    $this->actingAs($user)
        ->from(portalUrlForAdministrators('/student/notifications/inbox'))
        ->post(portalUrlForAdministrators("/student/notifications/{$ownedNotification->id}/read"))
        ->assertRedirect(portalUrlForAdministrators('/student/notifications/inbox'));

    expect($ownedNotification->fresh()?->read_at)->not->toBeNull();

    $this->actingAs($user)
        ->post(portalUrlForAdministrators("/student/notifications/{$otherNotification->id}/read"))
        ->assertNotFound();

    $this->actingAs($user)
        ->delete(portalUrlForAdministrators("/student/notifications/{$otherNotification->id}"))
        ->assertNotFound();

    $this->actingAs($user)
        ->from(portalUrlForAdministrators('/student/notifications/inbox'))
        ->delete(portalUrlForAdministrators("/student/notifications/{$ownedNotification->id}"))
        ->assertRedirect(portalUrlForAdministrators('/student/notifications/inbox'));

    expect($ownedNotification->fresh())->toBeNull()
        ->and($otherNotification->fresh())->not->toBeNull();
});

it('marks all unread notifications for only the authenticated user', function (): void {
    $user = User::factory()->create(['role' => UserRole::Student]);
    $otherUser = User::factory()->create(['role' => UserRole::Student]);
    $firstNotification = createInboxNotification($user, 'First notification');
    $secondNotification = createInboxNotification($user, 'Second notification');
    $otherNotification = createInboxNotification($otherUser, 'Other notification');

    $this->actingAs($user)
        ->from(portalUrlForAdministrators('/student/notifications/inbox'))
        ->post(portalUrlForAdministrators('/student/notifications/mark-all-read'))
        ->assertRedirect(portalUrlForAdministrators('/student/notifications/inbox'));

    expect($firstNotification->fresh()?->read_at)->not->toBeNull()
        ->and($secondNotification->fresh()?->read_at)->not->toBeNull()
        ->and($otherNotification->fresh()?->read_at)->toBeNull();
});
