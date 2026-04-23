<?php

declare(strict_types=1);

use App\Support\ResourceStorageLocator;
use Illuminate\Support\Facades\Storage;

it('resolves object-storage style keys using disk exists checks', function (): void {
    Storage::fake('resource-locator');
    Storage::disk('resource-locator')->put('assessments/existing-key.pdf', 'pdf-data');

    expect(ResourceStorageLocator::exists('resource-locator', 'assessments/existing-key.pdf'))->toBeTrue();
});

it('supports local absolute file paths for legacy resource records', function (): void {
    Storage::fake('resource-locator');
    Storage::disk('resource-locator')->put('assessments/existing-absolute.pdf', 'pdf-data');

    $absolutePath = Storage::disk('resource-locator')->path('assessments/existing-absolute.pdf');

    expect(ResourceStorageLocator::exists('resource-locator', $absolutePath))->toBeTrue();
});

it('returns false when the resource does not exist on disk', function (): void {
    Storage::fake('resource-locator');

    expect(ResourceStorageLocator::exists('resource-locator', 'assessments/missing.pdf'))->toBeFalse();
});
