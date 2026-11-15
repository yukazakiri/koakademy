<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\GradeRelease;
use App\Models\Student;
use App\Models\User;
use App\Notifications\StudentGradesPublishedNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

final class GradeReleaseNotificationService
{
    /** @return array<string, mixed> */
    public function send(GradeRelease $release, Student $student, bool $retry = false): array
    {
        $configured = data_get(app(GeneralSettingsService::class)->getGlobalSettingsModel()?->more_configs, 'notification_channels.enabled_channels', ['mail', 'database']);
        $configured = is_array($configured) ? $configured : [];
        $status = ['mail' => 'disabled', 'database' => 'disabled', 'warnings' => []];
        $previous = $release->notification_status ?? [];

        if (in_array('database', $configured, true)) {
            $user = $student->user_id ? User::query()->whereKey($student->user_id)
                ->whereIn('role', [UserRole::Student->value, UserRole::GraduateStudent->value, UserRole::ShsStudent->value])->first() : null;
            if ($retry && ($previous['database'] ?? null) === 'queued') {
                $status['database'] = 'queued';
            } elseif ($user) {
                try {
                    $user->notify(new StudentGradesPublishedNotification($release->school_year, $release->semester, 'database'));
                    $status['database'] = 'queued';
                } catch (Throwable $exception) {
                    report($exception);
                    $status['database'] = 'failed';
                    $status['warnings'][] = 'The in-app notification could not be queued.';
                }
            } else {
                $status['database'] = 'unavailable';
                $status['warnings'][] = 'No linked student portal account was found.';
            }
        }

        if (in_array('mail', $configured, true)) {
            if ($retry && ($previous['mail'] ?? null) === 'queued') {
                $status['mail'] = 'queued';
            } elseif (filter_var($student->email, FILTER_VALIDATE_EMAIL)) {
                try {
                    Notification::route('mail', $student->email)->notify(new StudentGradesPublishedNotification($release->school_year, $release->semester, 'mail'));
                    $status['mail'] = 'queued';
                } catch (Throwable $exception) {
                    report($exception);
                    $status['mail'] = 'failed';
                    $status['warnings'][] = 'The email notification could not be queued.';
                }
            } else {
                $status['mail'] = 'unavailable';
                $status['warnings'][] = 'No valid student email address was found.';
            }
        }

        foreach (array_intersect($configured, ['sms', 'broadcast', 'pusher']) as $channel) {
            $status['warnings'][] = "The {$channel} channel is not supported for grade-release notifications.";
        }

        if ($retry) {
            foreach (['mail', 'database'] as $channel) {
                if (($previous[$channel] ?? null) === 'queued') {
                    $status[$channel] = 'queued';
                }
            }
        }

        GradeRelease::query()->whereKey($release->id)->where('revision', $release->revision)->update(['notification_status' => json_encode($status, JSON_THROW_ON_ERROR)]);

        return $status;
    }
}
