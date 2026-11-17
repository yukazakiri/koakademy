<?php

declare(strict_types=1);

return [
    // Permit HTTPS callbacks and native loopback callbacks. The registration
    // controller rejects non-loopback HTTP regardless of this domain setting.
    'redirect_domains' => ['*'],
    'custom_schemes' => ['cursor', 'vscode', 'claude'],
];
