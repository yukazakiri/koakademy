<?php

declare(strict_types=1);

use App\Filament\Resources\Mails\SuppressionResource;
use Backstage\Mails\Laravel\Models\Mail;
use Backstage\Mails\Laravel\Models\MailAttachment;
use Backstage\Mails\Laravel\Models\MailEvent;
use Backstage\Mails\Resources\EventResource;
use Backstage\Mails\Resources\MailResource;

return [
    'models' => [
        'mail' => Mail::class,
        'event' => MailEvent::class,
        'attachment' => MailAttachment::class,
    ],

    'database' => [
        'tables' => [
            'mails' => 'mails',
            'attachments' => 'mail_attachments',
            'events' => 'mail_events',
            'polymorph' => 'mailables',
        ],

        'pruning' => [
            'enabled' => true,
            'after' => 30,
        ],
    ],

    'headers' => [
        'uuid' => 'X-Mails-UUID',
        'associate' => 'X-Mails-Associated-Models',
    ],

    'webhooks' => [
        'routes' => [
            'prefix' => 'webhooks/mails',
        ],

        'queue' => env('MAILS_QUEUE_WEBHOOKS', false),
    ],

    'logging' => [
        'enabled' => env('MAILS_LOGGING_ENABLED', true),

        'attributes' => [
            'subject',
            'from',
            'to',
            'reply_to',
            'cc',
            'bcc',
            'html',
            'text',
        ],

        'encrypted' => env('MAILS_ENCRYPTED', true),

        'tracking' => [
            'bounces' => true,
            'clicks' => true,
            'complaints' => true,
            'deliveries' => true,
            'opens' => true,
            'unsubscribes' => true,
        ],

        'attachments' => [
            'enabled' => env('MAILS_LOGGING_ATTACHMENTS_ENABLED', true),
            'disk' => env('FILESYSTEM_DISK', 'local'),
            'root' => 'mails/attachments',
        ],
    ],

    'notifications' => [
        'mail' => [
            'to' => ['test@example.com'],
        ],

        'discord' => [
        ],

        'slack' => [
        ],

        'telegram' => [
        ],
    ],

    'events' => [
        'soft_bounced' => [
            'notify' => ['mail'],
        ],

        'hard_bounced' => [
            'notify' => ['mail'],
        ],

        'bouncerate' => [
            'notify' => [],
            'retain' => 30,
            'treshold' => 1,
        ],

        'deliveryrate' => [
            'treshold' => 99,
        ],

        'complained' => [
        ],

        'unsent' => [
        ],
    ],

    'resources' => [
        'mail' => MailResource::class,
        'event' => EventResource::class,
        'suppression' => SuppressionResource::class,
    ],

    'navigation' => [
        'group' => 'Communications',
        'sort' => 20,
    ],
];
