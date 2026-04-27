<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use App\Settings\SiteSettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;

use function Pest\Laravel\actingAs;

/**
 * Grants the necessary brand permissions to a user.
 */
function grantBrandPermissions(User $user): void
{
    foreach (['View:SystemManagementBrand', 'Update:SystemManagementBrand'] as $permission) {
        Permission::firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo(['View:SystemManagementBrand', 'Update:SystemManagementBrand']);
}

test('uploading a png logo generates all branding files and saves relative path to settings', function () {
    Storage::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantBrandPermissions($user);

    $file = UploadedFile::fake()->image('logo.png', 512, 512);

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'logo' => $file,
            'app_name' => 'Test App',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $files = Storage::disk()->allFiles('branding');
    expect($files)->not->toBeEmpty();

    $settings = app(SiteSettings::class);

    // Stored path must be a relative storage key, not a full URL
    expect($settings->logo)
        ->toBeString()
        ->toStartWith('branding/')
        ->not->toStartWith('http://')
        ->not->toStartWith('https://');

    // Verify the timestamped subdirectory pattern: branding/{ts}/logo.png
    expect($settings->logo)->toMatch('/^branding\/\d+\/logo\.png$/');

    // Favicon and OG image also stored
    expect($settings->favicon)->toMatch('/^branding\/\d+\/favicon\.ico$/');
    expect($settings->og_image)->toMatch('/^branding\/\d+\/og-image\.png$/');
});

test('uploading an svg logo rasterizes it and generates all branding files', function () {
    Storage::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantBrandPermissions($user);

    // Create a minimal valid SVG file with explicit MIME type
    $svgContent = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">'
        .'<circle cx="50" cy="50" r="40" fill="#3b82f6"/>'
        .'</svg>';

    // Write to a real temp path so the UploadedFile can be read
    $tmpSvgPath = tempnam(sys_get_temp_dir(), 'test_logo_').'_logo.svg';
    file_put_contents($tmpSvgPath, $svgContent);

    $svgFile = new UploadedFile(
        $tmpSvgPath,
        'logo.svg',
        'image/svg+xml',
        null,
        true,
    );

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'logo' => $svgFile,
            'app_name' => 'SVG Brand Test',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $files = Storage::disk()->allFiles('branding');
    expect($files)->not->toBeEmpty();

    $settings = app(SiteSettings::class);

    // Must save a relative path, not a URL
    expect($settings->logo)
        ->toBeString()
        ->toStartWith('branding/')
        ->not->toStartWith('http://')
        ->not->toStartWith('https://');

    // The PNG derivatives must exist (SVG was rasterized)
    expect($settings->logo)->toMatch('/^branding\/\d+\/logo\.png$/');
    expect($settings->favicon)->toMatch('/^branding\/\d+\/favicon\.ico$/');
    expect($settings->og_image)->toMatch('/^branding\/\d+\/og-image\.png$/');

    // The original SVG should also be stored
    $ts = explode('/', $settings->logo)[1];
    expect(Storage::disk()->exists("branding/{$ts}/logo.svg"))->toBeTrue();

    // All derived sizes should be present
    $expectedFiles = [
        "branding/{$ts}/logo.png",
        "branding/{$ts}/favicon.ico",
        "branding/{$ts}/favicon-16x16.png",
        "branding/{$ts}/favicon-32x32.png",
        "branding/{$ts}/favicon-96x96.png",
        "branding/{$ts}/apple-touch-icon.png",
        "branding/{$ts}/web-app-manifest-192x192.png",
        "branding/{$ts}/web-app-manifest-512x512.png",
        "branding/{$ts}/og-image.png",
    ];

    foreach ($expectedFiles as $expectedFile) {
        expect(Storage::disk()->exists($expectedFile))
            ->toBeTrue("Expected file {$expectedFile} to exist on default disk");
    }
});

test('two successive logo uploads produce different storage paths and both files exist', function () {
    Storage::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantBrandPermissions($user);

    // First upload
    $firstFile = UploadedFile::fake()->image('logo-first.png', 200, 200);

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'logo' => $firstFile,
            'app_name' => 'First Upload',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $firstLogoPath = app(SiteSettings::class)->logo;

    // Wait 1 second to guarantee a different timestamp key
    sleep(1);

    // Second upload
    $secondFile = UploadedFile::fake()->image('logo-second.png', 300, 300);

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'logo' => $secondFile,
            'app_name' => 'Second Upload',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $secondLogoPath = app(SiteSettings::class)->logo;

    // Paths must differ — no overwriting
    expect($firstLogoPath)->not->toBe($secondLogoPath);

    // Both files must physically exist on disk
    expect(Storage::disk()->exists($firstLogoPath))->toBeTrue('First logo file was overwritten');
    expect(Storage::disk()->exists($secondLogoPath))->toBeTrue('Second logo file was not created');
});

test('brand settings can be updated without uploading a logo', function () {
    Storage::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantBrandPermissions($user);

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'app_name' => 'No Logo Update',
            'tagline' => 'Testing without logo',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $settings = app(SiteSettings::class);
    expect($settings->app_name)->toBe('No Logo Update');

    // No files should have been uploaded
    expect(Storage::disk()->allFiles('branding'))->toBeEmpty();
});

test('svg logo upload is rejected when the file is invalid', function () {
    Storage::fake();

    $user = User::factory()->create(['role' => UserRole::Admin]);
    grantBrandPermissions($user);

    // Upload a non-image file — executable extension clearly fails the mimes rule
    $invalidFile = UploadedFile::fake()->create('malicious.exe', 10, 'application/octet-stream');

    actingAs($user)
        ->put(route('administrators.system-management.brand.update'), [
            'logo' => $invalidFile,
            'app_name' => 'Malicious Upload',
        ])
        ->assertSessionHasErrors('logo');
});
