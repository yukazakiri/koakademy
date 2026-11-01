<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\Faculty;
use App\Models\GeneralSetting;
use App\Models\User;
use App\Services\LogoConversionService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * SEC-017 · SVG uploads, unenforced API flag and unhardened session cookie defaults.
 */
beforeEach(function (): void {
    GeneralSetting::factory()->create([
        'semester' => 1,
        'school_starting_date' => '2026-08-01',
        'school_ending_date' => '2027-05-31',
    ]);
});

it('rejects svg uploads when creating a faculty member', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $svgFile = UploadedFile::fake()->createWithContent(
        'photo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
    );

    $this->actingAs($admin)
        ->post(route('administrators.faculties.store'), [
            'faculty_id_number' => 'FAC-99901',
            'first_name' => 'Malicious',
            'last_name' => 'Tester',
            'email' => 'malicious.svg@example.test',
            'status' => 'active',
            'photo' => $svgFile,
        ])
        ->assertSessionHasErrors(['photo']);

    expect(Faculty::query()->where('email', 'malicious.svg@example.test')->exists())->toBeFalse();
});

it('rejects svg uploads when updating a faculty member', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $faculty = Faculty::factory()->create([
        'faculty_id_number' => 'FAC-99902',
        'first_name' => 'Safe',
        'last_name' => 'Faculty',
        'email' => 'safe.faculty@example.test',
        'status' => 'active',
    ]);

    $svgFile = UploadedFile::fake()->createWithContent(
        'photo.svg',
        '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'
    );

    $this->actingAs($admin)
        ->put(route('administrators.faculties.update', $faculty), [
            'faculty_id_number' => 'FAC-99902',
            'first_name' => 'Safe',
            'last_name' => 'Faculty',
            'email' => 'safe.faculty@example.test',
            'status' => 'active',
            'photo' => $svgFile,
        ])
        ->assertSessionHasErrors(['photo']);
});

it('accepts valid raster image uploads for faculty photo', function (): void {
    Storage::fake('public');
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $pngFile = UploadedFile::fake()->image('photo.png', 200, 200);

    $this->actingAs($admin)
        ->post(route('administrators.faculties.store'), [
            'faculty_id_number' => 'FAC-99903',
            'first_name' => 'Valid',
            'last_name' => 'Faculty',
            'email' => 'valid.faculty@example.test',
            'status' => 'active',
            'photo' => $pngFile,
        ])
        ->assertSessionDoesntHaveErrors(['photo'])
        ->assertRedirect();

    $faculty = Faculty::query()->where('email', 'valid.faculty@example.test')->first();
    expect($faculty)->not->toBeNull()
        ->and($faculty->photo_url)->not->toBeNull();

    $storedPath = $faculty->getRawOriginal('photo_url');
    Storage::disk('public')->assertExists($storedPath);
});

it('enforces API_ENABLED flag across api routes', function (): void {
    Config::set('api.enabled', false);

    $response = $this->getJson('/api/v1/public/settings');

    $response->assertNotFound()
        ->assertJson([
            'error' => true,
            'message' => 'The API is disabled for this installation.',
            'code' => 'NOT_FOUND',
        ]);
});

it('allows api routes when API_ENABLED is true', function (): void {
    Config::set('api.enabled', true);

    $response = $this->getJson('/api/v1/public/settings');

    $response->assertOk();
});

it('never stores raw svg to public disk during logo conversion', function (): void {
    Storage::fake('public');
    Storage::fake('private');

    // Create a real PNG to verify LogoConversionService stores raster files to public disk and never logo.svg
    $pngFile = UploadedFile::fake()->image('logo.png', 256, 256);

    $service = app(LogoConversionService::class);
    $paths = $service->process($pngFile, 'public');

    expect($paths)->toHaveKeys(['logo', 'favicon', 'og_image']);
    Storage::disk('public')->assertExists($paths['logo']);
    Storage::disk('public')->assertMissing('branding/*/logo.svg');
});

it('has secure session and debug defaults configured', function (): void {
    $envRepo = Illuminate\Support\Env::getRepository();
    $origEnv = $_SERVER['APP_ENV'] ?? env('APP_ENV');
    $origSecure = $_SERVER['SESSION_SECURE_COOKIE'] ?? env('SESSION_SECURE_COOKIE');
    $origEncrypt = $_SERVER['SESSION_ENCRYPT'] ?? env('SESSION_ENCRYPT');

    $setEnv = static function (string $key, ?string $value) use ($envRepo): void {
        if ($value === null) {
            $envRepo->clear($key);
            unset($_SERVER[$key], $_ENV[$key]);
            putenv($key);
        } else {
            $envRepo->set($key, $value);
            $_SERVER[$key] = $value;
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    };

    try {
        // 1. Session encryption is enabled by default
        $setEnv('SESSION_ENCRYPT', null);
        $sessionConfig = require config_path('session.php');
        expect($sessionConfig['encrypt'])->toBeTrue();

        // 2. Session secure cookie is driven by app.env when SESSION_SECURE_COOKIE is unset
        $setEnv('SESSION_SECURE_COOKIE', null);

        $setEnv('APP_ENV', 'production');
        $prodSession = require config_path('session.php');
        expect($prodSession['secure'])->toBeTrue();

        $setEnv('APP_ENV', 'local');
        $localSession = require config_path('session.php');
        expect($localSession['secure'])->toBeFalse();

        // When SESSION_SECURE_COOKIE is explicitly configured, it is respected
        $setEnv('SESSION_SECURE_COOKIE', 'false');
        $setEnv('APP_ENV', 'production');
        $explicitFalse = require config_path('session.php');
        expect($explicitFalse['secure'])->toBeFalse();

        $setEnv('SESSION_SECURE_COOKIE', 'true');
        $setEnv('APP_ENV', 'local');
        $explicitTrue = require config_path('session.php');
        expect($explicitTrue['secure'])->toBeTrue();

        // 3. .env.example sets APP_DEBUG=false
        $envExample = File::get(base_path('.env.example'));
        expect($envExample)->toMatch('/^APP_DEBUG=false/m');
    } finally {
        $setEnv('APP_ENV', $origEnv !== null ? (string) $origEnv : null);
        $setEnv('SESSION_SECURE_COOKIE', $origSecure !== null ? (string) $origSecure : null);
        $setEnv('SESSION_ENCRYPT', $origEncrypt !== null ? (string) $origEncrypt : null);
    }
});
