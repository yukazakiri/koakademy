<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;

final class NotificationController extends Controller
{
    /**
     * Mark a notification as read.
     */
    public function markAsRead(Request $request, string $id): RedirectResponse
    {
        $notification = DatabaseNotification::query()
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', $request->user()::class)
            ->firstOrFail();

        $notification->markAsRead();

        return back();
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return back();
    }

    /**
     * Delete a notification.
     */
    public function destroy(Request $request, string $id): RedirectResponse
    {
        $notification = DatabaseNotification::query()
            ->where('id', $id)
            ->where('notifiable_id', $request->user()->id)
            ->where('notifiable_type', $request->user()::class)
            ->firstOrFail();

        $notification->delete();

        return back();
    }
}
