<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class AnnouncementController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): Response
    {
        $announcements = Announcement::query()
            ->global()
            ->published()
            ->active()
            ->orderBy('priority', 'desc') // 'important' might be mapped to high priority, or handled via enum/string sort if needed.
            ->orderBy('published_at', 'desc')
            ->get()
            ->map(fn (Announcement $announcement): array => [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'type' => $announcement->type,
                'priority' => $announcement->priority,
                'date' => $announcement->published_at?->format('M d, Y') ?? $announcement->created_at->format('M d, Y'),
                'is_read' => false, // Placeholder if we implement read receipts later
            ]);

        return Inertia::render('announcements/index', [
            'announcements' => $announcements,
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatar' => $request->user()->getFilamentAvatarUrl(),
                'role' => $request->user()->role?->value ?? 'user',
            ],
        ]);
    }
}
