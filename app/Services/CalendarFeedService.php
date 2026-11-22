<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Faculty;
use App\Models\Student;
use App\Models\User;
use Carbon\Carbon;

final class CalendarFeedService
{
    private const string PREFERENCE_SECRET_KEY = 'calendar_feed_secret';

    /**
     * Generate a per-user rotatable token for a user's calendar feed.
     */
    public function generateToken(User $user): string
    {
        $secret = $this->ensureUserSecret($user);

        return $this->computeToken($user, $secret);
    }

    /**
     * Rotate the per-user calendar feed secret, invalidating all previously issued feed URLs.
     */
    public function rotateToken(User $user): string
    {
        $secret = bin2hex(random_bytes(32));
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $preferences[self::PREFERENCE_SECRET_KEY] = $secret;
        $user->forceFill(['preferences' => $preferences])->saveQuietly();

        return $this->computeToken($user, $secret);
    }

    /**
     * Validate the provided token against the user's expected token.
     */
    public function validateToken(User $user, string $token): bool
    {
        if ($user->suspended_at !== null) {
            return false;
        }

        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $secret = $preferences[self::PREFERENCE_SECRET_KEY] ?? null;

        if (! is_string($secret) || $secret === '') {
            return false;
        }

        return hash_equals($this->computeToken($user, $secret), $token);
    }

    /**
     * Get HTTP subscription URL.
     */
    public function getFeedUrl(User $user): string
    {
        return route('calendar.feed', [
            'user' => $user->id,
            'token' => $this->generateToken($user),
        ]);
    }

    /**
     * Get Webcal subscription URL for one-click addition to Outlook / Apple Calendar.
     */
    public function getWebcalUrl(User $user): string
    {
        $url = $this->getFeedUrl($user);

        return preg_replace('/^https?:\/\//i', 'webcal://', $url) ?? $url;
    }

    /**
     * Render RFC 5545 compliant iCalendar (.ics) string.
     */
    public function renderIcs(User $user): string
    {
        $integrationService = app(ApplicationIntegrationSettingsService::class);
        $appName = $integrationService->getCalendarFeedTitlePrefix();
        $calendarName = "{$appName} Schedule - {$user->name}";
        $schedules = $this->getUserSchedules($user);
        $rruleWeeks = $integrationService->getCalendarFeedRruleWeeks();

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//KoAkademy//Timetable Schedule Feed//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->escapeIcsText($calendarName),
            'X-WR-TIMEZONE:'.config('app.timezone', 'UTC'),
        ];

        $dtStamp = Carbon::now('UTC')->format('Ymd\THis\Z');

        foreach ($schedules as $index => $item) {
            $uid = md5("{$user->id}-{$item['title']}-{$item['start_time']}-{$index}").'@koakademy.edu';
            $dtStart = Carbon::parse($item['start_time'])->setTimezone('UTC')->format('Ymd\THis\Z');
            $dtEnd = Carbon::parse($item['end_time'])->setTimezone('UTC')->format('Ymd\THis\Z');

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = "UID:{$uid}";
            $lines[] = "DTSTAMP:{$dtStamp}";
            $lines[] = "DTSTART:{$dtStart}";
            $lines[] = "DTEND:{$dtEnd}";
            $lines[] = "RRULE:FREQ=WEEKLY;COUNT={$rruleWeeks}";
            $lines[] = 'SUMMARY:'.$this->escapeIcsText($item['title']);
            $lines[] = 'LOCATION:'.$this->escapeIcsText($item['room']);
            $lines[] = 'DESCRIPTION:'.$this->escapeIcsText($item['description']);
            $lines[] = 'STATUS:CONFIRMED';
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * Retrieve user schedule entries (students or faculty).
     *
     * @return list<array{title: string, room: string, description: string, start_time: string, end_time: string}>
     */
    public function getUserSchedules(User $user): array
    {
        $events = [];

        // Check if user is a student
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
                $subjectTitle = $class->subject->title ?? $class->subject_code ?? 'Class';
                $this->processClassSchedules($class, $events, "Class: {$subjectTitle}");
            }
        }

        // Check if user is faculty
        $faculty = Faculty::where('email', $user->email)->first();
        if ($faculty) {
            $classes = $faculty->classes;
            foreach ($classes as $class) {
                $subjectTitle = $class->subject->title ?? $class->subject_code ?? 'Class';
                $this->processClassSchedules($class, $events, "Teaching: {$subjectTitle}");
            }
        }

        return $events;
    }

    private function ensureUserSecret(User $user): string
    {
        $preferences = is_array($user->preferences) ? $user->preferences : [];
        $existing = $preferences[self::PREFERENCE_SECRET_KEY] ?? null;

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $secret = bin2hex(random_bytes(32));
        $preferences[self::PREFERENCE_SECRET_KEY] = $secret;
        $user->forceFill(['preferences' => $preferences])->saveQuietly();

        return $secret;
    }

    private function computeToken(User $user, string $secret): string
    {
        $appKey = (string) config('app.key');
        $version = (int) ($user->session_version ?? 0);

        return hash_hmac('sha256', "calendar-feed:{$user->id}:v{$version}:{$secret}", $appKey);
    }

    /**
     * @param  mixed  $class
     * @param  list<array<string, string>>  $events
     */
    private function processClassSchedules($class, array &$events, string $titlePrefix): void
    {
        $class->loadMissing('schedules.room');
        foreach ($class->schedules as $schedule) {
            if (! $schedule->start_time || ! $schedule->end_time || ! $schedule->day_of_week) {
                continue;
            }

            $nextDate = $this->getNextDateForDay($schedule->day_of_week);
            $startDateTime = $nextDate.'T'.$schedule->start_time->format('H:i:s');
            $endDateTime = $nextDate.'T'.$schedule->end_time->format('H:i:s');

            $events[] = [
                'title' => $titlePrefix,
                'room' => $schedule->room->name ?? 'TBA',
                'description' => "Section: {$class->section}",
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
        }

        return date('Y-m-d', strtotime('next '.$dayName));
    }

    private function escapeIcsText(string $text): string
    {
        return str_replace(
            ['\\', ';', ',', "\n"],
            ['\\\\', '\;', '\,', '\n'],
            mb_trim($text)
        );
    }
}
