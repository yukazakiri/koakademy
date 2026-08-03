<?php

declare(strict_types=1);

namespace App\Enums;

enum NewsletterProvider: string
{
    case Sequenzy = 'sequenzy';
    case Brevo = 'brevo';
    case Mailchimp = 'mailchimp';
}
