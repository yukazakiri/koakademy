<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\NotificationInboxRequest;
use App\Models\User;
use App\Services\NotificationShareService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

final class NotificationController extends Controller
{
    /**
     * Display the authenticated user's notification inbox.
     */
    public function inbox(NotificationInboxRequest $request, NotificationShareService $notificationService): Response
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return Inertia::render('notifications/index', [
            'notificationFeed' => $notificationService->paginateNotifications($user, $request->status()),
            'filters' => [
                'status' => $request->status(),
            ],
        ]);
    }

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
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

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
