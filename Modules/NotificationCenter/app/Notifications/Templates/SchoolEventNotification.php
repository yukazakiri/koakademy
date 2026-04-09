<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class SchoolEventNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'school-event';

    protected function getSubject(): string
    {
        return '🎉 '.$this->data['title'] ?? 'School Event';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.school-event';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-sparkles';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'info';
    }
}
