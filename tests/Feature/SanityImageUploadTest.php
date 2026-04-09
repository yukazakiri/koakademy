<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\SanityService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\mock;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->admin = User::factory()->create([
        'role' => App\Enums\UserRole::SuperAdmin,
    ]);
});

it('can upload an image to sanity', function () {
    actingAs($this->admin);

    Storage::fake('local');
    $file = UploadedFile::fake()->image('test-image.jpg');

    // Mock SanityService
    mock(SanityService::class)
        ->shouldReceive('uploadImage')
        ->once()
        ->andReturn([
            'assetId' => 'image-123-400x400-jpg',
            'url' => 'https://cdn.sanity.io/images/project/dataset/123-400x400.jpg',
            'filename' => 'test-image.jpg',
        ]);

    post(route('administrators.sanity-content.upload-image'), [
        'image' => $file,
    ])
        ->assertRedirect()
        ->assertSessionHas('flash.imageData', [
            'assetId' => 'image-123-400x400-jpg',
            'url' => 'https://cdn.sanity.io/images/project/dataset/123-400x400.jpg',
            'filename' => 'test-image.jpg',
        ]);
});

it('handles upload failure from sanity service', function () {
    actingAs($this->admin);

    Storage::fake('local');
    $file = UploadedFile::fake()->image('test-image.jpg');

    mock(SanityService::class)
        ->shouldReceive('uploadImage')
        ->once()
        ->andReturn(null);

    post(route('administrators.sanity-content.upload-image'), [
        'image' => $file,
    ])
        ->assertRedirect()
        ->assertSessionHas('flash.error', 'Failed to upload image to Sanity');
});

it('validates image file', function () {
    actingAs($this->admin);

    post(route('administrators.sanity-content.upload-image'), [
        'image' => 'not-an-image',
    ])
        ->assertSessionHasErrors('image');
});

it('validates image size', function () {
    actingAs($this->admin);

    Storage::fake('local');
    // 11MB image (limit is 10MB)
    $file = UploadedFile::fake()->create('large-image.jpg', 11264);

    post(route('administrators.sanity-content.upload-image'), [
        'image' => $file,
    ])
        ->assertSessionHasErrors('image');
});
