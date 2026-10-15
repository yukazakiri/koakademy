<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ApplicationIntegrationSettingsService;
use App\Services\CalendarFeedService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

final class CalendarFeedController extends Controller
{
    public function __construct(
        private readonly CalendarFeedService $calendarFeedService,
        private readonly ApplicationIntegrationSettingsService $integrationSettings
    ) {}

    /**
     * Public calendar feed via secure token for Outlook, Apple Calendar, Google Calendar subscription.
     */
    public function feed(User $user, string $token): Response
    {
        if (! $this->integrationSettings->isCalendarFeedAllowedFor($user)) {
            abort(403, 'Calendar subscription feeds are disabled by the institutional administrator.');
        }

        if (! $this->calendarFeedService->validateToken($user, $token)) {
            abort(403, 'Invalid or expired calendar feed token.');
        }

        $icsContent = $this->calendarFeedService->renderIcs($user);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="koakademy-schedule.ics"',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    /**
     * Download the .ics file directly for authenticated users.
     */
    public function download(): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $this->integrationSettings->isCalendarFeedAllowedFor($user)) {
            abort(403, 'Calendar export is disabled by the institutional administrator.');
        }

        $icsContent = $this->calendarFeedService->renderIcs($user);

        return response($icsContent, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="koakademy-schedule.ics"',
        ]);
    }
}
