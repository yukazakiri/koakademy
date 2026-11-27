<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\ClassEnrollment;
use App\Models\Classes;
use App\Models\ClassScheduleChange;
use App\Models\Room;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\StudentEnrollment;
use App\Models\User;
use App\Notifications\ClassScheduleChangedNotification;
use App\Services\ClassScheduleChangeNotificationService;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Testing\Fakes\NotificationFake;

it('records publication of a previously drafted meeting as a visible schedule change', function () {
    $service = app(ClassScheduleChangeNotificationService::class);
    $class = Classes::factory()->create();
    $meeting = Schedule::factory()->create(['class_id' => $class->id, 'status' => 'draft']);
    $before = $service->snapshot($class);

    $meeting->update(['status' => 'published']);
    $service->notifyIfChanged($class, $before, null, false);

    expect($before)->toBe([])
        ->and($class->scheduleChanges()->count())->toBe(1);
});

it('notifies enrolled students when a class schedule changes', function () {
    Notification::fake();

    $service = app(ClassScheduleChangeNotificationService::class);
    $oldRoom = Room::factory()->create(['name' => 'Room 101']);
    $newRoom = Room::factory()->create(['name' => 'Room 202']);
    $class = Classes::factory()->create([
        'room_id' => $oldRoom->id,
        'subject_code' => 'IT 101',
        'section' => 'A',
    ]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $oldRoom->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student@example.test',
    ]);
    $student = Student::factory()->create([
        'email' => 'student@example.test',
        'user_id' => $studentUser->id,
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $oldSnapshot = $service->snapshot($class);

    $class->schedules()->delete();
    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $newRoom->id,
        'day_of_week' => 'Wednesday',
        'start_time' => '10:00',
        'end_time' => '11:30',
    ]);

    $service->notifyIfChanged($class, $oldSnapshot);

    $scheduleChange = ClassScheduleChange::query()->where('class_id', $class->id)->first();
    expect($scheduleChange)->not->toBeNull()
        ->and($scheduleChange->studentNotifications()->where('student_id', $student->id)->whereNotNull('notified_at')->exists())->toBeTrue();

    Notification::assertSentTo($studentUser, ClassScheduleChangedNotification::class);
    Notification::assertSentOnDemand(
        ClassScheduleChangedNotification::class,
        fn (ClassScheduleChangedNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $channels === ['mail']
            && ($notifiable->routes['mail'] ?? null) === 'student@example.test'
    );
});

it('keeps a failed automatic dispatch pending and retries only the unqueued channel', function () {
    $service = app(ClassScheduleChangeNotificationService::class);
    $class = Classes::factory()->create(['classification' => 'shs', 'settings' => array_merge(Classes::getDefaultSettings(), ['notify_students_on_schedule_changes' => true])]);
    $meeting = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday', 'start_time' => '08:00', 'end_time' => '09:00']);
    $user = User::factory()->create(['role' => UserRole::Student, 'email' => 'retry@example.test']);
    $student = Student::factory()->create(['user_id' => $user->id, 'email' => 'retry@example.test']);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);
    $snapshot = $service->snapshot($class);
    $meeting->update(['day_of_week' => 'Tuesday']);
    $fake = new class extends NotificationFake
    {
        public bool $failMail = true;

        public int $attempts = 0;

        public function send($notifiables, $notification)
        {
            $this->attempts++;
            if ($notifiables instanceof AnonymousNotifiable && $this->failMail) {
                throw new RuntimeException('Queue unavailable');
            }

            return parent::send($notifiables, $notification);
        }
    };
    Notification::swap($fake);
    $this->app->instance(Illuminate\Contracts\Notifications\Dispatcher::class, $fake);
    $service->notifyIfChanged($class, $snapshot);
    expect($class->scheduleChanges()->firstOrFail()->studentNotifications()->firstOrFail()->auto_dispatch)->toBeTrue();
    expect($class->scheduleChanges()->firstOrFail()->studentNotifications()->firstOrFail()->only(['user_id', 'email', 'auto_dispatch', 'notified_at']))->toBe(['user_id' => $user->id, 'email' => 'retry@example.test', 'auto_dispatch' => true, 'notified_at' => null]);
    expect($fake->attempts)->toBe(2);
    $pending = $class->scheduleChanges()->firstOrFail()->studentNotifications()->firstOrFail()->fresh();
    expect($pending->database_queued_at)->not->toBeNull()
        ->and($pending->mail_queued_at)->toBeNull()
        ->and($pending->notified_at)->toBeNull();
    $fake->assertSentToTimes($user, ClassScheduleChangedNotification::class, 1);

    $fake->failMail = false;
    expect($service->dispatchPendingAutomatic())->toBe(1)
        ->and($pending->fresh()->notified_at)->not->toBeNull()
        ->and($service->dispatchPendingAutomatic())->toBe(0);
    $fake->assertSentToTimes($user, ClassScheduleChangedNotification::class, 1);
    $fake->assertSentOnDemandTimes(ClassScheduleChangedNotification::class, 1);
});

