<?php

declare(strict_types=1);

$integerList = static function (string $name, string $default): array {
    return array_values(array_filter(
        array_map(static fn (string $value): int => (int) mb_trim($value), explode(',', (string) env($name, $default))),
        static fn (int $value): bool => $value > 0,
    ));
};

return [
    'connection' => env('ASSESSMENT_EXPORT_CONNECTION', 'assessment-pdf'),
    'render_queue' => env('ASSESSMENT_EXPORT_QUEUE', 'assessment-pdf'),
    'event_queue' => env('ASSESSMENT_EXPORT_EVENT_QUEUE', 'assessment-events'),
    'disk' => env('ASSESSMENT_EXPORT_DISK', env('FILESYSTEM_DISK', 'local')),
    'max_active_per_user' => (int) env('ASSESSMENT_EXPORT_MAX_ACTIVE_PER_USER', 1),
    'broadcast_throttle_ms' => (int) env('ASSESSMENT_EXPORT_BROADCAST_THROTTLE_MS', 0),
    'student_limit_options' => $integerList('ASSESSMENT_EXPORT_STUDENT_LIMITS', '10,25,50,100,250,500'),
    'render' => [
        'timeout' => (int) env('ASSESSMENT_EXPORT_RENDER_TIMEOUT', 600),
        'tries' => (int) env('ASSESSMENT_EXPORT_RENDER_TRIES', 3),
        'backoff' => $integerList('ASSESSMENT_EXPORT_RENDER_BACKOFF', '30,120,300'),
    ],
    'prepare' => [
        'timeout' => (int) env('ASSESSMENT_EXPORT_PREPARE_TIMEOUT', 300),
        'tries' => (int) env('ASSESSMENT_EXPORT_PREPARE_TRIES', 2),
        'backoff' => $integerList('ASSESSMENT_EXPORT_PREPARE_BACKOFF', '15,60'),
    ],
    'merge' => [
        'timeout' => (int) env('ASSESSMENT_EXPORT_MERGE_TIMEOUT', 1800),
        'tries' => (int) env('ASSESSMENT_EXPORT_MERGE_TRIES', 2),
        'backoff' => $integerList('ASSESSMENT_EXPORT_MERGE_BACKOFF', '60,300'),
        'fan_in' => (int) env('ASSESSMENT_EXPORT_MERGE_FAN_IN', 20),
    ],
    'visibility' => [
        'terminal_minutes' => (int) env('ASSESSMENT_EXPORT_TERMINAL_VISIBLE_MINUTES', 15),
    ],
    'retention' => [
        'intermediate_hours' => (int) env('ASSESSMENT_EXPORT_INTERMEDIATE_HOURS', 24),
        'final_days' => (int) env('ASSESSMENT_EXPORT_FINAL_DAYS', 30),
        'metadata_days' => (int) env('ASSESSMENT_EXPORT_METADATA_DAYS', 90),
    ],
];
