<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Modules\CompatibilityChecker;
use App\Modules\ModuleAccessService;
use App\Modules\ModuleManifestRepository;
use App\Modules\ModuleStateRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->moduleFixturePath = storage_path('framework/testing/activation-'.bin2hex(random_bytes(6)));
    File::ensureDirectoryExists($this->moduleFixturePath.'/Announcement');
    File::ensureDirectoryExists($this->moduleFixturePath.'/LibrarySystem');
    File::copy(base_path('Modules/LibrarySystem/module.json'), $this->moduleFixturePath.'/LibrarySystem/module.json');
    config([
        'modules-marketplace.enabled' => true,
        'modules-marketplace.registry_url' => null,
        'modules.statuses-file' => $this->moduleFixturePath.'/statuses.json',
    ]);
    File::put($this->moduleFixturePath.'/statuses.json', json_encode(['Announcement' => false], JSON_THROW_ON_ERROR));
    $this->app->forgetInstance(ModuleStateRepository::class);
});

afterEach(function (): void {
    File::deleteDirectory($this->moduleFixturePath);
});

it('rejects direct activation of incompatible or incomplete extensions', function (array $requires, array $compatibility): void {
    $data = json_decode(File::get(base_path('Modules/Announcement/module.json')), true, 512, JSON_THROW_ON_ERROR);
    $data['requires'] = $requires;
    $data['compatibility'] = $compatibility;
    File::put($this->moduleFixturePath.'/Announcement/module.json', json_encode($data, JSON_THROW_ON_ERROR));
    $this->app->instance(ModuleManifestRepository::class, new ModuleManifestRepository(
        app(ModuleStateRepository::class), $this->moduleFixturePath,
    ));
    $this->app->forgetInstance(CompatibilityChecker::class);
    $this->app->forgetInstance(ModuleAccessService::class);
    $user = User::factory()->create(['role' => UserRole::Admin]);
    $user->assignRole(UserRole::Admin);

    $this->actingAs($user)->post(portalUrlForAdministrators('/administrators/module-marketplace/Announcement/enable'))
        ->assertRedirect()->assertSessionHasErrors('module');

    expect(app(ModuleStateRepository::class)->isEnabled('Announcement'))->toBeFalse();
    expect(DB::table('module_installations')->where('module_name', 'Announcement')->where('enabled', true)->exists())->toBeFalse();
})->with([
    'core' => [['core' => '>=99.0.0'], []],
    'PHP upper bound' => [['php' => '<8.5'], []],
    'missing dependency' => [['modules' => ['Missing' => '^1.0']], []],
    'incompatible framework' => [[], ['laravel' => '^99.0']],
    'dependency version' => [['modules' => ['LibrarySystem' => '^99.0']], []],
    'disabled dependency' => [['modules' => ['LibrarySystem' => '^1.0']], []],
]);

it('rejects module identity collisions before choosing an installed copy', function (string $field, string $value): void {
    $first = json_decode(File::get(base_path('Modules/Announcement/module.json')), true, 512, JSON_THROW_ON_ERROR);
    $second = $first;
    $second['name'] = 'Other';
    $second['alias'] = 'other';
    $second['composer_package'] = 'school-tools/other';
    $second[$field] = $value;
    File::ensureDirectoryExists($this->moduleFixturePath.'/Other');
    File::put($this->moduleFixturePath.'/Announcement/module.json', json_encode($first, JSON_THROW_ON_ERROR));
    File::put($this->moduleFixturePath.'/Other/module.json', json_encode($second, JSON_THROW_ON_ERROR));
    $repository = new ModuleManifestRepository(app(ModuleStateRepository::class), $this->moduleFixturePath);

    expect(fn () => $repository->installed())->toThrow(InvalidArgumentException::class, 'Duplicate module identity');
})->with([
    ['name', 'Announcement'],
    ['name', 'ANNOUNCEMENT'],
    ['alias', 'announcement'],
    ['composer_package', 'koakademy/announcement'],
]);
