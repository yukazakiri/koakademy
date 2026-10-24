<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Classes;
use App\Models\ClassScheduleChangeStudentNotification;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\ClassScheduleChangedNotification;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

final class ClassScheduleChangeNotificationService
{
    /**
     * @return array<int, array{day_of_week: string, start_time: string, end_time: string, room_id: int|null, room_name: string|null, delivery_mode?: string}>
     */
    public function snapshot(Classes $class): array
    {
        return $this->formatScheduleSnapshot(
            $class->schedules()
                ->with('room:id,name')
                ->get(),
            $class->delivery_mode ?? Classes::DELIVERY_FACE_TO_FACE,
        );
    }

    /**
     * @param  array<int, array{day_of_week: string, start_time: string, end_time: string, room_id: int|null, room_name: string|null, delivery_mode?: string}>  $oldSnapshot
     */
    public function notifyIfChanged(Classes $class, array $oldSnapshot, ?User $changedBy = null): int
    {
        $newSnapshot = $this->snapshot($class);

        if ($this->canonicalize($oldSnapshot) === $this->canonicalize($newSnapshot)) {
            return 0;
        }

        $students = $this->enrolledStudents($class);
        $scheduleChange = $class->scheduleChanges()->create([
            'changed_by_user_id' => $changedBy?->id,
            'old_schedule' => $oldSnapshot,
            'new_schedule' => $newSnapshot,
        ]);

        if ($students->isEmpty()) {
            Log::info('Class schedule changed, but no enrolled students were found to notify.', [
                'class_id' => $class->id,
                'class_schedule_change_id' => $scheduleChange->id,
            ]);

            return 0;
        }

        $users = $this->linkedStudentUsers($students);
        foreach ($students as $student) {
            $user = $this->linkedUserForStudent($student, $users);

            $scheduleChange->studentNotifications()->updateOrCreate(
                [
                    'student_id' => $student->id,
                ],
                [
                    'user_id' => $user?->id,
                    'email' => $this->normalizedEmail($student->email),
                ]
            );
        }

        $settings = array_merge(Classes::getDefaultSettings(), (array) ($class->settings ?? []));
        if (! (bool) ($settings['notify_students_on_schedule_changes'] ?? true)) {
            Log::info('Class schedule change recorded for manual notification.', [
                'class_id' => $class->id,
                'class_schedule_change_id' => $scheduleChange->id,
                'student_count' => $students->count(),
            ]);

            return 0;
        }

        $recipientCount = $this->sendPendingNotifications(
            $scheduleChange->studentNotifications()->pending()->get(),
            $changedBy
        );

        $emails = $students
            ->pluck('email')
            ->map(fn ($email): ?string => $this->normalizedEmail($email))
            ->filter()
            ->unique()
            ->values();

        Log::info('Class schedule change notifications queued.', [
            'class_id' => $class->id,
            'class_schedule_change_id' => $scheduleChange->id,
            'student_count' => $students->count(),
            'linked_user_count' => $users->count(),
            'email_count' => $emails->count(),
            'recipient_count' => $recipientCount,
        ]);

        return $recipientCount;
    }

    /**
     * @return Collection<int, ClassScheduleChangeStudentNotification>
     */
    public function pendingNotificationsForEnrollment(StudentEnrollment $enrollment): Collection
    {
        $classIds = $enrollment->student->classEnrollments()
            ->where('status', true)
            ->whereHas('class', function ($query) use ($enrollment): void {
                $query->where('school_year', $enrollment->school_year)
                    ->where('semester', $enrollment->semester);
            })
            ->pluck('class_id')
            ->map(fn ($classId): int => (int) $classId)
            ->unique()
            ->values();

        if ($classIds->isEmpty()) {
            return collect();
        }

        return ClassScheduleChangeStudentNotification::query()
            ->pending()
            ->where('student_id', $enrollment->student->id)
            ->whereHas('scheduleChange', fn ($query) => $query->whereIn('class_id', $classIds))
            ->with([
                'scheduleChange.class.subject',
                'scheduleChange.class.faculty',
                'scheduleChange.changedBy',
            ])
            ->orderByDesc(
                \App\Models\ClassScheduleChange::query()
                    ->select('created_at')
                    ->whereColumn('class_schedule_changes.id', 'class_schedule_change_student_notifications.class_schedule_change_id')
                    ->limit(1)
            )
            ->get();
    }

