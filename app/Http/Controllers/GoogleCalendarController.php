<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ConnectedAccount;
use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Exception;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

final class GoogleCalendarController extends Controller
{
    public function connect()
    {
        return Socialite::driver('google')
            ->scopes(['https://www.googleapis.com/auth/calendar'])
            ->with(['access_type' => 'offline', 'prompt' => 'consent select_account'])
            ->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();
            $user = Auth::user();

            ConnectedAccount::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'provider' => 'google',
                ],
                [
                    'provider_id' => $googleUser->getId(),
                    'name' => $googleUser->getName(),
                    'nickname' => $googleUser->getNickname(),
                    'email' => $googleUser->getEmail(),
                    'avatar_path' => $googleUser->getAvatar(),
                    'token' => $googleUser->token,
                    'refresh_token' => $googleUser->refreshToken,
                    'expires_at' => now()->addSeconds($googleUser->expiresIn),
                ]
            );

            return redirect('/profile')->with('success', 'Google Calendar connected successfully!');
        } catch (Exception $e) {
            Log::error('Google OAuth Error: '.$e->getMessage());

            return redirect('/profile')->with('error', 'Failed to connect Google Calendar.');
        }
    }

    public function disconnect()
    {
        $user = Auth::user();
        ConnectedAccount::where('user_id', $user->id)->where('provider', 'google')->delete();

        return redirect('/profile')->with('success', 'Google Calendar disconnected.');
    }

    public function sync(Request $request)
    {
        $user = Auth::user();
        $account = ConnectedAccount::where('user_id', $user->id)->where('provider', 'google')->first();

        if (! $account) {
            return response()->json(['message' => 'Not connected'], 400);
        }

        try {
            $client = $this->getGoogleClient($account);
            $service = new Calendar($client);

            // Fetch schedules based on user role
            $schedules = $this->getUserSchedules($user);

            $calendarId = 'primary';
            $insertedCount = 0;

            foreach ($schedules as $scheduleItem) {
                // Check if event already exists (this is a simplified check, ideally we'd store event ID)
                // For now, we'll just add "Current Semester" events.
                // To avoid duplicates, we could search for events with the same summary and time.
                // But for this implementation, let's just insert upcoming events for the next week as a demo.

                $event = new Event([
                    'summary' => $scheduleItem['title'],
                    'location' => $scheduleItem['room'],
                    'description' => $scheduleItem['description'],
                    'start' => [
                        'dateTime' => $scheduleItem['start_time'],
                        'timeZone' => config('app.timezone'),
                    ],
                    'end' => [
                        'dateTime' => $scheduleItem['end_time'],
                        'timeZone' => config('app.timezone'),
                    ],
                    // Recurrence rule could be added here
                    'recurrence' => ['RRULE:FREQ=WEEKLY;COUNT=18'], // Assuming ~18 weeks semester
                    'extendedProperties' => [
                        'private' => [
                            'app_event_type' => 'dccp_schedule',
                        ],
                    ],
                ]);

                // Determine the next occurrence of this day
                // This logic needs to be robust.
                // For now, let's just use the start_time provided by getUserSchedules which should calculate the next occurrence.

                $service->events->insert($calendarId, $event);
                $insertedCount++;
            }

            return response()->json(['message' => "Synced $insertedCount events to Google Calendar."]);

        } catch (Exception $e) {
            Log::error('Google Calendar Sync Error: '.$e->getMessage());

            return response()->json(['message' => 'Failed to sync calendar.'], 500);
        }
    }

    public function unsync(Request $request)
    {
        $user = Auth::user();
        $account = ConnectedAccount::where('user_id', $user->id)->where('provider', 'google')->first();

        if (! $account) {
            return response()->json(['message' => 'Not connected'], 400);
        }

        try {
            $client = $this->getGoogleClient($account);
            $service = new Calendar($client);
            $calendarId = 'primary';
            $deletedCount = 0;

            // 1. Try to delete events with our extended property (Safe & Robust)
            $events = $service->events->listEvents($calendarId, [
                'privateExtendedProperty' => 'app_event_type=dccp_schedule',
                'singleEvents' => false, // Delete the series
            ]);

            foreach ($events->getItems() as $event) {
                $service->events->delete($calendarId, $event->getId());
                $deletedCount++;
            }

            // 2. Fallback: If no tagged events found, try to find legacy events by pattern (Best Effort)
            // Only do this if we deleted nothing, to avoid over-deleting if we have a mix.
            // Or maybe do it anyway? Let's do it if deletedCount is low, but safer to only do it if count is 0
            // to assume this is a legacy cleanup run.
            if ($deletedCount === 0) {
                // List all events (this matches the simplified logic in sync which doesn't check for duplicates)
                // We need to iterate and check properties manually since we can't search by description easily in API
                // unless we use 'q' which searches everything.
                $optParams = [
                    'singleEvents' => false,
                    'q' => 'Class: ', // Filter by prefix
                ];
                $events = $service->events->listEvents($calendarId, $optParams);

                foreach ($events->getItems() as $event) {
                    $summary = $event->getSummary();
                    $description = $event->getDescription();

                    // Strict check on title prefix and description format used in sync
                    if (
                        (str_starts_with((string) $summary, 'Class: ') || str_starts_with((string) $summary, 'Teaching: ')) &&
                        str_contains((string) $description, 'Section: ')
                    ) {
                        $service->events->delete($calendarId, $event->getId());
                        $deletedCount++;
                    }
                }
            }

            if ($deletedCount === 0) {
                return response()->json(['message' => 'No synced events found to remove.']);
            }

            return response()->json(['message' => "Removed $deletedCount events from Google Calendar."]);

        } catch (Exception $e) {
            Log::error('Google Calendar Unsync Error: '.$e->getMessage());

            return response()->json(['message' => 'Failed to unsync calendar.'], 500);
        }
    }

    private function getGoogleClient(ConnectedAccount $account): Client
    {
        $client = new Client();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setAccessToken($account->token);

        if ($account->expires_at->isPast()) {
            if ($account->refresh_token) {
                $client->fetchAccessTokenWithRefreshToken($account->refresh_token);
                $newToken = $client->getAccessToken();

                $account->update([
                    'token' => $newToken['access_token'],
                    'expires_at' => now()->addSeconds($newToken['expires_in']),
                ]);
            } else {
                throw new Exception('Token expired and no refresh token available.');
            }
        }

        return $client;
    }

    private function getUserSchedules(User $user)
    {
        $events = [];

        // Check if Student
        $student = Student::where('email', $user->email)
            ->orWhere('user_id', $user->id)
            ->first();

        if ($student) {
            $enrollments = $student->getCurrentClasses();
            foreach ($enrollments as $enrollment) {
                $class = $enrollment->class;
                if (! $class) {
                    continue;
                }

                $this->processClassSchedules($class, $events, 'Class: '.($class->subject->title ?? $class->subject_code));
            }
        }

        // Check if Faculty (Faculty model might not be directly linked to User via user_id in all setups, but let's try email)
        // Based on Faculty model, it uses UUID.
        $faculty = Faculty::where('email', $user->email)->first();
        if ($faculty) {
            $classes = $faculty->classes;
            foreach ($classes as $class) {
                $this->processClassSchedules($class, $events, 'Teaching: '.($class->subject->title ?? $class->subject_code));
            }
        }

        return $events;
    }

    private function processClassSchedules($class, &$events, string $titlePrefix): void
    {
        $class->load('schedules');
        foreach ($class->schedules as $schedule) {
            // Calculate next occurrence of the day
            $nextDate = $this->getNextDateForDay($schedule->day_of_week);

            // Format start and end datetime
            $startDateTime = $nextDate.'T'.$schedule->start_time->format('H:i:s');
            $endDateTime = $nextDate.'T'.$schedule->end_time->format('H:i:s');

            $events[] = [
                'title' => $titlePrefix,
                'room' => $schedule->room->name ?? 'TBA',
                'description' => 'Section: '.$class->section,
                'start_time' => $startDateTime,
                'end_time' => $endDateTime,
            ];
        }
    }

    private function getNextDateForDay(string $dayName): string
    {
        $days = [
            'monday' => 1, 'mon' => 1,
            'tuesday' => 2, 'tue' => 2,
            'wednesday' => 3, 'wed' => 3,
            'thursday' => 4, 'thu' => 4,
            'friday' => 5, 'fri' => 5,
            'saturday' => 6, 'sat' => 6,
            'sunday' => 7, 'sun' => 7,
        ];

        $dayNumber = $days[mb_strtolower($dayName)] ?? null;

        if (! $dayNumber) {
            return date('Y-m-d');
        } // Fallback

        return date('Y-m-d', strtotime('next '.$dayName));
    }
}
