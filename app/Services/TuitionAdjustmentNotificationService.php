<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Student;
use App\Models\TuitionAdjustment;
use App\Models\User;
use App\Notifications\StudentTuitionAdjustedNotification;
use Illuminate\Support\Facades\Notification;
use Throwable;

final readonly class TuitionAdjustmentNotificationService
{
    /** @return array{database: string, mail: string, warnings: list<string>} */
    public function send(TuitionAdjustment $adjustment): array
    {
        $adjustment->loadMissing(['enrollment.student', 'actor']);
        $student = $adjustment->enrollment?->student;
        $warnings = [];
        $database = 'unavailable';
        $mail = 'unavailable';

        if (! $student instanceof Student) {
            return compact('database', 'mail', 'warnings');
        }

        $notification = new StudentTuitionAdjustedNotification(
            beforeSnapshot: $adjustment->before_snapshot,
            afterSnapshot: $adjustment->after_snapshot,
            reason: $adjustment->reason,
            actorName: $adjustment->actor?->name,
        );
        $user = $this->linkedUser($student);

        if ($user instanceof User) {
            try {
                $user->notify($notification);
                $database = 'queued';
            } catch (Throwable $exception) {
                report($exception);
                $database = 'failed';
                $warnings[] = 'The in-app notification could not be queued.';
            }
        } else {
            $warnings[] = 'No linked student portal account was found.';
        }

        $email = $this->normalizedEmail($student->email);
        if ($email !== null) {
            try {
                Notification::route('mail', $email)->notify($notification);
                $mail = 'queued';
            } catch (Throwable $exception) {
                report($exception);
                $mail = 'failed';
                $warnings[] = 'The email notification could not be queued.';
            }
        } else {
            $warnings[] = 'No student email address was available.';
        }

        $status = compact('database', 'mail', 'warnings');
        $adjustment->forceFill(['delivery_status' => $status])->save();

        return $status;
    }

    private function linkedUser(Student $student): ?User
    {
        $email = $this->normalizedEmail($student->email);

        return User::query()
            ->whereIn('role', [UserRole::Student->value, UserRole::GraduateStudent->value, UserRole::ShsStudent->value])
            ->where(function ($query) use ($student, $email): void {
                if ($student->user_id !== null) {
                    $query->orWhere('id', $student->user_id);
                }
                if ($email !== null) {
                    $query->orWhereRaw('lower(email) = ?', [$email]);
                }
                $query->orWhere('record_id', (string) $student->id);
            })
            ->first();
    }

    private function normalizedEmail(mixed $email): ?string
    {
        return is_string($email) && mb_trim($email) !== '' ? mb_strtolower(mb_trim($email)) : null;
    }
}
