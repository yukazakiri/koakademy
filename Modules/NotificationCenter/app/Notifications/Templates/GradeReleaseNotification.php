<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class GradeReleaseNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'grade-release';

    protected function getSubject(): string
    {
        return '📊 Grade Release - '.$this->data['semester'] ?? 'Semester';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.grade-release';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'success';
    }
}
