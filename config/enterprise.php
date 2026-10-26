<?php

declare(strict_types=1);

return [
    // License signing keys belong to the vendor. School installations only
    // receive public verification keys; these are separate from registry keys.
    'trusted_keys' => [
        'default' => env('ENTERPRISE_LICENSE_PUBLIC_KEY'),
    ],
];
