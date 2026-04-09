<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class SuccessNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'success';

    protected function getSubject(): string
    {
        return $this->data['title'] ?? 'Congratulations!';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.success';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-trophy';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'success';
    }
}
