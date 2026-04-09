<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class ClassSuspensionNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'class-suspension';

    protected function getSubject(): string
    {
        return '⚠️ Class Suspension: '.$this->data['suspension_date'] ?? 'Notice';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.class-suspension';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-exclamation-triangle';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'warning';
    }
}
