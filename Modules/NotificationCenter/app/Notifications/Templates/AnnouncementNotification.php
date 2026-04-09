<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class AnnouncementNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'announcement';

    protected function getSubject(): string
    {
        return $this->data['title'] ?? 'System Announcement';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.announcement';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-megaphone';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'info';
    }
}
