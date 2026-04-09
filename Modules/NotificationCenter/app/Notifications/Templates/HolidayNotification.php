<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class HolidayNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'holiday';

    protected function getSubject(): string
    {
        return '🌴 '.$this->data['title'] ?? 'Holiday Notice';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.holiday';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-sun';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'info';
    }
}
