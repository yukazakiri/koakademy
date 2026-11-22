<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

// Private channel for user notifications
Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

// Restrict test-notifications channel to local/testing environments or Developers
Broadcast::channel('test-notifications', function (User $user): bool {
    return app()->environment(['local', 'testing'])
        || $user->role === UserRole::Developer
        || $user->hasRole(UserRole::Developer);
});

// Example for Filament notifications (you can customize this based on your needs)
Broadcast::channel('filament-notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Administrators channel for system-wide events
Broadcast::channel('administrators', function (User $user) {
    return $user->isAdministrative();
});

Broadcast::channel('online-users', function (User $user): array {
    return ['id' => $user->id];
});
