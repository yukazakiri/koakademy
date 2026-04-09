<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class OfficialNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'official';

    protected function getSubject(): string
    {
        return $this->data['title'] ?? 'Official Notice';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.official';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'info';
    }
}
