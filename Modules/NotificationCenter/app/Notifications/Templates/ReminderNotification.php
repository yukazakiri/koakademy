<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class ReminderNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'reminder';

    protected function getSubject(): string
    {
        return '🔔 '.$this->data['title'] ?? 'Friendly Reminder';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.reminder';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-clock';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'warning';
    }
}
