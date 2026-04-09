<?php

declare(strict_types=1);

namespace Modules\NotificationCenter\Notifications\Templates;

use Modules\NotificationCenter\Notifications\BaseTemplateNotification;

final class PaymentNoticeNotification extends BaseTemplateNotification
{
    protected string $templateSlug = 'payment-notice';

    protected function getSubject(): string
    {
        return '💳 Payment Notice: '.$this->data['title'] ?? 'Fee Payment Required';
    }

    protected function getTemplatePath(): string
    {
        return 'notificationcenter::emails.templates.payment-notice';
    }

    protected function getFilamentIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }

    protected function getFilamentTypeMethod(): string
    {
        return 'warning';
    }
}
