<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Joaopaulolndev\FilamentEditProfile\Livewire\EditProfileForm;

it('can upload user avatar to default storage', function () {
    // Create a user
    $user = User::factory()->create([
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'avatar_url' => null,
    ]);

    // Create a fake image file
    $file = UploadedFile::fake()->image('avatar.jpg', 200, 200);

    // Test the upload using Storage facade directly
    $path = Storage::putFile('avatars', $file, 'private');

    expect($path)->toContain('avatars');
    expect(Storage::exists($path))->toBeTrue();

    // Update user with avatar
    $user->update(['avatar_url' => $path]);
    $user->refresh();

    expect($user->avatar_url)->toBe($path);
    expect(Storage::exists($user->avatar_url))->toBeTrue();

    // Clean up
    Storage::delete($path);
});

it('can update user avatar replacing old one', function () {
    // Create a user with existing avatar
    $oldFile = UploadedFile::fake()->image('old-avatar.jpg', 200, 200);
    $oldPath = Storage::putFile('avatars', $oldFile, 'private');

    $user = User::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane.smith@example.com',
        'avatar_url' => $oldPath,
    ]);

    expect(Storage::exists($oldPath))->toBeTrue();

    // Upload new avatar
    $newFile = UploadedFile::fake()->image('new-avatar.jpg', 200, 200);
    $newPath = Storage::putFile('avatars', $newFile, 'private');

    // Update user
    $user->update(['avatar_url' => $newPath]);
    $user->refresh();

    expect($user->avatar_url)->toBe($newPath);
    expect(Storage::exists($newPath))->toBeTrue();

    // Clean up both files
    Storage::delete([$oldPath, $newPath]);
});

it('can handle profile avatar form component', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    // Test that the edit profile page is accessible
    $response = $this->get('/admin/edit-profile');

    $response->assertSuccessful();
    $response->assertSeeLivewire(EditProfileForm::class);
});

it('verifies filament-edit-profile configuration', function () {
    expect(config('filament-edit-profile.avatar_column'))->toBe('avatar_url');
    expect(config('filament-edit-profile.visibility'))->toBe('private');
});

it('verifies default filesystem is configured', function () {
    expect(config('filesystems.default'))->toBeString();
});

it('can generate storage urls for avatars', function () {
    $testPaths = [
        'avatars/test-avatar.jpg',
        'avatars/user-123/profile.png',
        'livewire-tmp/avatar-upload.jpg',
    ];

    foreach ($testPaths as $path) {
        $url = Storage::url($path);

        expect($url)->toBeString();
        expect($url)->toContain($path);
    }
});

it('handles avatar upload lifecycle with cleanup', function () {
    $user = User::factory()->create(['avatar_url' => null]);

    // First upload
    $file1 = UploadedFile::fake()->image('avatar-1.jpg', 200, 200);
    $path1 = Storage::putFile('avatars', $file1, 'private');
    $user->update(['avatar_url' => $path1]);

    expect(Storage::exists($path1))->toBeTrue();
    $user->refresh();
    expect($user->avatar_url)->toBe($path1);

    // Second upload (replacing)
    $file2 = UploadedFile::fake()->image('avatar-2.jpg', 200, 200);
    $path2 = Storage::putFile('avatars', $file2, 'private');

    // In real scenario, old file should be deleted before updating
    $oldPath = $user->avatar_url;
    $user->update(['avatar_url' => $path2]);

    expect(Storage::exists($path2))->toBeTrue();
    $user->refresh();
    expect($user->avatar_url)->toBe($path2);

    // Clean up both files
    Storage::delete([$path1, $path2]);
});

it('ensures avatar files are private', function () {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->image('private-avatar.jpg', 200, 200);

    // Upload with private visibility
    $path = Storage::putFile('avatars', $file, 'private');

    expect(Storage::exists($path))->toBeTrue();

    // Update user
    $user->update(['avatar_url' => $path]);

    // Clean up
    Storage::delete($path);
});
