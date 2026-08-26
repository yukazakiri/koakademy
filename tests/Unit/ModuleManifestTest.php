<?php

declare(strict_types=1);

use App\Modules\Contracts\ModuleManifest;
use App\Modules\ModuleManifestRepository;

it('parses every shipped module manifest and status entry', function (): void {
    $repository = app(ModuleManifestRepository::class);

    $manifests = $repository->all();

    expect($manifests)
        ->toHaveCount(6)
        ->each->toBeInstanceOf(ModuleManifest::class);

    foreach ($manifests as $manifest) {
        expect($manifest->composerPackage)->toStartWith('koakademy/');
    }

    expect($repository->enabled())->toHaveCount(6);
});

it('round trips the public module contract', function (): void {
    $manifest = ModuleManifest::fromArray([
        'name' => 'LibrarySystem',
        'alias' => 'library-system',
        'composer_package' => 'koakademy/library-system',
        'version' => '1.4.2',
        'description' => 'Library operations.',
        'author' => 'KoAkademy contributors',
        'license' => 'AGPL-3.0-or-later',
        'requires' => [
            'core' => '>=1.20.0',
            'php' => '>=8.5',
            'modules' => [
                'Inventory' => '^1.0',
            ],
        ],
        'compatibility' => [
            'laravel' => '^13.0',
            'filament' => '^5.0',
        ],
        'providers' => [
            'Modules\\LibrarySystem\\Providers\\LibrarySystemServiceProvider',
        ],
    ]);

    expect($manifest->toArray())
        ->toHaveKey('version', '1.4.2')
        ->toHaveKey('composer_package', 'koakademy/library-system')
        ->and($manifest->requires['modules']['Inventory'])->toBe('^1.0');
});

it('rejects a manifest without a provider or semantic version', function (): void {
    expect(fn (): ModuleManifest => ModuleManifest::fromArray([
        'name' => 'Broken',
        'alias' => 'broken',
        'version' => '1',
        'license' => 'AGPL-3.0-or-later',
        'providers' => [],
    ]))->toThrow(InvalidArgumentException::class);
});
