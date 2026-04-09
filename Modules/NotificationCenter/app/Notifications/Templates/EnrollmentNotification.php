<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class EnrollmentNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'enrollment';

    protected function getSubject(): string
    {
        return '✅ Enrollment Confirmation - '.$this->data['student_name'] ?? 'Student';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.enrollment';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-academic-cap';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'success';
    }
}
