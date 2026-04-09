<?php

declare(strict_types=1);

use App\Enums\InventoryItemType;
use App\Models\InventoryProduct;

it('casts item type to enum instances', function () {
    $product = new InventoryProduct([
        'item_type' => InventoryItemType::Router->value,
    ]);

    expect($product->item_type)->toBe(InventoryItemType::Router);
});

it('defaults inventory products to tool and borrowable', function () {
    $product = new InventoryProduct();

    expect($product->item_type)->toBe(InventoryItemType::Tool)
        ->and($product->is_borrowable)->toBeTrue();
});

it('recognizes network devices and formats locations', function () {
    $product = new InventoryProduct([
        'item_type' => InventoryItemType::Cctv->value,
        'location_building' => 'Main Building',
        'location_floor' => '2nd Floor',
        'location_area' => 'Hallway',
    ]);

    expect($product->isNetworkDevice())->toBeTrue()
        ->and($product->locationLabel())->toBe('Main Building - 2nd Floor - Hallway');
});
