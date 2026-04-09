<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Services;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class NotificationTemplateService
{
    private const TEMPLATE_CLASSES = [
        'announcement' => \Modules\NotificationCenter\Notifications\Templates\AnnouncementNotification::class,
        'success' => \Modules\NotificationCenter\Notifications\Templates\SuccessNotification::class,
        'official' => \Modules\NotificationCenter\Notifications\Templates\OfficialNotification::class,
        'warning' => \Modules\NotificationCenter\Notifications\Templates\WarningNotification::class,
        'reminder' => \Modules\NotificationCenter\Notifications\Templates\ReminderNotification::class,
        'class-suspension' => \Modules\NotificationCenter\Notifications\Templates\ClassSuspensionNotification::class,
        'school-event' => \Modules\NotificationCenter\Notifications\Templates\SchoolEventNotification::class,
        'academic-schedule' => \Modules\NotificationCenter\Notifications\Templates\AcademicScheduleNotification::class,
        'payment-notice' => \Modules\NotificationCenter\Notifications\Templates\PaymentNoticeNotification::class,
        'enrollment' => \Modules\NotificationCenter\Notifications\Templates\EnrollmentNotification::class,
        'grade-release' => \Modules\NotificationCenter\Notifications\Templates\GradeReleaseNotification::class,
        'holiday' => \Modules\NotificationCenter\Notifications\Templates\HolidayNotification::class,
    ];

    public function getNotificationClass(string $templateSlug): ?BaseTemplateNotification
    {
        $class = self::TEMPLATE_CLASSES[$templateSlug] ?? null;

        if ($class && class_exists($class)) {
            return app($class);
        }

        return null;
    }

    public function createNotification(string $templateSlug, array $data, array $channels = ['mail', 'database']): ?BaseTemplateNotification
    {
        $class = self::TEMPLATE_CLASSES[$templateSlug] ?? null;

        if ($class && class_exists($class)) {
            return new $class($data, $channels);
        }

        return null;
    }

    public function getAvailableTemplates(): array
    {
        return array_keys(self::TEMPLATE_CLASSES);
    }

    public function getTemplateInfo(string $templateSlug): array
    {
        $templates = [
            'announcement' => [
                'name' => 'Announcement',
                'description' => 'Bold, modern dark theme for system-wide announcements',
                'category' => 'informational',
                'icon' => 'heroicon-o-megaphone',
                'variables' => ['title', 'subtitle', 'content', 'action_url', 'action_text', 'metadata'],
            ],
            'success' => [
                'name' => 'Success / Achievement',
                'description' => 'Celebratory design for achievements and success messages',
                'category' => 'celebration',
                'icon' => 'heroicon-o-trophy',
                'variables' => ['title', 'content', 'badge', 'achievement', 'stats', 'action_url'],
            ],
            'official' => [
                'name' => 'Official Document',
                'description' => 'Formal letterhead style for official correspondence',
                'category' => 'formal',
                'icon' => 'heroicon-o-document-text',
                'variables' => ['title', 'content', 'recipient_name', 'reference_number', 'details'],
            ],
            'warning' => [
                'name' => 'Urgent Alert',
                'description' => 'High-contrast design for critical warnings',
                'category' => 'alert',
                'icon' => 'heroicon-o-exclamation-triangle',
                'variables' => ['title', 'content', 'priority', 'alert_code', 'action_items', 'deadline'],
            ],
            'reminder' => [
                'name' => 'Friendly Reminder',
                'description' => 'Warm pastel theme for gentle reminders',
                'category' => 'reminder',
                'icon' => 'heroicon-o-clock',
                'variables' => ['title', 'content', 'recipient_name', 'due_date', 'reminder_items'],
            ],
            'class-suspension' => [
                'name' => 'Class Suspension',
                'description' => 'Weather/emergency class cancellation notices',
                'category' => 'academic',
                'icon' => 'heroicon-o-exclamation-triangle',
                'variables' => ['title', 'suspension_date', 'affected_levels', 'content', 'reason_details', 'resumption_info', 'instructions', 'issued_by'],
            ],
            'school-event' => [
                'name' => 'School Event',
                'description' => 'Intramurals, Foundation Day, and school activities',
                'category' => 'events',
                'icon' => 'heroicon-o-sparkles',
                'variables' => ['title', 'tagline', 'event_type', 'event_date', 'event_time', 'venue', 'content', 'activities', 'schedule', 'participants', 'requirements', 'organizer'],
            ],
            'academic-schedule' => [
                'name' => 'Academic Schedule',
                'description' => 'Exam schedules, registration periods, deadlines',
                'category' => 'academic',
                'icon' => 'heroicon-o-calendar',
                'variables' => ['title', 'school_year', 'period_name', 'content', 'important_dates', 'deadlines', 'notes', 'department'],
            ],
            'payment-notice' => [
                'name' => 'Payment Notice',
                'description' => 'Tuition, fees, and payment reminders',
                'category' => 'finance',
                'icon' => 'heroicon-o-currency-dollar',
                'variables' => ['title', 'amount', 'currency', 'student_info', 'due_date', 'days_remaining', 'balance_breakdown', 'payment_methods', 'penalty_info'],
            ],
            'enrollment' => [
                'name' => 'Enrollment',
                'description' => 'Enrollment confirmations and status updates',
                'category' => 'academic',
                'icon' => 'heroicon-o-academic-cap',
                'variables' => ['title', 'student_name', 'student_id', 'course', 'year_level', 'section', 'school_year', 'semester', 'subjects', 'next_steps', 'assessment_url'],
            ],
            'grade-release' => [
                'name' => 'Grade Release',
                'description' => 'Semester grades and academic standing',
                'category' => 'academic',
                'icon' => 'heroicon-o-chart-bar',
                'variables' => ['title', 'student_name', 'student_id', 'semester', 'school_year', 'grades', 'gwa', 'subjects_passed', 'units_earned', 'academic_standing'],
            ],
            'holiday' => [
                'name' => 'Holiday / Break',
                'description' => 'Holiday announcements and school breaks',
                'category' => 'academic',
                'icon' => 'heroicon-o-sun',
                'variables' => ['title', 'holiday_date', 'holiday_day', 'holiday_type', 'duration', 'resume_date', 'content', 'activities', 'reminders'],
            ],
        ];

        return $templates[$templateSlug] ?? [];
    }

    public function getAllTemplatesInfo(): array
    {
        $result = [];

        foreach (array_keys(self::TEMPLATE_CLASSES) as $slug) {
            $result[$slug] = $this->getTemplateInfo($slug);
        }

        return $result;
    }

    public function templateExists(string $templateSlug): bool
    {
        return isset(self::TEMPLATE_CLASSES[$templateSlug]);
    }

    public function getTemplatesByCategory(string $category): array
    {
        $result = [];

        foreach (self::TEMPLATE_CLASSES as $slug => $class) {
            $info = $this->getTemplateInfo($slug);
            if (($info['category'] ?? '') === $category) {
                $result[$slug] = $info;
            }
        }

        return $result;
    }
}
