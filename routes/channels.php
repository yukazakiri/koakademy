<?php

declare(strict_types=1);

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

// Allow all users to listen to test notifications (for development)
Broadcast::channel('test-notifications', function ($user) {
    return true;
});

// Example for Filament notifications (you can customize this based on your needs)
Broadcast::channel('filament-notifications.{userId}', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

// Administrators channel for system-wide events
Broadcast::channel('administrators', function (User $user) {
    return $user->isAdministrative();
});
