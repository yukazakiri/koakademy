<?php

declare(strict_types=1);

use App\Support\RuntimeSecurity;
use Illuminate\Support\Facades\File;

it('selects queue connections by QUEUE_CONNECTION driver rather than host OS family', function (): void {
    $queueConfigSource = File::get(base_path('config/queue.php'));

    expect($queueConfigSource)->not->toContain('PHP_OS_FAMILY');

    $envKeys = [
        'QUEUE_CONNECTION',
        'ASSESSMENT_NOTIFICATION_CONNECTION',
        'ASSESSMENT_NOTIFICATION_QUEUE',
        'RECEIPT_EMAIL_CONNECTION',
        'RECEIPT_EMAIL_QUEUE',
    ];

    $originals = [];
    foreach ($envKeys as $key) {
        $originals[$key] = [
            'env' => $_ENV[$key] ?? null,
            'server' => $_SERVER[$key] ?? null,
            'getenv' => getenv($key),
        ];
    }

    $setEnv = static function (string $key, ?string $value): void {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv($key);

            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv("{$key}={$value}");
    };

    try {
        foreach ($envKeys as $key) {
            $setEnv($key, null);
        }

        $setEnv('QUEUE_CONNECTION', 'database');
        $databaseQueueConfig = require base_path('config/queue.php');

        expect($databaseQueueConfig['assessment_notification_connection'])->toBe('database')
            ->and($databaseQueueConfig['assessment_notification_queue'])->toBe('default')
            ->and($databaseQueueConfig['receipt_email_connection'])->toBe('database')
            ->and($databaseQueueConfig['receipt_email_queue'])->toBe('default');

        $setEnv('QUEUE_CONNECTION', 'redis');
        $redisQueueConfig = require base_path('config/queue.php');

        expect($redisQueueConfig['assessment_notification_connection'])->toBe('redis-pdf')
            ->and($redisQueueConfig['assessment_notification_queue'])->toBe('pdf-generation')
            ->and($redisQueueConfig['receipt_email_connection'])->toBe('redis-pdf')
            ->and($redisQueueConfig['receipt_email_queue'])->toBe('pdf-generation');
    } finally {
        foreach ($originals as $key => $saved) {
            if ($saved['env'] === null) {
                unset($_ENV[$key]);
            } else {
                $_ENV[$key] = $saved['env'];
            }

            if ($saved['server'] === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $saved['server'];
            }

            if ($saved['getenv'] === false) {
                putenv($key);
            } else {
                putenv("{$key}={$saved['getenv']}");
            }
        }
    }
});

it('provides a self-contained containerized local development stack and devcontainer', function (): void {
    $compose = File::get(base_path('compose.yaml'));

    expect($compose)
        ->toContain('context: ./docker/dev')
        ->not->toContain('./vendor/laravel/sail/runtimes')
        ->not->toContain('traefik:')
        ->not->toContain('station:')
        ->not->toContain(':z"');

    expect(File::exists(base_path('docker/dev/Dockerfile')))->toBeTrue()
        ->and(File::exists(base_path('docker/dev/start-container')))->toBeTrue()
        ->and(File::exists(base_path('docker/dev/php.ini')))->toBeTrue()
        ->and(File::exists(base_path('docker/dev/supervisord.conf')))->toBeTrue()
        ->and(File::exists(base_path('.devcontainer/devcontainer.json')))->toBeTrue();

    $devcontainer = json_decode(File::get(base_path('.devcontainer/devcontainer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($devcontainer)
        ->toHaveKey('service', 'laravel.test')
        ->toHaveKey('postCreateCommand', 'composer setup');
});

it('standardizes cross-platform composer setup and dev workflows and keeps sail in require-dev', function (): void {
    $composer = json_decode(File::get(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

    expect($composer['require'])->not->toHaveKey('laravel/sail')
        ->and($composer['require-dev'])->toHaveKey('laravel/sail');

    $setupScripts = implode("\n", $composer['scripts']['setup']);
    $devScripts = implode("\n", $composer['scripts']['dev']);

    expect($setupScripts)
        ->toContain('npm ci')
        ->not->toContain('npm install')
        ->toContain('storage:link')
        ->toContain('passport:keys')
        ->toContain('wayfinder:generate');

    expect($devScripts)
        ->toContain('php scripts/dev-logs.php')
        ->toContain('127.0.0.1');

    expect(File::exists(base_path('scripts/dev-logs.php')))->toBeTrue();
});

it('pins the contributor toolchain, guards worktree autoloading, and removes root scratch files', function (): void {
    $package = json_decode(File::get(base_path('package.json')), true, 512, JSON_THROW_ON_ERROR);
    $nodeVersion = mb_trim(File::get(base_path('.node-version')));
    $mise = File::get(base_path('mise.toml'));
    $envExample = File::get(base_path('.env.example'));
    $bootstrap = File::get(base_path('bootstrap/app.php'));

    expect($package['engines']['node'] ?? null)->toBe('>='.RuntimeSecurity::MINIMUM_NODE_VERSION)
        ->and(RuntimeSecurity::nodeVersionIsSupported($nodeVersion))->toBeTrue()
        ->and($mise)->toContain('php = "'.RuntimeSecurity::MINIMUM_PHP_VERSION.'"')
        ->and($mise)->toContain('node = "'.$nodeVersion.'"')
        ->and($envExample)->toMatch('/^APP_URL=http:\/\/127\.0\.0\.1:8000$/m')
        ->and($envExample)->toMatch('/^APP_TIMEZONE=UTC$/m')
        ->and($bootstrap)->toContain("if (is_link(__DIR__.'/../vendor'))");

    expect(File::exists(base_path('bun.lock')))->toBeFalse()
        ->and(File::exists(base_path('pandacode-run.sh')))->toBeFalse()
        ->and(File::exists(base_path('PROFILE_SETTINGS_REVAMP_PLAN.md')))->toBeFalse()
        ->and(File::exists(base_path('.devcontainerignore')))->toBeFalse();
});
