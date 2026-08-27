<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

it('creates a disabled status file for fresh installations', function (): void {
    $statusesPath = storage_path('framework/testing/module-statuses-disabled-'.uniqid().'.json');

    try {
        config(['modules.statuses-file' => $statusesPath]);

        expect(Artisan::call('modules:initialize-statuses', [
            '--mode' => 'disabled',
            '--no-interaction' => true,
        ]))->toBe(0);

        $statuses = json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR);

        expect($statuses)
            ->toHaveCount(6)
            ->toMatchArray([
                'Announcement' => false,
                'Cashier' => false,
                'Inventory' => false,
                'LibrarySystem' => false,
                'NotificationCenter' => false,
                'StudentMedicalRecords' => false,
            ]);
    } finally {
        File::delete($statusesPath);
    }
});

it('preserves the image status file for upgrades and never overwrites an existing status file', function (): void {
    $statusesPath = storage_path('framework/testing/module-statuses-preserve-'.uniqid().'.json');

    try {
        config(['modules.statuses-file' => $statusesPath]);

        expect(Artisan::call('modules:initialize-statuses', [
            '--mode' => 'preserve',
            '--no-interaction' => true,
        ]))->toBe(0);

        expect(json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR))
            ->toMatchArray([
                'Announcement' => true,
                'Cashier' => true,
                'Inventory' => true,
                'LibrarySystem' => true,
                'NotificationCenter' => true,
                'StudentMedicalRecords' => true,
            ]);

        File::put($statusesPath, json_encode(['Cashier' => false], JSON_THROW_ON_ERROR));

        expect(Artisan::call('modules:initialize-statuses', [
            '--mode' => 'disabled',
            '--no-interaction' => true,
        ]))->toBe(0);

        expect(json_decode(File::get($statusesPath), true, 512, JSON_THROW_ON_ERROR))
            ->toBe(['Cashier' => false]);
    } finally {
        File::delete($statusesPath);
    }
});
