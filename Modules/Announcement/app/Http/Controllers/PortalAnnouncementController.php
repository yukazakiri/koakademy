<?php

declare(strict_types=1);

namespace Modules\Announcement\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Announcement\Services\AnnouncementDataService;

final class PortalAnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementDataService $announcementDataService,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Announcement/PublicIndex', [
            'announcements' => $this->announcementDataService->getPortalIndexData(),
            'user' => [
                'name' => $request->user()->name,
                'email' => $request->user()->email,
                'avatar' => $request->user()->getFilamentAvatarUrl(),
                'role' => $request->user()->role?->value ?? 'user',
            ],
        ]);
    }
}
