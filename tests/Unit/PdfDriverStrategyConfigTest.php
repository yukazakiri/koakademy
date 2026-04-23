<?php

declare(strict_types=1);

it('sets cloudflare as the primary production PDF driver profile', function (): void {
    $config = config('laravel-pdf');
    $strategy = $config['strategy'] ?? [];
    $profiles = $strategy['profiles'] ?? [];
    $production = $profiles['production'] ?? [];

    expect($production['primary'] ?? null)->toBe('cloudflare');
});

it('stores a production fallback order and rollback driver in PDF strategy config', function (): void {
    $config = config('laravel-pdf');
    $strategy = $config['strategy'] ?? [];
    $profiles = $strategy['profiles'] ?? [];
    $production = $profiles['production'] ?? [];
    $fallback = $production['fallback'] ?? [];

    expect($config)->toBeArray()
        ->and($strategy)->toBeArray()
        ->and($production['primary'] ?? null)->toBeString()
        ->and($fallback)->toBeArray()
        ->and($fallback)->toContain('dompdf')
        ->and($strategy['rollback_driver'] ?? null)->toBeString();
});
