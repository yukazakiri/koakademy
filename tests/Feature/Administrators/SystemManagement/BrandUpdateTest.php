<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

test('system brand management uploads logo to r2 disk and stores relative path', function () {
    Storage::fake('r2');

    $user = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    foreach (['View:SystemManagementBrand', 'Update:SystemManagementBrand'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementBrand', 'Update:SystemManagementBrand']);

    $file = UploadedFile::fake()->image('logo.png');

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'logo' => $file,
            'app_name' => 'Test App',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $files = Storage::disk('r2')->allFiles('branding');
    expect($files)->not->toBeEmpty();

    $savedLogo = app(SiteSettings::class)->logo;
    expect($savedLogo)
        ->toBeString()
        ->toStartWith('branding/')
        ->not->toStartWith('http://')
        ->not->toStartWith('https://');
});