it('skips archived class notifications before limiting automatic retries', function () {
    Notification::fake();
    $service = app(ClassScheduleChangeNotificationService::class);
    $archivedClass = Classes::factory()->create();
    $liveClass = Classes::factory()->create();
    $archivedStudent = Student::factory()->create(['email' => 'archived@example.test']);
    $liveStudent = Student::factory()->create(['email' => 'live@example.test']);
    ClassEnrollment::factory()->create(['class_id' => $liveClass->id, 'student_id' => $liveStudent->id, 'status' => true]);
    $archivedChange = $archivedClass->scheduleChanges()->create(['old_schedule' => [], 'new_schedule' => []]);
    $liveChange = $liveClass->scheduleChanges()->create(['old_schedule' => [], 'new_schedule' => []]);
    $archivedNotice = $archivedChange->studentNotifications()->create(['student_id' => $archivedStudent->id, 'email' => $archivedStudent->email, 'auto_dispatch' => true]);
    $liveNotice = $liveChange->studentNotifications()->create(['student_id' => $liveStudent->id, 'email' => $liveStudent->email, 'auto_dispatch' => true]);
    $archivedClass->delete();

    expect($service->dispatchPendingAutomatic(1))->toBe(1)
        ->and($liveNotice->fresh()->notified_at)->not->toBeNull()
        ->and($archivedNotice->fresh()->notified_at)->toBeNull();
    Notification::assertSentOnDemandTimes(ClassScheduleChangedNotification::class, 1);
});

it('records a manual notice when automatic delivery is suppressed', function () {
    Notification::fake();
    $service = app(ClassScheduleChangeNotificationService::class);
    $class = Classes::factory()->create();
    $meeting = Schedule::factory()->create(['class_id' => $class->id, 'day_of_week' => 'Monday']);
    $student = Student::factory()->create(['email' => 'manual@example.test']);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);
    $snapshot = $service->snapshot($class);
    $meeting->update(['day_of_week' => 'Tuesday']);

    $service->notifyIfChanged($class, $snapshot, null, false);

    $notice = $class->scheduleChanges()->firstOrFail()->studentNotifications()->firstOrFail();
    expect($notice->auto_dispatch)->toBeFalse()->and($notice->notified_at)->toBeNull();
    Notification::assertNothingSent();
});

it('uses resolved enrollment status when creating and sending notices', function () {
    Notification::fake();
    $service = app(ClassScheduleChangeNotificationService::class);
    $class = Classes::factory()->create();
    $meeting = Schedule::factory()->create(['class_id' => $class->id, 'status' => 'published', 'day_of_week' => 'Monday']);
    $enrolled = Student::factory()->create(['email' => 'enrolled-status@example.test']);
    $dropped = Student::factory()->create(['email' => 'dropped-status@example.test']);
    $legacyInactive = Student::factory()->create(['email' => 'legacy-inactive@example.test']);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $enrolled->id, 'status' => true, 'enrollment_status' => ClassEnrollment::STATUS_AUDIT]);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $dropped->id, 'status' => true, 'enrollment_status' => ClassEnrollment::STATUS_DROPPED]);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $legacyInactive->id, 'status' => false, 'enrollment_status' => ClassEnrollment::STATUS_ENROLLED]);
    $snapshot = $service->snapshot($class);
    $meeting->update(['day_of_week' => 'Tuesday']);

    $service->notifyIfChanged($class, $snapshot, null, false);
    $change = $class->scheduleChanges()->firstOrFail();
    expect($change->studentNotifications()->pluck('student_id')->map(fn ($id) => (int) $id)->all())->toBe([(int) $enrolled->id]);
    $result = $service->notifyPendingForClass($class);
    expect($result)->toBe(['count' => 1, 'recipients' => 1, 'failed' => 0]);
    Notification::assertSentOnDemandTimes(ClassScheduleChangedNotification::class, 1);
});

