<?php

declare(strict_types=1);

return [
    'timeout' => (float) env('NEWSLETTER_HTTP_TIMEOUT', 10),
    'connect_timeout' => (float) env('NEWSLETTER_HTTP_CONNECT_TIMEOUT', 5),
    'providers' => [
        'sequenzy' => [
            'url' => env('NEWSLETTER_SEQUENZY_URL', 'https://api.sequenzy.com/api/v1'),
        ],
        'brevo' => [
            'url' => env('NEWSLETTER_BREVO_URL', 'https://api.brevo.com/v3'),
        ],
        'mailchimp' => [
            'url' => env('NEWSLETTER_MAILCHIMP_URL', 'https://{server}.api.mailchimp.com/3.0'),
        ],
    ],
];
