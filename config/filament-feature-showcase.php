<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Application Version
    |--------------------------------------------------------------------------
    |
    | The current version of your application. Bump this value with every
    | release. The feature showcase modal will automatically appear for
    | users who haven't seen the current version yet.
    |
    | This value is dynamically overridden at boot time from version.json
    | and GitHub releases by AppServiceProvider::syncFeatureShowcaseConfig().
    | The value below serves as a fallback only.
    |
    */

    'current' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | User Preference Key
    |--------------------------------------------------------------------------
    |
    | The key used to store the last seen version in the user's preferences
    | JSON column. Change this if it conflicts with your existing schema.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | User Column
    |--------------------------------------------------------------------------
    |
    | The JSON column on your User model used to store preferences.
    | This column must be cast to 'array' or 'json' on your model.
    |
    */

    'user_column' => 'preferences',

    'preference_key' => 'last_seen_version',

    /*
    |--------------------------------------------------------------------------
    | Dismiss Route
    |--------------------------------------------------------------------------
    |
    | The URI for the POST endpoint that marks a version as seen.
    | This route is automatically registered by the package.
    |
    */

    'dismiss_route' => '/admin/dismiss-version-showcase',

    /*
    |--------------------------------------------------------------------------
    | Changelog
    |--------------------------------------------------------------------------
    |
    | This changelog is dynamically populated from GitHub stable releases
    | by AppServiceProvider::syncFeatureShowcaseConfig() at boot time.
    | The entries below serve as a fallback when the GitHub API is
    | unreachable. Only stable releases are included (no pre-releases).
    |
    */

    'changelog' => [

        '0.1.0' => [
            'title' => 'Initial Release',
            'description' => 'The first public release of KoAcademy.',
            'features' => [
                [
                    'icon' => 'heroicon-o-megaphone',
                    'title' => 'Announcements Module',
                    'description' => 'Moved announcements to a dedicated module with wired data service.',
                ],
            ],
        ],

    ],

];
