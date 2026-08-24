<?php

declare(strict_types=1);

use App\Support\HostingSecurity;

it('derives exact trusted host patterns from production configuration', function (): void {
    $patterns = HostingSecurity::trustedHostPatterns(
        appUrl: 'https://school.example',
        adminHost: 'school.example',
        portalHost: 'portal.school.example',
        additionalHosts: 'reports.school.example, https://invalid host.example',
    );

    $accepts = static fn (string $host): bool => collect($patterns)->contains(
        static fn (string $pattern): bool => preg_match('/'.$pattern.'/i', $host) === 1,
    );

    expect($accepts('school.example'))->toBeTrue()
        ->and($accepts('portal.school.example'))->toBeTrue()
        ->and($accepts('reports.school.example'))->toBeTrue()
        ->and($accepts('attacker-school.example'))->toBeFalse()
        ->and($accepts('school.example.attacker.test'))->toBeFalse();
});

it('parses explicit trusted proxies without accepting empty entries', function (): void {
    expect(HostingSecurity::trustedProxies('10.0.0.2, 192.0.2.0/24, '))->toBe([
        '10.0.0.2',
        '192.0.2.0/24',
    ])->and(HostingSecurity::trustedProxies('*'))->toBe('*');
});

it('detects whether an application URL requires HTTPS', function (): void {
    expect(HostingSecurity::usesHttps('http://127.0.0.1:8000'))->toBeFalse()
        ->and(HostingSecurity::usesHttps('https://school.example'))->toBeTrue();
});