it('does not send pending notices to dropped or unreachable students', function () {
    Notification::fake();
    $service = app(ClassScheduleChangeNotificationService::class);
    $class = Classes::factory()->create();
    $dropped = Student::factory()->create(['email' => 'dropped@example.test']);
    $unreachable = Student::factory()->create(['user_id' => null, 'email' => null]);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $dropped->id, 'status' => false, 'enrollment_status' => ClassEnrollment::STATUS_DROPPED]);
    ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $unreachable->id, 'status' => true]);
    $change = $class->scheduleChanges()->create(['old_schedule' => [], 'new_schedule' => []]);
    $droppedNotice = $change->studentNotifications()->create(['student_id' => $dropped->id, 'email' => $dropped->email]);
    $unreachableNotice = $change->studentNotifications()->create(['student_id' => $unreachable->id, 'email' => null]);

    $result = $service->notifyPendingForClass($class);

    expect($result)->toBe(['count' => 1, 'recipients' => 0, 'failed' => 1])
        ->and($droppedNotice->fresh()->notified_at)->toBeNull()
        ->and($unreachableNotice->fresh()->notified_at)->toBeNull();
    Notification::assertNothingSent();
});

it('continues to other recipients after a manual delivery fails', function () {
    $service = app(ClassScheduleChangeNotificationService::class);
    $class = Classes::factory()->create();
    $firstUser = User::factory()->create(['role' => UserRole::Student, 'email' => 'fail@example.test']);
    $first = Student::factory()->create(['user_id' => $firstUser->id, 'email' => 'fail@example.test']);
    $second = Student::factory()->create(['user_id' => null, 'email' => 'success@example.test']);
    foreach ([$first, $second] as $student) {
        ClassEnrollment::factory()->create(['class_id' => $class->id, 'student_id' => $student->id, 'status' => true]);
    }
    $change = $class->scheduleChanges()->create(['old_schedule' => [], 'new_schedule' => []]);
    $failed = $change->studentNotifications()->create(['student_id' => $first->id, 'user_id' => $firstUser->id, 'email' => $first->email]);
    $succeeded = $change->studentNotifications()->create(['student_id' => $second->id, 'email' => $second->email]);
    $fake = new class extends NotificationFake
    {
        public function send($notifiables, $notification)
        {
            if ($notifiables instanceof AnonymousNotifiable && ($notifiables->routes['mail'] ?? null) === 'fail@example.test') {
                throw new RuntimeException('Queue unavailable');
            }

            return parent::send($notifiables, $notification);
        }
    };
    Notification::swap($fake);
    $this->app->instance(Illuminate\Contracts\Notifications\Dispatcher::class, $fake);

    $result = $service->notifyPendingForClass($class);

    expect($result)->toBe(['count' => 2, 'recipients' => 2, 'failed' => 1])
        ->and($failed->fresh()->notified_at)->toBeNull()
        ->and($failed->fresh()->database_queued_at)->not->toBeNull()
        ->and($succeeded->fresh()->notified_at)->not->toBeNull();
    $fake->assertSentToTimes($firstUser, ClassScheduleChangedNotification::class, 1);
    $fake->assertSentOnDemandTimes(ClassScheduleChangedNotification::class, 1);
});

