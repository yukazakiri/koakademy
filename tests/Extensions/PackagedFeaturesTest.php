<?php

declare(strict_types=1);

use App\Filament\Plugins\DiscoveredModulesPlugin;
use App\Modules\ModuleManifestRepository;
use Composer\InstalledVersions;
use Filament\Panel;

it('runs each extracted feature from exactly one Composer-owned package', function (string $name, string $package): void {
    $installed = app(ModuleManifestRepository::class)->installedModule($name);

    expect($installed)->not->toBeNull();
    expect($installed->manifest->composerPackage)->toBe($package);
    expect(InstalledVersions::isInstalled($package))->toBeTrue();
    expect(realpath($installed->path))->toBe(realpath(base_path('vendor/'.$package)));
    expect(is_dir(base_path('Modules/'.$name)))->toBeFalse();
    expect(app('migrator')->paths())->toContain($installed->path.'/database/migrations');

    $provider = new ReflectionClass($installed->manifest->providers[0]);
    expect($installed->containsFile((string) $provider->getFileName()))->toBeTrue();
})->with([
    ['Announcement', 'koakademy/announcement'],
    ['Cashier', 'koakademy/cashier'],
    ['Inventory', 'koakademy/inventory'],
    ['LibrarySystem', 'koakademy/library-system'],
    ['NotificationCenter', 'koakademy/notification-center'],
    ['StudentMedicalRecords', 'koakademy/student-medical-records'],
]);

it('keeps packaged Filament resources and the cashier page registered', function (): void {
    $panel = Panel::make()->id('packaged-features');
    DiscoveredModulesPlugin::make()->register($panel);

    expect($panel->getResources())->toContain(
        Modules\Announcement\Filament\Resources\Announcements\AnnouncementResource::class,
        Modules\Inventory\Filament\Resources\InventoryProducts\InventoryProductResource::class,
        Modules\LibrarySystem\Filament\Resources\Books\BookResource::class,
        Modules\StudentMedicalRecords\Filament\Resources\MedicalRecords\MedicalRecordResource::class,
    );
    expect($panel->getPages())->toContain(Modules\Cashier\Filament\Pages\Cashier::class);
});
