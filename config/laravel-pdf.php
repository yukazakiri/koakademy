<?php

declare(strict_types=1);

use Spatie\LaravelPdf\Jobs\GeneratePdfJob;

return [
    /*
     * The default driver to use for PDF generation.
     * Supported: "browsershot", "cloudflare", "dompdf", "gotenberg"
     */
    'driver' => env('LARAVEL_PDF_DRIVER', match (env('APP_ENV', 'production')) {
        'local', 'development', 'testing' => env('LARAVEL_PDF_LOCAL_DRIVER', 'browsershot'),
        'staging' => env('LARAVEL_PDF_STAGING_DRIVER', 'dompdf'),
        default => env('LARAVEL_PDF_PRODUCTION_DRIVER', 'dompdf'),
    }),

    /*
     * Driver strategy profiles by environment.
     *
     * Goal: keep production on a non-Chrome primary path.
     */
    'strategy' => [
        'profiles' => [
            'production' => [
                'primary' => env('LARAVEL_PDF_PRODUCTION_DRIVER', 'dompdf'),
                'fallback' => array_values(array_filter(array_map(
                    static fn (string $driver): string => mb_trim($driver),
                    explode(',', env('LARAVEL_PDF_PRODUCTION_FALLBACK', 'cloudflare,browsershot')),
                ))),
            ],
            'staging' => [
                'primary' => env('LARAVEL_PDF_STAGING_DRIVER', 'dompdf'),
                'fallback' => array_values(array_filter(array_map(
                    static fn (string $driver): string => mb_trim($driver),
                    explode(',', env('LARAVEL_PDF_STAGING_FALLBACK', 'cloudflare,browsershot')),
                ))),
            ],
            'local' => [
                'primary' => env('LARAVEL_PDF_LOCAL_DRIVER', 'browsershot'),
                'fallback' => array_values(array_filter(array_map(
                    static fn (string $driver): string => mb_trim($driver),
                    explode(',', env('LARAVEL_PDF_LOCAL_FALLBACK', 'dompdf')),
                ))),
            ],
        ],
        'rollback_driver' => env('LARAVEL_PDF_ROLLBACK_DRIVER', 'browsershot'),
    ],

    /*
     * The job class used for queued PDF generation.
     * You can replace this with your own class that extends GeneratePdfJob
     * to customize things like $tries, $timeout, $backoff, or default queue.
     */
    'job' => GeneratePdfJob::class,

    /*
     * Browsershot driver configuration.
     *
     * Requires the spatie/browsershot package:
     * composer require spatie/browsershot
     */
    'browsershot' => [
        /*
         * Configure the paths to Node.js, npm, Chrome, and other binaries.
         * Leave null to use system defaults or Browsershot's auto-detection.
         */
        'node_binary' => env('LARAVEL_PDF_NODE_BINARY'),
        'npm_binary' => env('LARAVEL_PDF_NPM_BINARY'),
        'include_path' => env('LARAVEL_PDF_INCLUDE_PATH'),
        'chrome_path' => env('LARAVEL_PDF_CHROME_PATH'),
        'node_modules_path' => env('LARAVEL_PDF_NODE_MODULES_PATH'),
        'bin_path' => env('LARAVEL_PDF_BIN_PATH'),
        'temp_path' => env('LARAVEL_PDF_TEMP_PATH'),

        /*
         * Other Browsershot configuration options.
         */
        'write_options_to_file' => env('LARAVEL_PDF_WRITE_OPTIONS_TO_FILE', true),
        'no_sandbox' => env('LARAVEL_PDF_NO_SANDBOX', false),
    ],

    /*
     * Cloudflare Browser Rendering driver configuration.
     *
     * Requires a Cloudflare account with the Browser Rendering API enabled.
     * https://developers.cloudflare.com/browser-rendering/
     */
    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    ],

    /*
     * Gotenberg driver configuration.
     *
     * Requires a running Gotenberg instance (Docker recommended).
     * https://gotenberg.dev
     */
    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://localhost:3000'),
        'username' => env('GOTENBERG_USERNAME'),
        'password' => env('GOTENBERG_PASSWORD'),
    ],

    /*
     * DOMPDF driver configuration.
     *
     * Pure PHP PDF generation — no external binaries required.
     * Requires the dompdf/dompdf package:
     * composer require dompdf/dompdf
     */
    'dompdf' => [
        /*
         * Allow DOMPDF to fetch external resources (images, CSS).
         * Set to true if your HTML references remote URLs.
         */
        'is_remote_enabled' => env('LARAVEL_PDF_DOMPDF_REMOTE_ENABLED', false),

        /*
         * The base path for local file access.
         * Defaults to DOMPDF's built-in chroot setting when null.
         */
        'chroot' => env('LARAVEL_PDF_DOMPDF_CHROOT'),
    ],

    /*
    * WeasyPrint driver configuration.
    *
    * Requires the Weasyprint binary and pontedilana/php-weasyprint package:
    * composer require pontedilana/php-weasyprint
    *
    * @see https://doc.courtbouillon.org/weasyprint/stable/first_steps.html
    */
    'weasyprint' => [
        /*
         * Configure the paths to the Weasyprint binary.
         */
        'binary' => env('LARAVEL_PDF_WEASYPRINT_BINARY', 'weasyprint'),

        /*
         * The timeout (default = 10 seconds)
         */
        'timeout' => 10,
    ],
];
