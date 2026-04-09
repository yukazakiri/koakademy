<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class WarningNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'warning';

    protected function getSubject(): string
    {
        return '⚠️ '.$this->data['title'] ?? 'Urgent Alert';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.warning';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'danger';
    }
}
