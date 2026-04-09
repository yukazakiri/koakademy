<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\NotificationCenter\Models\NotificationTemplate;

final class NotificationTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug' => 'announcement',
                'name' => 'Announcement',
                'description' => 'Bold, modern dark theme for system-wide announcements',
                'category' => 'informational',
                'mail_template' => 'notificationcenter::emails.templates.announcement',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'subtitle', 'content', 'action_url', 'action_text', 'metadata'],
                'styles' => ['theme' => 'dark', 'primary_color' => '#e94560'],
                'is_active' => true,
            ],
            [
                'slug' => 'success',
                'name' => 'Success / Achievement',
                'description' => 'Celebratory design for achievements and success messages',
                'category' => 'celebration',
                'mail_template' => 'notificationcenter::emails.templates.success',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'content', 'badge', 'achievement', 'stats', 'action_url'],
                'styles' => ['theme' => 'light', 'primary_color' => '#667eea'],
                'is_active' => true,
            ],
            [
                'slug' => 'official',
                'name' => 'Official Document',
                'description' => 'Formal letterhead style for official correspondence',
                'category' => 'formal',
                'mail_template' => 'notificationcenter::emails.templates.official',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'content', 'recipient_name', 'reference_number', 'details'],
                'styles' => ['theme' => 'light', 'primary_color' => '#1a365d'],
                'is_active' => true,
            ],
            [
                'slug' => 'warning',
                'name' => 'Urgent Alert',
                'description' => 'High-contrast design for critical warnings',
                'category' => 'alert',
                'mail_template' => 'notificationcenter::emails.templates.warning',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'content', 'priority', 'alert_code', 'action_items', 'deadline'],
                'styles' => ['theme' => 'dark', 'primary_color' => '#f59e0b'],
                'is_active' => true,
            ],
            [
                'slug' => 'reminder',
                'name' => 'Friendly Reminder',
                'description' => 'Warm pastel theme for gentle reminders',
                'category' => 'reminder',
                'mail_template' => 'notificationcenter::emails.templates.reminder',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'content', 'recipient_name', 'due_date', 'reminder_items'],
                'styles' => ['theme' => 'light', 'primary_color' => '#fcb69f'],
                'is_active' => true,
            ],
            [
                'slug' => 'class-suspension',
                'name' => 'Class Suspension',
                'description' => 'Weather/emergency class cancellation notices',
                'category' => 'academic',
                'mail_template' => 'notificationcenter::emails.templates.class-suspension',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'suspension_date', 'affected_levels', 'content', 'reason_details', 'resumption_info', 'instructions', 'issued_by'],
                'styles' => ['theme' => 'warning', 'primary_color' => '#f59e0b'],
                'is_active' => true,
            ],
            [
                'slug' => 'school-event',
                'name' => 'School Event',
                'description' => 'Intramurals, Foundation Day, and school activities',
                'category' => 'events',
                'mail_template' => 'notificationcenter::emails.templates.school-event',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'tagline', 'event_type', 'event_date', 'event_time', 'venue', 'content', 'activities', 'schedule', 'participants', 'requirements', 'organizer'],
                'styles' => ['theme' => 'vibrant', 'primary_color' => '#7c3aed'],
                'is_active' => true,
            ],
            [
                'slug' => 'academic-schedule',
                'name' => 'Academic Schedule',
                'description' => 'Exam schedules, registration periods, deadlines',
                'category' => 'academic',
                'mail_template' => 'notificationcenter::emails.templates.academic-schedule',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'school_year', 'period_name', 'content', 'important_dates', 'deadlines', 'notes', 'department'],
                'styles' => ['theme' => 'professional', 'primary_color' => '#1e3a5f'],
                'is_active' => true,
            ],
            [
                'slug' => 'payment-notice',
                'name' => 'Payment Notice',
                'description' => 'Tuition, fees, and payment reminders',
                'category' => 'finance',
                'mail_template' => 'notificationcenter::emails.templates.payment-notice',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'amount', 'currency', 'student_info', 'due_date', 'days_remaining', 'balance_breakdown', 'payment_methods', 'penalty_info'],
                'styles' => ['theme' => 'finance', 'primary_color' => '#7c3aed'],
                'is_active' => true,
            ],
            [
                'slug' => 'enrollment',
                'name' => 'Enrollment',
                'description' => 'Enrollment confirmations and status updates',
                'category' => 'academic',
                'mail_template' => 'notificationcenter::emails.templates.enrollment',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'student_name', 'student_id', 'course', 'year_level', 'section', 'school_year', 'semester', 'subjects', 'next_steps', 'assessment_url'],
                'styles' => ['theme' => 'success', 'primary_color' => '#059669'],
                'is_active' => true,
            ],
            [
                'slug' => 'grade-release',
                'name' => 'Grade Release',
                'description' => 'Semester grades and academic standing',
                'category' => 'academic',
                'mail_template' => 'notificationcenter::emails.templates.grade-release',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'student_name', 'student_id', 'semester', 'school_year', 'grades', 'gwa', 'subjects_passed', 'units_earned', 'academic_standing'],
                'styles' => ['theme' => 'academic', 'primary_color' => '#1d4ed8'],
                'is_active' => true,
            ],
            [
                'slug' => 'holiday',
                'name' => 'Holiday / Break',
                'description' => 'Holiday announcements and school breaks',
                'category' => 'academic',
                'mail_template' => 'notificationcenter::emails.templates.holiday',
                'default_channels' => ['mail', 'database'],
                'variables' => ['title', 'holiday_date', 'holiday_day', 'holiday_type', 'duration', 'resume_date', 'content', 'activities', 'reminders'],
                'styles' => ['theme' => 'festive', 'primary_color' => '#f59e0b'],
                'is_active' => true,
            ],
        ];

        foreach ($templates as $template) {
            NotificationTemplate::updateOrCreate(
                ['slug' => $template['slug']],
                $template
            );
        }
    }
}
