<?php

declare(strict_types=1);

function productionEnvironment(): string
{
    return file_get_contents(base_path('.env.production.example')) ?: '';
}

it('ships safe production environment defaults', function (): void {
    $environment = productionEnvironment();

    expect($environment)
        ->toContain('APP_ENV=production')
        ->toContain('APP_DEBUG=false')
        ->toContain('SESSION_ENCRYPT=true')
        ->toContain('SESSION_SECURE_COOKIE=true')
        ->toContain('SESSION_HTTP_ONLY=true')
        ->toContain('FILESYSTEM_DISK=s3')
        ->toContain('RUN_MIGRATIONS=false')
        ->not->toContain('PUSHER_APP_ID=1801608')
        ->not->toContain('SANITY_PROJECT_ID=4pzjiopf');
});

it('uses Gotenberg through Laravel PDF without a DOMPDF fallback', function (): void {
    $environment = productionEnvironment();
    $pdfConfig = file_get_contents(config_path('laravel-pdf.php')) ?: '';

    expect($environment)
        ->toContain('LARAVEL_PDF_DRIVER=gotenberg')
        ->toContain('GOTENBERG_URL=http://gotenberg:3000')
        ->and(mb_strtolower($pdfConfig))->not->toContain('dompdf');
});

it('publishes only the application loopback port in production compose', function (): void {
    $compose = file_get_contents(base_path('compose.production.yaml')) ?: '';

    expect($compose)
        ->toContain('127.0.0.1:${APP_PORT:-8000}:8000')
        ->toContain('postgres:')
        ->toContain('redis:')
        ->toContain('gotenberg:')
        ->not->toMatch('/postgres:\n(?:.|\n)*?\n\s+ports:/')
        ->not->toMatch('/redis:\n(?:.|\n)*?\n\s+ports:/')
        ->not->toMatch('/gotenberg:\n(?:.|\n)*?\n\s+ports:/');
});

it('keeps KoAkademy package and image metadata AGPL consistent', function (): void {
    $rootPackage = json_decode(file_get_contents(base_path('composer.json')) ?: '{}', true, flags: JSON_THROW_ON_ERROR);
    $moduleManifests = glob(base_path('Modules/*/composer.json')) ?: [];
    $dockerfile = file_get_contents(base_path('docker/Dockerfile')) ?: '';

    expect($rootPackage['name'] ?? null)->toBe('yukazakiri/koakademy')
        ->and($rootPackage['license'] ?? null)->toBe('AGPL-3.0-or-later')
        ->and($dockerfile)->toContain('org.opencontainers.image.source=https://github.com/yukazakiri/koakademy')
        ->and($dockerfile)->toContain('org.opencontainers.image.licenses=AGPL-3.0-or-later');

    foreach ($moduleManifests as $manifest) {
        $module = json_decode(file_get_contents($manifest) ?: '{}', true, flags: JSON_THROW_ON_ERROR);

        expect($module['name'] ?? null)->toStartWith('koakademy/')
            ->and($module['license'] ?? null)->toBe('AGPL-3.0-or-later');
    }
});

it('builds production assets from the canonical npm lockfile on Node 22', function (): void {
    $dockerfile = file_get_contents(base_path('docker/Dockerfile')) ?: '';

    expect($dockerfile)
        ->toContain('FROM node:22-bookworm-slim@sha256:')
        ->toContain('COPY package.json package-lock.json ./')
        ->toContain('RUN npm ci')
        ->toContain('WAYFINDER_GENERATE=false npm run build')
        ->not->toContain('oven/bun')
        ->not->toContain('bun install');
});

it('generates portable same-origin Wayfinder routes for production assets', function (): void {
    $dockerfile = file_get_contents(base_path('docker/Dockerfile')) ?: '';

    expect($dockerfile)
        ->toContain('PORTAL_HOST= ADMIN_HOST= php artisan wayfinder:generate --no-interaction');
});

it('installs a checksummed Supercronic binary for AMD64 and ARM64', function (): void {
    $dockerfile = file_get_contents(base_path('docker/Dockerfile')) ?: '';

    expect($dockerfile)
        ->toContain('ARG TARGETARCH')
        ->toContain('amd64) supercronic_sha256=')
        ->toContain('arm64) supercronic_sha256=')
        ->toContain('supercronic-linux-${TARGETARCH}')
        ->toContain('sha256sum --check')
        ->not->toContain('supercronic-linux-amd64" \\');
});

it('documents setup wizard onboarding instead of a CLI-created first admin', function (): void {
    $gettingStarted = file_get_contents(base_path('GETTING_STARTED.md')) ?: '';

    expect($gettingStarted)
        ->toContain('https://school.example/setup')
        ->toContain('first super administrator')
        ->not->toContain('make:filament-user');
});
