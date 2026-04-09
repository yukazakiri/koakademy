<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class AcademicScheduleNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'academic-schedule';

    protected function getSubject(): string
    {
        return '📅 '.$this->data['title'] ?? 'Academic Schedule Update';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.academic-schedule';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-calendar';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'info';
    }
}
