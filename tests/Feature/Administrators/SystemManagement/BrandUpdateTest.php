<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

test('system brand management uploads logo to default disk', function () {
    $defaultDisk = config('filesystems.default');

    Storage::fake($defaultDisk);
    if ($defaultDisk !== 'public') {
        Storage::fake('public');
    }

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

    // It should be stored on the default disk
    $files = Storage::disk($defaultDisk)->allFiles('branding');
    expect($files)->not->toBeEmpty();

    // If default is not public, it should not be on public disk
    if ($defaultDisk !== 'public') {
        expect(Storage::disk('public')->allFiles('branding'))->toBeEmpty();
    }
});
