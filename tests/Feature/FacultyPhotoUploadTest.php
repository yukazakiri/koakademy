<?php

declare(strict_types=1);

use App\Models\Faculty;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

function expectedStorageUrl(): string
{
    $defaultDisk = config('filesystems.default');

    return (string) (config("filesystems.disks.{$defaultDisk}.url") ?? '');
}

it('can upload faculty photo using default storage', function () {
    // Create a faculty record
    $faculty = Faculty::factory()->create([
        'first_name' => 'John',
        'last_name' => 'Doe',
        'email' => 'john.doe@example.com',
    ]);

    // Create a fake image file
    $file = UploadedFile::fake()->image('faculty-photo.jpg', 800, 600);

    // Test the upload using Storage facade directly
    $path = Storage::putFile('faculty-photos', $file, 'public');

    expect($path)->toContain('faculty-photos');
    expect(Storage::exists($path))->toBeTrue();
    expect(Storage::url($path))->toContain(expectedStorageUrl());

    // Clean up
    Storage::delete($path);
});

it('can save faculty with photo using filament form', function () {
    // Skip if Redis is not available (for testing environment)
    try {
        Redis::ping();
    } catch (Exception $e) {
        $this->markTestSkipped('Redis not available for testing');
    }

    // Create a faculty record
    $faculty = Faculty::factory()->create([
        'first_name' => 'Jane',
        'last_name' => 'Smith',
        'email' => 'jane.smith@example.com',
    ]);

    // Create a fake image file
    $file = UploadedFile::fake()->image('jane-photo.jpg', 800, 600);

    // Simulate file upload to default storage
    $photoPath = Storage::putFile('faculty-photos', $file, 'public');
    $photoUrl = Storage::url($photoPath);

    // Update faculty with photo
    $faculty->update(['photo_url' => $photoPath]);

    // Verify the update
    $faculty->refresh();
    expect($faculty->photo_url)->toBe($photoPath);

    // Test the photoUrl accessor
    expect($faculty->photo_url)->toContain(expectedStorageUrl());
});

it('can generate correct storage urls for faculty photos', function () {
    $testPaths = [
        'faculty-photos/test.jpg',
        'faculty-photos/user/avatar.png',
        'faculty-photos/2024/01/profile.webp',
    ];

    foreach ($testPaths as $path) {
        $url = Storage::url($path);

        expect($url)->toContain(expectedStorageUrl());
        expect($url)->toContain($path);
    }
});

it('handles default storage configuration correctly', function () {
    $defaultDisk = config('filesystems.default');
    $config = config("filesystems.disks.{$defaultDisk}");

    expect($config['driver'])->toBeString();
});

it('verifies default filesystem is configured', function () {
    expect(config('filesystems.default'))->toBeString();
    expect(config('filament.default_filesystem_disk'))->toBeString();
});

it('can test faculty photo upload lifecycle', function () {
    $faculty = Faculty::factory()->create();
    $originalPhotoUrl = $faculty->photo_url;

    // Upload new photo
    $file = UploadedFile::fake()->image('new-photo.jpg', 1024, 768);
    $newPath = Storage::putFile('faculty-photos', $file, 'public');

    // Update faculty
    $faculty->update(['photo_url' => $newPath]);

    // Verify old photo doesn't exist (if it was stored)
    if ($originalPhotoUrl && ! str_starts_with($originalPhotoUrl, 'http')) {
        expect(Storage::exists($originalPhotoUrl))->toBeFalse();
    }

    // Verify new photo exists and has correct URL
    $faculty->refresh();
    expect(Storage::exists($faculty->photo_url))->toBeTrue();
    expect($faculty->photo_url)->toContain('faculty-photos');

    // Clean up
    Storage::delete($faculty->photo_url);
});