it('does not notify when normalized schedules are unchanged', function () {
    Notification::fake();

    $service = app(ClassScheduleChangeNotificationService::class);
    $room = Room::factory()->create(['name' => 'Room 101']);
    $class = Classes::factory()->create(['room_id' => $room->id]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $student = Student::factory()->create(['email' => 'student@example.test']);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $oldSnapshot = $service->snapshot($class);

    $class->schedules()->delete();
    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $room->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    expect($service->notifyIfChanged($class, $oldSnapshot))->toBe(0);

    expect(ClassScheduleChange::query()->where('class_id', $class->id)->exists())->toBeFalse();

    Notification::assertNothingSent();
});

it('does not notify inactive class enrollments', function () {
    Notification::fake();

    $service = app(ClassScheduleChangeNotificationService::class);
    $oldRoom = Room::factory()->create(['name' => 'Room 101']);
    $newRoom = Room::factory()->create(['name' => 'Room 202']);
    $class = Classes::factory()->create(['room_id' => $oldRoom->id]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $oldRoom->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student@example.test',
    ]);
    $student = Student::factory()->create([
        'email' => 'student@example.test',
        'user_id' => $studentUser->id,
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => false,
    ]);

    $oldSnapshot = $service->snapshot($class);

    $class->schedules()->delete();
    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $newRoom->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    expect($service->notifyIfChanged($class, $oldSnapshot))->toBe(0);

    Notification::assertNothingSent();
});

it('records pending notifications when automatic schedule alerts are disabled', function () {
    Notification::fake();

    $service = app(ClassScheduleChangeNotificationService::class);
    $oldRoom = Room::factory()->create(['name' => 'Room 101']);
    $newRoom = Room::factory()->create(['name' => 'Room 202']);
    $class = Classes::factory()->create([
        'room_id' => $oldRoom->id,
        'semester' => 1,
        'school_year' => '2024 - 2025',
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'notify_students_on_schedule_changes' => false,
        ]),
    ]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $oldRoom->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $student = Student::factory()->create(['email' => 'student@example.test']);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $oldSnapshot = $service->snapshot($class);

    $class->schedules()->delete();
    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $newRoom->id,
        'day_of_week' => 'Friday',
        'start_time' => '13:00',
        'end_time' => '14:30',
    ]);

    expect($service->notifyIfChanged($class, $oldSnapshot))->toBe(0);
    Notification::assertNothingSent();

    $pending = $service->pendingNotificationsForEnrollment($enrollment);

    expect($pending)->toHaveCount(1)
        ->and($service->previewPendingNotifications($pending)[0]['new_schedule'])->toContain('Friday, 1:00 PM-2:30 PM at Room 202');
});

it('manually notifies pending schedule changes for an enrollment', function () {
    Notification::fake();

    $service = app(ClassScheduleChangeNotificationService::class);
    $oldRoom = Room::factory()->create(['name' => 'Room 101']);
    $newRoom = Room::factory()->create(['name' => 'Room 202']);
    $class = Classes::factory()->create([
        'room_id' => $oldRoom->id,
        'semester' => 1,
        'school_year' => '2024 - 2025',
        'settings' => array_merge(Classes::getDefaultSettings(), [
            'notify_students_on_schedule_changes' => false,
        ]),
    ]);

    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $oldRoom->id,
        'day_of_week' => 'Monday',
        'start_time' => '08:00',
        'end_time' => '09:00',
    ]);

    $studentUser = User::factory()->create([
        'role' => UserRole::Student,
        'email' => 'student@example.test',
    ]);
    $student = Student::factory()->create([
        'email' => 'student@example.test',
        'user_id' => $studentUser->id,
    ]);
    $enrollment = StudentEnrollment::factory()->create([
        'student_id' => $student->id,
        'semester' => 1,
        'school_year' => '2024 - 2025',
    ]);
    ClassEnrollment::factory()->create([
        'class_id' => $class->id,
        'student_id' => $student->id,
        'status' => true,
    ]);

    $oldSnapshot = $service->snapshot($class);

    $class->schedules()->delete();
    Schedule::factory()->create([
        'class_id' => $class->id,
        'room_id' => $newRoom->id,
        'day_of_week' => 'Tuesday',
        'start_time' => '10:00',
        'end_time' => '11:00',
    ]);

    $service->notifyIfChanged($class, $oldSnapshot);
    $result = $service->notifyPendingForEnrollment($enrollment);

    expect($result)->toBe(['count' => 1, 'recipients' => 2, 'failed' => 0])
        ->and($service->pendingNotificationsForEnrollment($enrollment))->toHaveCount(0);

    Notification::assertSentTo($studentUser, ClassScheduleChangedNotification::class);
    Notification::assertSentOnDemand(ClassScheduleChangedNotification::class);
});