    /**
     * @return array{count: int, recipients: int}
     */
    public function notifyPendingForEnrollment(StudentEnrollment $enrollment, ?User $notifiedBy = null): array
    {
        $pending = $this->pendingNotificationsForEnrollment($enrollment);

        return [
            'count' => $pending->count(),
            'recipients' => $this->sendPendingNotifications($pending, $notifiedBy),
        ];
    }

    /**
     * @param  Collection<int, ClassScheduleChangeStudentNotification>  $pending
     */
    public function previewPendingNotifications(Collection $pending): array
    {
        return $pending
            ->map(function (ClassScheduleChangeStudentNotification $studentNotification): array {
                $change = $studentNotification->scheduleChange;
                $class = $change->class;

                return [
                    'id' => $studentNotification->id,
                    'class_id' => $class?->id,
                    'class_title' => $class?->record_title ?? 'Class',
                    'subject_code' => $class?->subject_code,
                    'subject_title' => $class?->subject_title,
                    'section' => $class?->section,
                    'faculty' => $class?->faculty?->full_name,
                    'old_schedule' => $this->summarize((array) $change->old_schedule),
                    'new_schedule' => $this->summarize((array) $change->new_schedule),
                    'changed_by' => $change->changedBy?->name,
                    'changed_at' => $change->created_at?->toDateTimeString(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $schedules
     * @return array<int, array{day_of_week: string, start_time: string, end_time: string, room_id: int|null, room_name: string|null, delivery_mode?: string}>
     */
    private function formatScheduleSnapshot(iterable $schedules, string $deliveryMode): array
    {
        return collect($schedules)
            ->map(fn ($schedule): array => [
                'day_of_week' => (string) $schedule->day_of_week,
                'start_time' => $this->formatTime($schedule->start_time),
                'end_time' => $this->formatTime($schedule->end_time),
                'room_id' => $deliveryMode !== Classes::DELIVERY_ONLINE && $schedule->room_id !== null ? (int) $schedule->room_id : null,
                'room_name' => $deliveryMode === Classes::DELIVERY_ONLINE ? 'Online' : $schedule->room?->name,
                'delivery_mode' => $deliveryMode,
            ])
            ->sortBy(fn (array $schedule): string => sprintf(
                '%d|%s|%s|%s',
                $this->daySortValue($schedule['day_of_week']),
                $schedule['start_time'],
                $schedule['end_time'],
                (string) ($schedule['room_id'] ?? '')
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{day_of_week: string, start_time: string, end_time: string, room_id: int|null, room_name: string|null, delivery_mode?: string}>  $snapshot
     * @return array<int, array{day_of_week: string, start_time: string, end_time: string, room_id: int|null, delivery_mode: string}>
     */
    private function canonicalize(array $snapshot): array
    {
        return collect($snapshot)
            ->map(fn (array $schedule): array => [
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'room_id' => $schedule['room_id'],
                'delivery_mode' => $schedule['delivery_mode'] ?? Classes::DELIVERY_FACE_TO_FACE,
            ])
            ->sortBy(fn (array $schedule): string => sprintf(
                '%d|%s|%s|%s',
                $this->daySortValue($schedule['day_of_week']),
                $schedule['start_time'],
                $schedule['end_time'],
                (string) ($schedule['room_id'] ?? '')
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{day_of_week: string, start_time: string, end_time: string, room_id: int|null, room_name: string|null, delivery_mode?: string}>  $snapshot
     * @return array<int, string>
     */
    private function summarize(array $snapshot): array
    {
        return collect($snapshot)
            ->map(fn (array $schedule): string => sprintf(
                '%s, %s-%s%s',
                $schedule['day_of_week'],
                $this->displayTime($schedule['start_time']),
                $this->displayTime($schedule['end_time']),
                ($schedule['delivery_mode'] ?? Classes::DELIVERY_FACE_TO_FACE) === Classes::DELIVERY_ONLINE
                    ? ' (Online)'
                    : (! empty($schedule['room_name']) ? ' at '.$schedule['room_name'] : '')
            ))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, Student>
     */
    private function enrolledStudents(Classes $class): Collection
    {
        return $class->class_enrollments()
            ->where(fn ($query) => $query
                ->whereNull('status')
                ->orWhere('status', true))
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter(fn ($student): bool => $student instanceof Student)
            ->unique('id')
            ->values();
    }

    /**
     * @param  Collection<int, Student>  $students
     * @return Collection<int, User>
     */
    private function linkedStudentUsers(Collection $students): Collection
    {
        $studentUserIds = $students
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $studentEmails = $students
            ->pluck('email')
            ->filter(fn ($email): bool => is_string($email) && mb_trim($email) !== '')
            ->map(fn (string $email): string => mb_strtolower(mb_trim($email)))
            ->unique()
            ->values();

        $studentRecordIds = $students
            ->pluck('id')
            ->map(fn ($id): string => (string) $id)
            ->unique()
            ->values();

        return User::query()
            ->where(function ($query) use ($studentUserIds, $studentEmails, $studentRecordIds): void {
                if ($studentUserIds->isNotEmpty()) {
                    $query->orWhereIn('id', $studentUserIds);
                }

                if ($studentEmails->isNotEmpty()) {
                    $query->orWhereIn(DB::raw('lower(email)'), $studentEmails);
                }

                if ($studentRecordIds->isNotEmpty()) {
                    $query->orWhereIn('record_id', $studentRecordIds);
                }
            })
            ->whereIn('role', [
                UserRole::Student->value,
                UserRole::GraduateStudent->value,
                UserRole::ShsStudent->value,
            ])
            ->get()
            ->unique('id')
            ->values();
    }

    private function linkedUserForStudent(Student $student, Collection $users): ?User
    {
        $email = $this->normalizedEmail($student->email);

        return $users->first(function (User $user) use ($student, $email): bool {
            if ($student->user_id !== null && (int) $user->id === (int) $student->user_id) {
                return true;
            }

            if ($email !== null && mb_strtolower((string) $user->email) === $email) {
                return true;
            }

            return (string) $user->record_id === (string) $student->id;
        });
    }

    /**
     * @param  Collection<int, ClassScheduleChangeStudentNotification>  $pending
     */
    private function sendPendingNotifications(Collection $pending, ?User $notifiedBy = null): int
    {
        $recipientCount = 0;

        foreach ($pending as $studentNotification) {
            $studentNotification->loadMissing([
                'scheduleChange.class.faculty',
                'scheduleChange.changedBy',
                'user',
            ]);

            $change = $studentNotification->scheduleChange;
            $class = $change->class;

            if (! $class instanceof Classes) {
                continue;
            }

            $notification = new ClassScheduleChangedNotification(
                classId: (int) $class->id,
                classTitle: $class->record_title,
                oldSchedule: $this->summarize((array) $change->old_schedule),
                newSchedule: $this->summarize((array) $change->new_schedule),
                changedByUserId: $change->changed_by_user_id,
                changedByName: $change->changedBy?->name,
                subjectTitle: $class->subject_title,
                section: $class->section,
                facultyName: $class->faculty?->full_name,
                changedAt: $change->created_at,
            );
            $notification->afterCommit();

            if ($studentNotification->user instanceof User) {
                $studentNotification->user->notify($notification);
                $recipientCount++;
            }

            $email = $this->normalizedEmail($studentNotification->email);
            if ($email !== null) {
                Notification::route('mail', $email)->notify($notification);
                $recipientCount++;
            }

            $studentNotification->forceFill([
                'notified_at' => now(),
                'notified_by_user_id' => $notifiedBy?->id,
            ])->save();
        }

        return $recipientCount;
    }

    private function normalizedEmail(mixed $email): ?string
    {
        if (! is_string($email) || mb_trim($email) === '') {
            return null;
        }

        return mb_strtolower(mb_trim($email));
    }

    private function formatTime(mixed $time): string
    {
        if ($time instanceof DateTimeInterface) {
            return $time->format('H:i');
        }

        $value = (string) $time;
        if (preg_match('/^\d{2}:\d{2}/', $value, $matches) === 1) {
            return $matches[0];
        }

        return $value;
    }

    private function displayTime(string $time): string
    {
        $date = DateTimeImmutable::createFromFormat('H:i', $time);

        return $date instanceof DateTimeImmutable ? $date->format('g:i A') : $time;
    }

    private function daySortValue(string $day): int
    {
        $index = array_search($day, [
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday',
            'Friday',
            'Saturday',
            'Sunday',
        ], true);

        return $index === false ? 99 : $index;
    }
}
