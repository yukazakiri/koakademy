<?php

declare(strict_types=1);

use App\Support\RuntimeSecurity;

it('rejects PHP releases that predate the October 2026 security fixes', function (): void {
    expect(RuntimeSecurity::phpVersionIsSupported('8.5.0'))->toBeFalse()
        ->and(RuntimeSecurity::phpVersionIsSupported('8.5.10'))->toBeFalse()
        ->and(RuntimeSecurity::phpVersionIsSupported('8.5.11'))->toBeFalse()
        ->and(RuntimeSecurity::phpVersionIsSupported('8.5.12'))->toBeTrue()
        ->and(RuntimeSecurity::phpVersionIsSupported('8.5.13'))->toBeTrue()
        ->and(RuntimeSecurity::phpVersionIsSupported('8.6.0'))->toBeTrue();
});

it('names the patched PHP release in the failure message', function (): void {
    $message = RuntimeSecurity::phpVersionMessage('8.5.11');

    expect($message)
        ->toContain('8.5.11')
        ->toContain(RuntimeSecurity::MINIMUM_PHP_VERSION)
        ->toContain('CVE-2026-109618')
        ->toContain('CVE-2026-109619');
});

it('rejects Node.js releases that predate the October 2026 security fixes', function (): void {
    expect(RuntimeSecurity::nodeVersionIsSupported('22.22.1'))->toBeFalse()
        ->and(RuntimeSecurity::nodeVersionIsSupported('22.23.0'))->toBeFalse()
        ->and(RuntimeSecurity::nodeVersionIsSupported('22.23.3'))->toBeFalse()
        ->and(RuntimeSecurity::nodeVersionIsSupported('22.23.4'))->toBeTrue()
        ->and(RuntimeSecurity::nodeVersionIsSupported('22.24.0'))->toBeTrue()
        ->and(RuntimeSecurity::nodeVersionIsSupported('24.22.1'))->toBeTrue()
        ->and(RuntimeSecurity::nodeVersionIsSupported('26.12.1'))->toBeTrue();
});

it('treats an unreadable Node.js version as unsupported', function (): void {
    expect(RuntimeSecurity::nodeVersionIsSupported(null))->toBeFalse()
        ->and(RuntimeSecurity::nodeVersionIsSupported(''))->toBeFalse()
        ->and(RuntimeSecurity::nodeVersionIsSupported('22'))->toBeFalse();
});

it('names the patched Node.js release in the failure message', function (): void {
    $message = RuntimeSecurity::nodeVersionMessage('22.22.1');

    expect($message)
        ->toContain('22.22.1')
        ->toContain(RuntimeSecurity::MINIMUM_NODE_VERSION)
        ->toContain('CVE-2026-111497');
});
