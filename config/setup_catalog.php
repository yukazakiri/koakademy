<?php

declare(strict_types=1);

use App\Support\PhilippineSetupCatalogProvider;

return [
    // ISO alpha-2 => SetupCatalogProvider class. Unregistered countries use standard setup only.
    'providers' => [
        'PH' => PhilippineSetupCatalogProvider::class,
    ],
];
