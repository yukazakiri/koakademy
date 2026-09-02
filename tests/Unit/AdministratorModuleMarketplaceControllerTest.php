<?php

declare(strict_types=1);

use App\Http\Controllers\AdministratorModuleMarketplaceController;
use App\Modules\CompatibilityChecker;
use App\Modules\Contracts\ModuleManifest;
use App\Modules\Contracts\ModuleRelease;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use App\Modules\RegistryClient;
use App\Services\ModuleAdminNavigationService;
use Nwidart\Modules\Contracts\RepositoryInterface;

it('targets the exact catalog release in the Composer update command', function (): void {
    $manifests = new ModuleManifestRepository(new ModuleStateRepository);

    $controller = new AdministratorModuleMarketplaceController(
        $manifests,
        new RegistryClient,
        new CompatibilityChecker($manifests),
        new ModuleStateRepository,
        Mockery::mock(RepositoryInterface::class),
        app(ModuleAdminNavigationService::class),
    );
    $manifest = ModuleManifest::fromArray([
        'name' => 'Forms',
        'alias' => 'forms',
        'version' => '1.0.0',
        'description' => 'Online forms.',
        'author' => 'KoAkademy contributors',
        'license' => 'AGPL-3.0-or-later',
        'requires' => [],
        'compatibility' => [],
        'providers' => ['Modules\\Forms\\Providers\\FormsServiceProvider'],
        'composer_package' => 'koakademy/forms',
    ]);
    $release = ModuleRelease::fromArray([
        'version' => '1.1.0',
        'asset_url' => 'https://example.test/forms-1.1.0.zip',
        'sha256' => str_repeat('a', 64),
    ]);
    $method = new ReflectionMethod(AdministratorModuleMarketplaceController::class, 'updateCommand');
    $method->setAccessible(true);

    expect($method->invoke($controller, $manifest, $release, 'composer'))
        ->toBe('composer require koakademy/forms:1.1.0 --update-with-dependencies');
});
