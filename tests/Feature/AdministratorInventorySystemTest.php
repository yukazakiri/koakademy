<?php

declare(strict_types=1);

use App\Enums\InventoryItemType;
use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutVite;

/** @var User $admin */
$admin = null;

beforeEach(function () use (&$admin): void {
    withoutVite();
    config(['inertia.testing.ensure_pages_exist' => false]);

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    actingAs($admin);
});

it('renders the inventory overview', function (): void {
    get(route('administrators.inventory.index'))
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('administrators/inventory/index')
            ->has('stats')
            ->has('recent')
        );
});

it('creates an inventory item', function (): void {
    $payload = [
        'name' => 'Precision Drill',
        'sku' => 'TOOL-001',
        'item_type' => InventoryItemType::Tool->value,
        'description' => 'Cordless drill for maintenance tasks.',
        'category_id' => null,
        'supplier_id' => null,
        'price' => 0,
        'cost' => 0,
        'stock_quantity' => 5,
        'min_stock_level' => 1,
        'max_stock_level' => 10,
        'unit' => 'pcs',
        'barcode' => null,
        'track_stock' => true,
        'is_borrowable' => true,
        'is_active' => true,
        'notes' => null,
        'location_building' => 'Main Building',
        'location_floor' => '1st Floor',
        'location_area' => 'Tool Room',
        'ip_address' => null,
        'wifi_ssid' => null,
        'wifi_password' => null,
        'login_username' => null,
        'login_password' => null,
    ];

    post(route('administrators.inventory.items.store'), $payload)
        ->assertRedirect(route('administrators.inventory.items.index'));

    assertDatabaseHas('inventory_products', [
        'name' => 'Precision Drill',
        'sku' => 'TOOL-001',
        'item_type' => InventoryItemType::Tool->value,
    ]);
});

it('logs a borrowing and reduces stock', function () use (&$admin): void {
    $product = App\Models\InventoryProduct::factory()->create([
        'stock_quantity' => 4,
        'min_stock_level' => 1,
        'item_type' => InventoryItemType::Tool->value,
        'is_borrowable' => true,
        'track_stock' => true,
    ]);

    $payload = [
        'product_id' => $product->id,
        'quantity_borrowed' => 2,
        'borrower_name' => 'Alex Tan',
        'borrower_email' => 'alex@example.com',
        'borrower_phone' => '+63 900 000 0000',
        'department' => 'Facilities',
        'purpose' => 'Repairing ceiling tiles',
        'status' => 'borrowed',
        'borrowed_date' => now()->toDateTimeString(),
        'expected_return_date' => now()->addDays(5)->toDateTimeString(),
        'issued_by' => $admin->id,
    ];

    post(route('administrators.inventory.borrowings.store'), $payload)
        ->assertRedirect(route('administrators.inventory.borrowings.index'));

    $product->refresh();
    expect($product->stock_quantity)->toBe(2);

    assertDatabaseHas('inventory_borrowings', [
        'product_id' => $product->id,
        'borrower_name' => 'Alex Tan',
        'status' => 'borrowed',
    ]);
});
