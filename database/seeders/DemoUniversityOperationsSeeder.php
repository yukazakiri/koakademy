<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DemoUniversityOperationsSeeder extends Seeder
{
    private const YEAR = '2026 - 2027';

    private const SCHOOL_ID = 8;

    private const SEMESTER = 1;

    private string $now = '';

    private int $adminId = 1;

    /** @var list<int> */
    private array $userIds = [];

    /** @var list<object> */
    private array $faculty = [];

    /** @var list<object> */
    private array $students = [];

    /** @var list<object> */
    private array $classes = [];

    /** @var array<int, list<object>> */
    private array $enrollmentsByClass = [];

    /** @var array<int, object> */
    private array $scheduleByClass = [];

    public function run(): void
    {
        $this->now = now()->toDateTimeString();
        $this->adminId = (int) (DB::table('users')->whereIn('role', ['super_admin', 'admin'])->value('id') ?? 1);
        $this->userIds = DB::table('users')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $this->faculty = DB::table('faculty')->get()->all();
        $this->students = DB::table('students')->whereNull('deleted_at')->get()->all();
        $this->classes = DB::table('classes')->where('school_year', self::YEAR)->where('semester', self::SEMESTER)->get()->all();

        foreach (DB::table('schedule')->whereIn('class_id', array_map(fn ($class) => $class->id, $this->classes))->orderBy('id')->get() as $schedule) {
            $this->scheduleByClass[$schedule->class_id] ??= $schedule;
        }

        $classIds = array_map(fn ($class) => $class->id, $this->classes);
        foreach (DB::table('class_enrollments')->whereIn('class_id', $classIds)->get() as $enrollment) {
            $this->enrollmentsByClass[$enrollment->class_id][] = $enrollment;
        }

        $this->seedEvents();
        $this->seedBookings();
        $this->seedAttendance();
        $this->seedClassPosts();
        $this->seedHelpTickets();
        $this->seedAnnouncements();
        $this->seedNotifications();
        $this->seedNewsletterAndOnboarding();
        $this->seedTags();
        $this->seedScheduleChanges();
        $this->seedFacultyDeadlinesAndFields();
        $this->seedForms();
        $this->seedMails();

        $this->command?->info('Operations data ready: '.DB::table('events')->count().' events, '.DB::table('class_attendance_records')->count().' attendance records, '.DB::table('class_posts')->count().' class posts, '.DB::table('mails')->count().' mails.');
    }

    private function seedEvents(): void
    {
        $this->command?->info('Creating events, reminders, and RSVPs...');

        $types = ['event', 'holiday', 'exam', 'seminar', 'sports', 'cultural', 'meeting'];
        $titles = [
            'General Assembly', 'Founders Day', 'Midterm Examinations', 'Research Colloquium',
            'Intramural Games', 'Cultural Festival', 'Faculty Meeting', 'Career Fair',
            'Community Outreach', 'Science Exhibit', 'Leadership Summit', 'Alumni Homecoming',
        ];
        $eventIds = [];
        for ($i = 0; $i < 60; $i++) {
            $start = Carbon::create(2026, 8, 3)->addDays($i * 5);
            $type = $types[$i % count($types)];
            $eventIds[] = DB::table('events')->insertGetId([
                'title' => $titles[$i % count($titles)].' '.($i + 1),
                'description' => 'A university-wide '.$type.' organized for students, faculty, and staff.',
                'type' => $type,
                'category' => $type === 'exam' ? 'academic' : 'institutional',
                'location' => ['Main Auditorium', 'Covered Court', 'Library Hall', 'Room 301', 'Online'][$i % 5],
                'start_datetime' => $start->toDateTimeString(),
                'end_datetime' => $start->copy()->addHours(3)->toDateTimeString(),
                'is_all_day' => $type === 'holiday' ? 1 : 0,
                'recurrence_type' => null,
                'recurrence_data' => null,
                'recurrence_end_date' => null,
                'max_attendees' => $type === 'seminar' ? 200 : 500,
                'requires_rsvp' => in_array($type, ['seminar', 'sports', 'cultural', 'meeting'], true) ? 1 : 0,
                'allow_guests' => 1,
                'status' => $start->isPast() ? 'completed' : 'active',
                'visibility' => $i % 3 === 0 ? 'public' : 'private',
                'custom_fields' => json_encode(['organizer' => 'Office of Student Affairs']),
                'notes' => 'Please coordinate with the organizing office for special accommodations.',
                'created_by' => $this->adminId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $usedRsvps = [];
        $rsvps = [];
        $reminders = [];
        for ($i = 0; $i < 800; $i++) {
            $eventId = $eventIds[$i % count($eventIds)];
            $userId = $this->userIds[($i * 7) % count($this->userIds)];
            $key = $eventId.'|'.$userId;
            if (isset($usedRsvps[$key])) {
                continue;
            }
            $usedRsvps[$key] = true;
            $rsvps[] = [
                'event_id' => $eventId,
                'user_id' => $userId,
                'response' => ['yes', 'no', 'maybe', 'pending'][$i % 4],
                'guest_count' => $i % 5,
                'dietary_requirements' => $i % 11 === 0 ? 'Vegetarian' : null,
                'special_requests' => null,
                'notes' => null,
                'responded_at' => $this->now,
                'checked_in' => $i % 6 === 0 ? 1 : 0,
                'checked_in_at' => $i % 6 === 0 ? $this->now : null,
                'custom_responses' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        $usedReminders = [];
        for ($i = 0; $i < 300; $i++) {
            $eventId = $eventIds[$i % count($eventIds)];
            $userId = $this->userIds[($i * 11) % count($this->userIds)];
            $key = $eventId.'|'.$userId.'|'.$i % 3;
            if (isset($usedReminders[$key])) {
                continue;
            }
            $usedReminders[$key] = true;
            $reminders[] = [
                'event_id' => $eventId,
                'user_id' => $userId,
                'reminder_type' => ['email', 'database'][$i % 2],
                'minutes_before' => [60, 1440, 10080][$i % 3],
                'status' => ['pending', 'sent', 'sent'][$i % 3],
                'scheduled_at' => Carbon::create(2026, 8, 3)->addDays($i % 60)->subHour()->toDateTimeString(),
                'sent_at' => $i % 3 === 0 ? null : $this->now,
                'message' => 'Reminder for your upcoming university event.',
                'delivery_data' => json_encode(['channel' => 'mail']),
                'failure_reason' => null,
                'retry_count' => 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        foreach (array_chunk($rsvps, 300) as $chunk) {
            DB::table('event_rsvps')->insert($chunk);
        }
        foreach (array_chunk($reminders, 300) as $chunk) {
            DB::table('event_reminders')->insert($chunk);
        }
    }

    private function seedBookings(): void
    {
        $this->command?->info('Creating bookable resources and bookings...');

        $types = ['classroom', 'laboratory', 'equipment', 'vehicle', 'auditorium'];
        $resourceIds = [];
        for ($i = 0; $i < 30; $i++) {
            $resourceIds[] = DB::table('resource_bookings')->insertGetId([
                'resource_type' => $types[$i % count($types)],
                'resource_name' => ['Room ', 'Lab ', 'Projector ', 'Van ', 'Auditorium '][$i % 5].($i + 1),
                'resource_description' => 'A reservable university resource managed by the facilities office.',
                'location' => ['Main Building', 'Science Wing', 'Engineering Hall', 'Transport Hub'][$i % 4],
                'capacity' => [40, 30, 1, 12, 300][$i % 5],
                'features' => json_encode(['air_conditioning', 'projector', 'wifi']),
                'hourly_rate' => [0, 250, 150, 500, 1500][$i % 5],
                'availability_schedule' => json_encode(['monday_friday' => '07:00-19:00', 'saturday' => '08:00-17:00']),
                'requires_approval' => $i % 2 === 0 ? 1 : 0,
                'is_active' => 1,
                'booking_rules' => json_encode(['max_hours' => 8, 'advance_days' => 14]),
                'terms_and_conditions' => 'The requesting party is responsible for returning the resource in good condition.',
                'managed_by' => $this->adminId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $bookings = [];
        for ($i = 0; $i < 150; $i++) {
            $start = Carbon::create(2026, 8, 5)->addDays(($i * 2) % 120)->setTime(8 + ($i % 8), 0);
            $status = ['pending', 'approved', 'approved', 'completed', 'cancelled', 'rejected'][$i % 6];
            $bookings[] = [
                'resource_booking_id' => $resourceIds[$i % count($resourceIds)],
                'booked_by' => $this->userIds[$i % count($this->userIds)],
                'start_datetime' => $start->toDateTimeString(),
                'end_datetime' => $start->copy()->addHours(2)->toDateTimeString(),
                'purpose' => ['Review session', 'Laboratory work', 'Seminar', 'Student activity', 'Faculty meeting'][$i % 5],
                'notes' => 'Please prepare the room before the reserved time.',
                'expected_attendees' => 10 + ($i % 40),
                'status' => $status,
                'total_cost' => $status === 'approved' || $status === 'completed' ? 500 : 0,
                'approved_by' => in_array($status, ['approved', 'completed'], true) ? $this->adminId : null,
                'approved_at' => in_array($status, ['approved', 'completed'], true) ? $this->now : null,
                'approval_notes' => in_array($status, ['approved', 'completed'], true) ? 'Approved by the facilities office.' : null,
                'cancelled_at' => $status === 'cancelled' ? $this->now : null,
                'cancellation_reason' => $status === 'cancelled' ? 'Schedule conflict.' : null,
                'additional_requirements' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }

        DB::table('bookings')->insert($bookings);
    }

    private function seedAttendance(): void
    {
        $this->command?->info('Recording attendance sessions and records...');

        $recordRows = [];
        $sessionDates = ['2026-08-10', '2026-08-17', '2026-08-24'];

        foreach ($this->classes as $class) {
            $enrollments = $this->enrollmentsByClass[$class->id] ?? [];
            if ($enrollments === []) {
                continue;
            }

            foreach ($sessionDates as $week => $date) {
                $schedule = $this->scheduleByClass[$class->id] ?? null;
                $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'excused' => 0];
                $sessionId = DB::table('class_attendance_sessions')->insertGetId([
                    'class_id' => $class->id,
                    'schedule_id' => $schedule?->id,
                    'session_date' => $date,
                    'starts_at' => $schedule?->start_time ?? '08:00:00',
                    'ends_at' => $schedule?->end_time ?? '10:00:00',
                    'taken_by' => $class->faculty_id,
                    'topic' => 'Week '.($week + 1).': Course orientation and guided discussion',
                    'notes' => 'Regular class meeting.',
                    'is_locked' => 1,
                    'locked_at' => $this->now,
                    'summary' => null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    'is_no_meeting' => 0,
                    'no_meeting_reason' => null,
                ]);

                foreach ($enrollments as $enrollmentIndex => $enrollment) {
                    $status = match (($enrollmentIndex + $week) % 10) {
                        0, 1 => 'absent',
                        2 => 'late',
                        3 => 'excused',
                        default => 'present',
                    };
                    $counts[$status]++;
                    $recordRows[] = [
                        'class_attendance_session_id' => $sessionId,
                        'class_enrollment_id' => $enrollment->id,
                        'class_id' => $class->id,
                        'student_id' => $enrollment->student_id,
                        'status' => $status,
                        'remarks' => $status === 'excused' ? 'Excused with a medical certificate.' : null,
                        'marked_by' => $class->faculty_id,
                        'marked_at' => $this->now,
                        'created_at' => $this->now,
                        'updated_at' => $this->now,
                    ];
                }

                DB::table('class_attendance_sessions')->where('id', $sessionId)->update([
                    'summary' => json_encode($counts + ['total' => count($enrollments)]),
                ]);
            }
        }

        foreach (array_chunk($recordRows, 400) as $chunk) {
            DB::table('class_attendance_records')->insert($chunk);
        }
    }

    private function seedClassPosts(): void
    {
        $this->command?->info('Creating class posts and submissions...');

        $submissions = [];
        $statuses = ['backlog', 'in_progress', 'review', 'done', 'blocked'];

        foreach ($this->classes as $index => $class) {
            $enrollments = $this->enrollmentsByClass[$class->id] ?? [];
            $announcementId = DB::table('class_posts')->insertGetId([
                'class_id' => $class->id,
                'title' => 'Welcome to the course',
                'content' => 'Please review the syllabus and prepare for the first graded activity.',
                'type' => 'announcement',
                'attachments' => json_encode([]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'status' => 'done',
                'priority' => 'medium',
                'start_date' => '2026-08-03',
                'due_date' => null,
                'progress_percent' => 100,
                'assigned_faculty_id' => $class->faculty_id,
                'total_points' => null,
                'instruction' => null,
                'audience_mode' => 'all_students',
                'assigned_student_ids' => null,
                'rubric' => null,
            ]);

            $assignmentId = DB::table('class_posts')->insertGetId([
                'class_id' => $class->id,
                'title' => 'Week 1 graded activity',
                'content' => 'Complete the assigned exercise and submit your work before the deadline.',
                'type' => 'assignment',
                'attachments' => json_encode([[
                    'name' => 'instructions.pdf',
                    'url' => 'class-posts/'.$class->id.'/instructions.pdf',
                    'kind' => 'file',
                ]]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'status' => $statuses[$index % count($statuses)],
                'priority' => ['low', 'medium', 'high'][$index % 3],
                'start_date' => '2026-08-10',
                'due_date' => '2026-08-24',
                'progress_percent' => [0, 25, 50, 100, 10][$index % 5],
                'assigned_faculty_id' => $class->faculty_id,
                'total_points' => 100,
                'instruction' => 'Submit a PDF with your answers and include your name and section.',
                'audience_mode' => 'all_students',
                'assigned_student_ids' => null,
                'rubric' => json_encode([
                    ['title' => 'Content', 'description' => 'Depth and accuracy of the answer.', 'points' => 50, 'levels' => []],
                    ['title' => 'Accuracy', 'description' => 'Correctness of facts and solutions.', 'points' => 30, 'levels' => []],
                    ['title' => 'Presentation', 'description' => 'Organization, formatting, and clarity.', 'points' => 20, 'levels' => []],
                ]),
            ]);

            foreach ($enrollments as $enrollmentIndex => $enrollment) {
                if (($enrollmentIndex + $index) % 3 === 0) {
                    continue;
                }
                $graded = ($enrollmentIndex + $index) % 4 === 0;
                $submissions[] = [
                    'class_post_id' => $assignmentId,
                    'student_id' => $enrollment->student_id,
                    'content' => 'Please see the attached submission for my answers.',
                    'attachments' => json_encode([[
                        'name' => 'submission-'.$enrollment->student_id.'.pdf',
                        'url' => 'class-post-submissions/'.$class->id.'/'.$enrollment->student_id.'.pdf',
                        'kind' => 'file',
                    ]]),
                    'points' => $graded ? 80 + (($enrollmentIndex * 7) % 20) : null,
                    'feedback' => $graded ? 'Good work. Review the comments for improvement.' : null,
                    'status' => $graded ? 'graded' : 'submitted',
                    'submitted_at' => Carbon::create(2026, 8, 20)->addDays($enrollmentIndex % 5)->toDateTimeString(),
                    'graded_at' => $graded ? $this->now : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        foreach (array_chunk($submissions, 300) as $chunk) {
            DB::table('class_post_submissions')->insert($chunk);
        }
    }

    private function seedHelpTickets(): void
    {
        $this->command?->info('Creating help tickets and replies...');

        $subjects = ['Cannot access the student portal', 'Incorrect tuition balance', 'Missing grade', 'Schedule conflict', 'Library account issue'];
        $replies = [];
        for ($i = 0; $i < 120; $i++) {
            $ticketId = DB::table('help_tickets')->insertGetId([
                'user_id' => $this->userIds[$i % count($this->userIds)],
                'type' => ['technical', 'academic', 'financial', 'account', 'other'][$i % 5],
                'subject' => $subjects[$i % count($subjects)],
                'message' => 'I would like to request assistance regarding this concern. Thank you.',
                'status' => ['open', 'in_progress', 'resolved', 'closed'][$i % 4],
                'priority' => ['low', 'medium', 'high', 'urgent'][$i % 4],
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'attachments' => json_encode([]),
            ]);

            $replyCount = 1 + ($i % 3);
            for ($j = 0; $j < $replyCount; $j++) {
                $replies[] = [
                    'help_ticket_id' => $ticketId,
                    'user_id' => $j === 0 ? $this->adminId : $this->userIds[($i + $j) % count($this->userIds)],
                    'message' => $j === 0 ? 'Thank you for reaching out. We are looking into this concern.' : 'We have applied the necessary correction. Please check again.',
                    'attachments' => json_encode([]),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        foreach (array_chunk($replies, 300) as $chunk) {
            DB::table('help_ticket_replies')->insert($chunk);
        }
    }

    private function seedAnnouncements(): void
    {
        $this->command?->info('Publishing announcements...');

        $titles = ['Enrollment schedule', 'Scholarship applications', 'Library hours', 'Exam guidelines', 'Campus maintenance', 'Student organization fair'];
        $rows = [];
        for ($i = 0; $i < 50; $i++) {
            $published = $i % 5 !== 0;
            $start = Carbon::create(2026, 8, 1)->addDays($i * 3);
            $rows[] = [
                'title' => $titles[$i % count($titles)].' '.($i + 1),
                'content' => 'Please read this announcement carefully and coordinate with the responsible office for questions.',
                'type' => ['info', 'success', 'warning', 'danger'][$i % 4],
                'is_active' => $published ? 1 : 0,
                'starts_at' => $start->toDateTimeString(),
                'ends_at' => $start->copy()->addDays(14)->toDateTimeString(),
                'created_by' => $this->adminId,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'expires_at' => $start->copy()->addDays(14)->toDateTimeString(),
                'published_at' => $published ? $this->now : null,
                'status' => $published ? 'published' : 'draft',
                'priority' => ['low', 'medium', 'high', 'urgent'][$i % 4],
                'display_mode' => ['banner', 'toast', 'modal'][$i % 3],
                'requires_acknowledgment' => $i % 7 === 0 ? 1 : 0,
                'link' => null,
                'slug' => 'demo-announcement-'.($i + 1),
                'is_global' => 1,
                'class_id' => null,
                'school_id' => self::SCHOOL_ID,
                'attachments' => json_encode([]),
                'visibility_scope' => ['global', 'authenticated_only', 'role_based'][$i % 3],
                'audience_roles' => json_encode(['student', 'faculty']),
                'display_locations' => json_encode(['all']),
                'action_label' => null,
            ];
        }

        DB::table('announcements')->insert($rows);
    }

    private function seedNotifications(): void
    {
        $this->command?->info('Generating notifications...');

        $rows = [];
        for ($i = 0; $i < 800; $i++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\DemoUniversityNotification',
                'notifiable_type' => 'App\\Models\\User',
                'notifiable_id' => $this->userIds[$i % count($this->userIds)],
                'read_at' => $i % 3 === 0 ? $this->now : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'data' => json_encode([
                    'title' => ['New announcement', 'Payment received', 'Grade posted', 'Schedule updated'][$i % 4],
                    'message' => 'You have a new update in the KoAkademy portal.',
                    'url' => '/dashboard',
                    'icon' => 'bell',
                ]),
            ];
        }

        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }
    }

    private function seedNewsletterAndOnboarding(): void
    {
        $this->command?->info('Creating newsletter subscriptions and onboarding progress...');

        $subscriptions = [];
        $usedUsers = [];
        for ($i = 0; $i < 300; $i++) {
            $userId = $this->userIds[$i % count($this->userIds)];
            if (isset($usedUsers[$userId])) {
                continue;
            }
            $usedUsers[$userId] = true;
            $status = ['subscribed', 'subscribed', 'pending', 'unsubscribed'][$i % 4];
            $subscriptions[] = [
                'user_id' => $userId,
                'email' => DB::table('users')->where('id', $userId)->value('email'),
                'status' => $status,
                'subscribed_at' => $status === 'subscribed' ? $this->now : null,
                'declined_at' => $status === 'unsubscribed' ? $this->now : null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'provider' => 'local',
            ];
        }
        DB::table('newsletter_subscriptions')->insert($subscriptions);

        $progress = [];
        $usedProgress = [];
        $dismissals = [];
        $usedDismissals = [];
        $features = ['faculty_onboarding', 'student_tour', 'finance_tour', 'library_tour', 'forms_tour'];
        foreach ($this->userIds as $index => $userId) {
            if (count($progress) >= 50) {
                break;
            }
            $variant = $index % 2 === 0 ? 'faculty' : 'student';
            $key = $userId.'|'.$variant;
            if (isset($usedProgress[$key])) {
                continue;
            }
            $usedProgress[$key] = true;
            $completed = $index % 3 === 0;
            $progress[] = [
                'user_id' => $userId,
                'variant' => $variant,
                'completed_steps' => json_encode($completed ? ['profile', 'preferences', 'tour'] : ['profile']),
                'checklist_state' => json_encode(['profile' => true, 'preferences' => $completed]),
                'started_at' => $this->now,
                'completed_at' => $completed ? $this->now : null,
                'last_seen_at' => $this->now,
                'current_step_index' => $completed ? 3 : 1,
                'is_dismissed' => 0,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('onboarding_progress')->insert($progress);

        foreach ($this->userIds as $index => $userId) {
            if (count($dismissals) >= 100) {
                break;
            }
            $feature = $features[$index % count($features)];
            $key = $userId.'|'.$feature;
            if (isset($usedDismissals[$key])) {
                continue;
            }
            $usedDismissals[$key] = true;
            $dismissals[] = [
                'user_id' => $userId,
                'feature_key' => $feature,
                'dismissed_at' => $this->now,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('onboarding_dismissals')->insert($dismissals);
    }

    private function seedTags(): void
    {
        $this->command?->info('Applying tags...');

        $names = ['scholarship', 'leadership', 'research', 'sports', 'arts', 'technology', 'community', 'honors', 'international', 'service'];
        $tagIds = [];
        for ($i = 0; $i < 40; $i++) {
            $name = $names[$i % count($names)].' '.($i + 1);
            $tagIds[] = DB::table('tags')->insertGetId([
                'name' => $name,
                'slug' => Str::slug($name),
                'type' => ['student', 'class', 'announcement'][$i % 3],
                'order_column' => $i + 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }

        $classIds = array_map(fn ($class) => $class->id, $this->classes);
        $announcementIds = DB::table('announcements')->pluck('id')->all();
        $rows = [];
        $used = [];
        for ($i = 0; count($rows) < 800 && $i < 4000; $i++) {
            $tagId = $tagIds[$i % count($tagIds)];
            $type = ['App\\Models\\Student', 'App\\Models\\Classes', 'Modules\\Announcement\\Models\\Announcement'][$i % 3];
            $id = match ($type) {
                'App\\Models\\Student' => $this->students[$i % count($this->students)]->id,
                'App\\Models\\Classes' => $classIds[$i % count($classIds)],
                default => $announcementIds[$i % count($announcementIds)],
            };
            $key = $tagId.'|'.$type.'|'.$id;
            if (isset($used[$key])) {
                continue;
            }
            $used[$key] = true;
            $rows[] = ['tag_id' => $tagId, 'taggable_type' => $type, 'taggable_id' => $id];
        }
        DB::table('taggables')->insert($rows);
    }

    private function seedScheduleChanges(): void
    {
        $this->command?->info('Recording class schedule changes...');

        $notifications = [];
        for ($i = 0; $i < 40; $i++) {
            $class = $this->classes[$i % count($this->classes)];
            $changeId = DB::table('class_schedule_changes')->insertGetId([
                'class_id' => $class->id,
                'changed_by_user_id' => $this->adminId,
                'old_schedule' => json_encode(['day' => 'Monday', 'start' => '08:00', 'end' => '10:00']),
                'new_schedule' => json_encode(['day' => 'Wednesday', 'start' => '09:00', 'end' => '11:00']),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);

            $enrollments = array_slice($this->enrollmentsByClass[$class->id] ?? [], 0, 10);
            foreach ($enrollments as $enrollment) {
                $student = DB::table('students')->where('id', $enrollment->student_id)->first();
                if (! $student) {
                    continue;
                }
                $notifications[] = [
                    'class_schedule_change_id' => $changeId,
                    'student_id' => $student->id,
                    'user_id' => $student->user_id,
                    'email' => $student->email,
                    'notified_at' => $this->now,
                    'notified_by_user_id' => $this->adminId,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }

        foreach (array_chunk($notifications, 300) as $chunk) {
            DB::table('class_schedule_change_student_notifications')->insert($chunk);
        }
    }

    private function seedFacultyDeadlinesAndFields(): void
    {
        $this->command?->info('Creating faculty deadlines and custom field values...');

        $deadlines = [];
        for ($i = 0; $i < 120; $i++) {
            $faculty = $this->faculty[$i % count($this->faculty)];
            $class = $this->classes[$i % count($this->classes)];
            $deadlines[] = [
                'faculty_id' => $faculty->id,
                'class_id' => $class->id,
                'title' => ['Submit prelim grades', 'Upload lesson plan', 'Complete faculty report', 'Attend department meeting'][$i % 4],
                'description' => 'Please complete this requirement before the deadline.',
                'due_date' => Carbon::create(2026, 8, 15)->addDays($i % 90)->toDateTimeString(),
                'priority' => ['low', 'medium', 'high'][$i % 3],
                'type' => ['grading', 'lesson_plan', 'report', 'meeting'][$i % 4],
                'is_active' => 1,
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ];
        }
        DB::table('faculty_deadlines')->insert($deadlines);

        $definitions = DB::table('faculty_custom_field_definitions')->where('school_id', self::SCHOOL_ID)->get();
        if ($definitions->isEmpty()) {
            return;
        }

        $values = [];
        $facultySlice = array_slice($this->faculty, 0, 15);
        foreach ($facultySlice as $facultyIndex => $faculty) {
            foreach ($definitions as $definitionIndex => $definition) {
                $values[] = [
                    'school_id' => self::SCHOOL_ID,
                    'faculty_id' => $faculty->id,
                    'faculty_custom_field_definition_id' => $definition->id,
                    'value' => 'DEMO-'.($facultyIndex + 1).'-'.($definitionIndex + 1),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }
        }
        DB::table('faculty_custom_field_values')->insert($values);
    }

    private function seedForms(): void
    {
        $this->command?->info('Building demo forms and responses...');

        $forms = [
            ['demo-student-feedback', 'Student Experience Feedback', 'Share your experience with university services.', 'anonymous'],
            ['demo-course-evaluation', 'Course Evaluation', 'Evaluate the course delivery and instructor support.', 'authenticated'],
            ['demo-event-registration', 'Event Registration', 'Register for an upcoming university event.', 'guest_identifier'],
            ['demo-library-feedback', 'Library Feedback', 'Tell us how we can improve library services.', 'anonymous'],
            ['demo-faculty-satisfaction', 'Faculty Satisfaction Survey', 'Help us improve faculty support services.', 'authenticated'],
            ['demo-alumni-tracer', 'Alumni Tracer Survey', 'Update your employment and alumni information.', 'guest_identifier'],
            ['demo-medical-history', 'Medical History Form', 'Provide your medical history for the clinic.', 'authenticated'],
            ['demo-parent-consent', 'Parent Consent Form', 'Consent for student participation in activities.', 'invitation'],
        ];

        $fieldSets = [
            [['full_name', 'Full name', 'text', true], ['email', 'Email address', 'email', true], ['rating', 'Overall rating', 'rating', true], ['comments', 'Comments', 'textarea', false], ['recommend', 'Would you recommend us?', 'yes_no', true], ['consent', 'I consent to the use of my feedback', 'checkbox', true]],
            [['student_id', 'Student ID', 'text', true], ['course', 'Course', 'text', true], ['instructor', 'Instructor', 'text', true], ['rating', 'Instructor rating', 'rating', true], ['comments', 'Additional comments', 'textarea', false], ['recommend', 'Would you recommend this course?', 'yes_no', true]],
            [['full_name', 'Full name', 'text', true], ['email', 'Email address', 'email', true], ['phone', 'Phone number', 'phone', false], ['organization', 'Organization', 'text', false], ['attendance_type', 'Attendance type', 'select', true], ['dietary', 'Dietary requirements', 'checkbox', false]],
            [['name', 'Name', 'text', false], ['email', 'Email address', 'email', false], ['resource_type', 'Resource type', 'select', true], ['satisfaction', 'Satisfaction', 'rating', true], ['comments', 'Comments', 'textarea', false], ['visit_frequency', 'Visit frequency', 'select', false]],
            [['employee_id', 'Employee ID', 'text', true], ['department', 'Department', 'select', true], ['role', 'Role', 'text', false], ['satisfaction', 'Overall satisfaction', 'rating', true], ['comments', 'Comments', 'textarea', false], ['recommend', 'Would you recommend KoAkademy?', 'yes_no', true]],
            [['full_name', 'Full name', 'text', true], ['graduation_year', 'Graduation year', 'year', true], ['program', 'Program', 'text', true], ['employment_status', 'Employment status', 'select', true], ['employer', 'Employer', 'text', false], ['comments', 'Comments', 'textarea', false]],
            [['full_name', 'Full name', 'text', true], ['birth_date', 'Birth date', 'date', true], ['blood_type', 'Blood type', 'select', false], ['allergies', 'Allergies', 'textarea', false], ['conditions', 'Existing conditions', 'checkbox', false], ['emergency_contact', 'Emergency contact', 'phone', true]],
            [['parent_name', 'Parent or guardian name', 'text', true], ['relationship', 'Relationship', 'select', true], ['email', 'Email address', 'email', true], ['phone', 'Phone number', 'phone', true], ['consent', 'I give my consent', 'yes_no', true], ['notes', 'Additional notes', 'textarea', false]],
        ];

        $optionSets = [
            'attendance_type' => ['In person', 'Online', 'Hybrid'],
            'resource_type' => ['Book', 'Journal', 'Database', 'Multimedia'],
            'visit_frequency' => ['Daily', 'Weekly', 'Monthly', 'Rarely'],
            'department' => ['Information Technology', 'Business Administration', 'Hospitality Management', 'Education', 'Engineering'],
            'employment_status' => ['Employed', 'Self-employed', 'Unemployed', 'Further studies'],
            'blood_type' => ['A+', 'A-', 'B+', 'B-', 'O+', 'O-', 'AB+', 'AB-'],
            'conditions' => ['None', 'Asthma', 'Diabetes', 'Hypertension', 'Heart condition'],
            'relationship' => ['Mother', 'Father', 'Guardian', 'Sibling'],
            'dietary' => ['None', 'Vegetarian', 'Vegan', 'Halal', 'Gluten-free'],
        ];

        $formIds = [];
        $fieldsByForm = [];
        foreach ($forms as $formIndex => [$slug, $title, $description, $accessMode]) {
            $formId = (string) Str::ulid();
            $formIds[] = $formId;
            DB::table('forms')->insert([
                'id' => $formId,
                'tenant_key' => null,
                'created_by' => $this->adminId,
                'title' => $title,
                'slug' => $slug,
                'description' => $description,
                'status' => 'published',
                'access_mode' => $accessMode,
                'identity_type' => $accessMode === 'guest_identifier' ? 'email' : null,
                'settings' => json_encode(['confirmation_message' => 'Thank you for your submission.']),
                'version' => 1,
                'published_at' => $this->now,
                'closes_at' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'template_id' => null,
            ]);

            $fields = [];
            foreach ($fieldSets[$formIndex] as $position => [$key, $label, $type, $required]) {
                $fieldId = (string) Str::ulid();
                $options = $optionSets[$key] ?? ($type === 'yes_no' ? ['Yes', 'No'] : []);
                DB::table('form_fields')->insert([
                    'id' => $fieldId,
                    'form_id' => $formId,
                    'field_key' => $key,
                    'label' => $label,
                    'type' => $type,
                    'description' => null,
                    'required' => $required ? 1 : 0,
                    'position' => $position + 1,
                    'options' => $options === [] ? null : json_encode($options),
                    'validation' => null,
                    'visibility' => null,
                    'mapping' => null,
                    'is_sensitive' => in_array($key, ['birth_date', 'phone', 'email'], true) ? 1 : 0,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    'section' => 'General',
                    'presentation' => null,
                    'behavior' => null,
                ]);
                $fields[] = ['id' => $fieldId, 'key' => $key, 'type' => $type, 'options' => $options];
            }
            $fieldsByForm[$formId] = $fields;
        }

        $responseIds = [];
        foreach ($formIds as $formIndex => $formId) {
            $fields = $fieldsByForm[$formId];
            $formResponseIds = [];
            for ($i = 0; $i < 30; $i++) {
                $responseId = (string) Str::ulid();
                $responseIds[] = $responseId;
                $formResponseIds[] = $responseId;
                $user = $this->students[($formIndex * 30 + $i) % count($this->students)];
                $email = $user->email ?? 'respondent'.$i.'@example.com';
                $status = ['submitted', 'submitted', 'reviewed', 'applied'][$i % 4];
                DB::table('form_responses')->insert([
                    'id' => $responseId,
                    'form_id' => $formId,
                    'tenant_key' => null,
                    'respondent_user_id' => (string) ($user->user_id ?? ''),
                    'respondent_email' => $email,
                    'respondent_identifier' => $email,
                    'respondent_email_hash' => hash('sha256', $email),
                    'respondent_identifier_hash' => hash('sha256', $email),
                    'status' => $status,
                    'review_notes' => $status === 'reviewed' ? 'Reviewed by the registrar.' : null,
                    'latest_revision' => 1,
                    'submitted_at' => $this->now,
                    'reviewed_by' => in_array($status, ['reviewed', 'applied'], true) ? $this->adminId : null,
                    'reviewed_at' => in_array($status, ['reviewed', 'applied'], true) ? $this->now : null,
                    'applied_by' => $status === 'applied' ? $this->adminId : null,
                    'applied_at' => $status === 'applied' ? $this->now : null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                $answers = [];
                foreach ($fields as $field) {
                    $answers[$field['key']] = match ($field['type']) {
                        'email' => $email,
                        'phone' => '09171234567',
                        'number', 'rating' => 5,
                        'year' => 2020 + ($i % 6),
                        'date' => '2000-01-15',
                        'yes_no' => 'Yes',
                        'checkbox' => $field['options'] === [] ? ['Yes'] : array_slice($field['options'], 0, 1),
                        'select', 'radio' => $field['options'][0] ?? 'Option',
                        default => 'Sample response '.$i,
                    };
                }

                DB::table('form_response_revisions')->insert([
                    'id' => (string) Str::ulid(),
                    'form_response_id' => $responseId,
                    'revision' => 1,
                    'answer_payload' => json_encode($answers),
                    'field_snapshot' => json_encode($fields),
                    'created_by' => (string) $this->adminId,
                    'created_at' => $this->now,
                ]);
            }

            for ($i = 0; $i < 10; $i++) {
                $responseId = $formResponseIds[$i];
                $student = $this->students[($formIndex * 10 + $i) % count($this->students)];
                $email = $student->email ?? 'invitee'.$i.'@example.com';
                DB::table('form_invitations')->insert([
                    'id' => (string) Str::ulid(),
                    'form_id' => $formId,
                    'model_key' => 'student',
                    'model_type' => 'App\\Models\\Student',
                    'model_id' => (string) $student->id,
                    'token_hash' => hash('sha256', $formId.'|'.$email.'|'.$i),
                    'recipient_email' => $email,
                    'status' => $i % 2 === 0 ? 'completed' : 'sent',
                    'expires_at' => Carbon::now()->addDays(30)->toDateTimeString(),
                    'sent_at' => $this->now,
                    'opened_at' => $this->now,
                    'completed_at' => $i % 2 === 0 ? $this->now : null,
                    'response_id' => $i % 2 === 0 ? $responseId : null,
                    'error_message' => null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);

                DB::table('form_response_links')->insert([
                    'id' => (string) Str::ulid(),
                    'form_response_id' => $responseId,
                    'model_key' => 'student',
                    'model_type' => 'App\\Models\\Student',
                    'model_id' => (string) $student->id,
                    'match_method' => 'email',
                    'status' => $i % 2 === 0 ? 'applied' : 'pending',
                    'applied_by' => $i % 2 === 0 ? $this->adminId : null,
                    'applied_at' => $i % 2 === 0 ? $this->now : null,
                    'error_message' => null,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ]);
            }

            for ($i = 0; $i < 20; $i++) {
                DB::table('form_audit_events')->insert([
                    'id' => (string) Str::ulid(),
                    'form_id' => $formId,
                    'form_response_id' => $i % 2 === 0 ? $formResponseIds[$i % 30] : null,
                    'actor_id' => (string) $this->adminId,
                    'action' => ['created', 'published', 'response_submitted', 'response_reviewed'][$i % 4],
                    'metadata' => json_encode(['source' => 'demo']),
                    'created_at' => $this->now,
                ]);
            }
        }

        $templates = [
            ['Demo Event Feedback Template', 'event', ['title', 'rating', 'comments']],
            ['Demo Student Survey Template', 'student', ['full_name', 'email', 'rating', 'comments']],
            ['Demo Faculty Survey Template', 'faculty', ['employee_id', 'department', 'satisfaction']],
            ['Demo Alumni Tracer Template', 'alumni', ['full_name', 'graduation_year', 'employment_status']],
        ];
        foreach ($templates as $template) {
            DB::table('form_templates')->insert([
                'id' => (string) Str::ulid(),
                'tenant_key' => null,
                'created_by' => $this->adminId,
                'name' => $template[0],
                'description' => 'A reusable demo form template.',
                'model_key' => $template[1],
                'definition' => json_encode(['fields' => $template[2]]),
                'created_at' => $this->now,
                'updated_at' => $this->now,
            ]);
        }
    }

    private function seedMails(): void
    {
        $this->command?->info('Archiving outbound mail history...');

        $recipients = [];
        $events = [];
        $attachments = [];
        $mailables = [];
        $subjects = ['Enrollment confirmation', 'Payment receipt', 'Class schedule update', 'Event invitation', 'Password reset'];

        for ($i = 0; $i < 300; $i++) {
            $student = $this->students[$i % count($this->students)];
            $email = $student->email ?? 'recipient'.$i.'@example.com';
            $mailId = DB::table('mails')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'mail_class' => 'App\\Mail\\DemoUniversityMail',
                'subject' => $subjects[$i % count($subjects)],
                'from' => json_encode(['address' => 'noreply@koakademy.edu', 'name' => 'KoAkademy']),
                'reply_to' => json_encode([['address' => 'support@koakademy.edu', 'name' => 'KoAkademy Support']]),
                'to' => json_encode([['address' => $email, 'name' => $student->first_name.' '.$student->last_name]]),
                'cc' => null,
                'bcc' => null,
                'html' => '<p>Dear '.$student->first_name.', this is an automated message from KoAkademy.</p>',
                'text' => 'Dear '.$student->first_name.', this is an automated message from KoAkademy.',
                'opens' => $i % 4,
                'clicks' => $i % 7,
                'sent_at' => $this->now,
                'resent_at' => null,
                'accepted_at' => $this->now,
                'delivered_at' => $this->now,
                'last_opened_at' => $i % 4 > 0 ? $this->now : null,
                'last_clicked_at' => $i % 7 > 0 ? $this->now : null,
                'complained_at' => null,
                'soft_bounced_at' => null,
                'hard_bounced_at' => null,
                'unsubscribed_at' => null,
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'mailer' => 'smtp',
                'stream_id' => null,
                'tags' => json_encode(['demo', 'transactional']),
                'transport' => 'log',
            ]);

            $recipients[] = [
                'mail_id' => $mailId,
                'email' => $email,
                'name' => $student->first_name.' '.$student->last_name,
                'domain' => 'students.koakademy.edu',
            ];
            if ($i % 3 === 0) {
                $recipients[] = [
                    'mail_id' => $mailId,
                    'email' => 'registrar@koakademy.edu',
                    'name' => 'Registrar Office',
                    'domain' => 'koakademy.edu',
                ];
            }

            $events[] = [
                'mail_id' => $mailId,
                'type' => 'delivered',
                'ip_address' => '127.0.0.1',
                'hostname' => 'localhost',
                'platform' => 'web',
                'os' => 'macOS',
                'browser' => 'Chrome',
                'user_agent' => 'Mozilla/5.0',
                'city' => 'Quezon City',
                'country_code' => 'PH',
                'link' => null,
                'tag' => null,
                'payload' => json_encode(['source' => 'demo']),
                'created_at' => $this->now,
                'updated_at' => $this->now,
                'occurred_at' => $this->now,
                'unsuppressed_at' => null,
            ];
            if ($i % 4 === 0) {
                $events[] = [
                    'mail_id' => $mailId,
                    'type' => 'opened',
                    'ip_address' => '127.0.0.1',
                    'hostname' => 'localhost',
                    'platform' => 'web',
                    'os' => 'macOS',
                    'browser' => 'Chrome',
                    'user_agent' => 'Mozilla/5.0',
                    'city' => 'Quezon City',
                    'country_code' => 'PH',
                    'link' => null,
                    'tag' => null,
                    'payload' => json_encode(['source' => 'demo']),
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                    'occurred_at' => $this->now,
                    'unsuppressed_at' => null,
                ];
            }

            if ($i % 5 === 0) {
                $attachments[] = [
                    'mail_id' => $mailId,
                    'disk' => 'local',
                    'uuid' => (string) Str::uuid(),
                    'filename' => 'document-'.$i.'.pdf',
                    'mime' => 'application/pdf',
                    'inline' => 0,
                    'size' => 120000,
                    'created_at' => $this->now,
                    'updated_at' => $this->now,
                ];
            }

            $mailables[] = [
                'mail_id' => $mailId,
                'mailable_type' => 'App\\Models\\Student',
                'mailable_id' => $student->id,
            ];
        }

        foreach (array_chunk($recipients, 300) as $chunk) {
            DB::table('mail_recipients')->insert($chunk);
        }
        foreach (array_chunk($events, 300) as $chunk) {
            DB::table('mail_events')->insert($chunk);
        }
        foreach (array_chunk($attachments, 300) as $chunk) {
            DB::table('mail_attachments')->insert($chunk);
        }
        foreach (array_chunk($mailables, 300) as $chunk) {
            DB::table('mailables')->insert($chunk);
        }
    }
}
