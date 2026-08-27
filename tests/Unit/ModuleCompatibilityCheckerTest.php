<?php

declare(strict_types=1);

use App\Modules\CompatibilityChecker;
use App\Modules\Contracts\ModuleManifest;
use App\Modules\VersionConstraint;

it('accepts a module compatible with the running application', function (): void {
    $manifest = ModuleManifest::fromArray([
        'name' => 'Compatible',
        'alias' => 'compatible',
        'version' => '1.0.0',
        'license' => 'AGPL-3.0-or-later',
        'requires' => [
            'core' => '>=1.20.0',
            'php' => '>=8.5',
        ],
        'compatibility' => [
            'laravel' => '^13.0',
            'filament' => '^5.0',
        ],
        'providers' => ['Modules\\Compatible\\Providers\\CompatibleServiceProvider'],
    ]);

    expect(app(CompatibilityChecker::class)->check($manifest)->isCompatible())->toBeTrue();
});

it('accepts stable module requirements on edge core builds', function (): void {
    config()->set('app.version', '1.22.0-edge+sha.bc528c1956f5');

    $manifest = ModuleManifest::fromArray([
        'name' => 'EdgeCompatible',
        'alias' => 'edge-compatible',
        'version' => '1.0.0',
        'license' => 'AGPL-3.0-or-later',
        'requires' => [
            'core' => '>=1.22.0',
        ],
        'compatibility' => [],
        'providers' => ['Modules\\EdgeCompatible\\Providers\\EdgeCompatibleServiceProvider'],
    ]);

    expect(app(CompatibilityChecker::class)->check($manifest)->isCompatible())->toBeTrue();
});

it('reports incompatible core and module requirements', function (): void {
    $manifest = ModuleManifest::fromArray([
        'name' => 'Incompatible',
        'alias' => 'incompatible',
        'version' => '1.0.0',
        'license' => 'AGPL-3.0-or-later',
        'requires' => [
            'core' => '>=99.0.0',
            'modules' => [
                'MissingModule' => '^1.0',
            ],
        ],
        'compatibility' => [],
        'providers' => ['Modules\\Incompatible\\Providers\\IncompatibleServiceProvider'],
    ]);

    $result = app(CompatibilityChecker::class)->check($manifest);

    expect($result->isCompatible())->toBeFalse()
        ->and($result->errors)->toContain('Requires module [MissingModule] to be installed.')
        ->and($result->errors[0])->toContain('core');
});

it('supports the constraint forms used by module manifests', function (): void {
    $constraints = new VersionConstraint;

    expect($constraints->matches('1.20.1', '>=1.20.0'))->toBeTrue()
        ->and($constraints->matches('5.7.6', '^5.0'))->toBeTrue()
        ->and($constraints->matches('5.7.6', '^4.0 || ^5.0'))->toBeTrue()
        ->and($constraints->matches('1.22.0-edge+sha.bc528c1956f5', '>=1.22.0'))->toBeTrue()
        ->and($constraints->matches('1.21.9-edge+sha.previous', '>=1.22.0'))->toBeFalse()
        ->and($constraints->matches('2.0.0', '~1.4'))->toBeFalse();
});
