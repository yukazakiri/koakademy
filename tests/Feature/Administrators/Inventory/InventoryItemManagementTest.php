<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Modules\Inventory\Enums\InventoryHistoryEventType;
use Modules\Inventory\Enums\InventoryItemType;
use Modules\Inventory\Models\InventoryBorrowing;
use Modules\Inventory\Models\InventoryCategory;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryProductHistory;
use Modules\Inventory\Models\InventorySupplier;

use function Pest\Laravel\actingAs;

it('stores inventory item with auto-generated sku, condition counts, and image uploads', function (): void {
    Storage::fake('public');

    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    actingAs($admin)
        ->post(route('administrators.inventory.items.store'), [
            'name' => 'Dell Monitor 24"',
            'item_type' => InventoryItemType::Tool->value,
            'description' => 'Admin office monitor stock',
            'category_id' => null,
            'supplier_id' => null,
            'price' => '12000',
            'cost' => '9800',
            'stock_quantity' => 14,
            'defective_quantity' => 2,
            'min_stock_level' => 3,
            'max_stock_level' => 30,
            'unit' => 'pcs',
            'barcode' => null,
            'track_stock' => true,
            'is_borrowable' => true,
            'is_active' => true,
            'notes' => 'Separated by shelf labels',
            'location_building' => 'Main Building',
            'location_floor' => '2',
            'location_area' => 'Storage Room',
            'ip_address' => null,
            'wifi_ssid' => null,
            'wifi_password' => null,
            'login_username' => null,
            'login_password' => null,
            'images' => [
                UploadedFile::fake()->image('monitor-front.jpg'),
                UploadedFile::fake()->image('monitor-side.jpg'),
            ],
        ])
        ->assertRedirect(route('administrators.inventory.items.index'));

    $product = InventoryProduct::query()->where('name', 'Dell Monitor 24"')->first();

    expect($product)->not->toBeNull();

    expect($product?->stock_quantity)->toBe(14)
        ->and($product?->defective_quantity)->toBe(2)
        ->and($product?->sku)->not->toBe('')
        ->and($product?->sku)->toMatch('/^TOO-MAIN-\d{8}-\d{3}$/')
        ->and($product?->images)->toBeArray()
        ->and($product?->images)->toHaveCount(2);

    foreach ($product?->images ?? [] as $path) {
        Storage::disk('public')->assertExists($path);
    }
});

it('shows condition-based inventory metrics on dashboard', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    InventoryProduct::factory()->create([
        'stock_quantity' => 8,
        'defective_quantity' => 1,
    ]);

    InventoryProduct::factory()->create([
        'stock_quantity' => 12,
        'defective_quantity' => 3,
    ]);

    actingAs($admin)
        ->get(route('administrators.inventory.index'))
        ->assertOk()
        ->assertSee('defective_units', false)
        ->assertSee('good_units', false)
        ->assertSee('total_units', false);
});

it('tracks stock-aware good and defective returns for borrowings', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = InventoryProduct::factory()->create([
        'stock_quantity' => 20,
        'defective_quantity' => 1,
        'is_borrowable' => true,
        'track_stock' => true,
        'is_active' => true,
    ]);

    actingAs($admin)
        ->post(route('administrators.inventory.borrowings.store'), [
            'product_id' => $product->id,
            'quantity_borrowed' => 5,
            'borrower_name' => 'Juan Dela Cruz',
            'borrower_email' => 'juan@example.com',
            'borrower_phone' => '+63 900 111 1111',
            'department' => 'IT',
            'purpose' => 'Room setup',
            'status' => 'returned',
            'borrowed_date' => now()->subDay()->toDateTimeString(),
            'expected_return_date' => now()->toDateTimeString(),
            'actual_return_date' => now()->toDateTimeString(),
            'quantity_returned_good' => 4,
            'quantity_returned_defective' => 1,
            'return_notes' => 'One monitor has panel damage.',
            'issued_by' => $admin->id,
            'returned_to' => $admin->id,
        ])
        ->assertRedirect(route('administrators.inventory.borrowings.index'));

    $product->refresh();

    expect($product->stock_quantity)->toBe(19)
        ->and($product->defective_quantity)->toBe(2);

    $borrowing = InventoryBorrowing::query()->latest('id')->first();
    expect($borrowing)->not->toBeNull()
        ->and($borrowing?->quantity_returned_good)->toBe(4)
        ->and($borrowing?->quantity_returned_defective)->toBe(1);
});

it('records movement history when item location changes', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = InventoryProduct::factory()->create([
        'location_building' => 'Annex',
        'location_floor' => '1',
        'location_area' => 'Old Storage',
    ]);

    actingAs($admin)
        ->put(route('administrators.inventory.items.update', $product), [
            'name' => $product->name,
            'sku' => $product->sku,
            'item_type' => InventoryItemType::Tool->value,
            'description' => $product->description,
            'category_id' => null,
            'supplier_id' => null,
            'price' => $product->price,
            'cost' => $product->cost,
            'stock_quantity' => $product->stock_quantity,
            'defective_quantity' => $product->defective_quantity,
            'min_stock_level' => $product->min_stock_level,
            'max_stock_level' => $product->max_stock_level,
            'unit' => $product->unit,
            'barcode' => null,
            'track_stock' => $product->track_stock,
            'is_borrowable' => true,
            'is_active' => true,
            'notes' => 'Moved to main building',
            'location_building' => 'Main Building',
            'location_floor' => '2',
            'location_area' => 'Monitor Room',
            'ip_address' => null,
            'wifi_ssid' => null,
            'wifi_password' => null,
            'login_username' => null,
            'login_password' => null,
            'images' => [],
        ])
        ->assertRedirect(route('administrators.inventory.items.index'));

    $history = InventoryProductHistory::query()
        ->where('product_id', $product->id)
        ->where('event_type', InventoryHistoryEventType::LocationMoved->value)
        ->latest('id')
        ->first();

    expect($history)->not->toBeNull();
});

it('updates current location through dedicated action and records movement timeline', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $product = InventoryProduct::factory()->create([
        'location_building' => 'Annex',
        'location_floor' => '1',
        'location_area' => 'Storage A',
    ]);

    actingAs($admin)
        ->post(route('administrators.inventory.items.update-location', $product), [
            'location_building' => 'Main Building',
            'location_floor' => '2',
            'location_area' => 'Laboratory',
            'notes' => 'Moved for equipment deployment',
        ])
        ->assertRedirect(route('administrators.inventory.items.edit', $product));

    $product->refresh();

    expect($product->location_building)->toBe('Main Building')
        ->and($product->location_floor)->toBe('2')
        ->and($product->location_area)->toBe('Laboratory');

    $history = InventoryProductHistory::query()
        ->where('product_id', $product->id)
        ->where('event_type', InventoryHistoryEventType::LocationMoved->value)
        ->latest('id')
        ->first();

    expect($history)->not->toBeNull()
        ->and($history?->notes)->toBe('Moved for equipment deployment')
        ->and($history?->before)->toBeArray()
        ->and($history?->after)->toBeArray();
});

it('creates category and supplier inline from dropdown flow', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    actingAs($admin)
        ->post(route('administrators.inventory.items.store'), [
            'name' => 'Portable Projector',
            'item_type' => InventoryItemType::Tool->value,
            'description' => 'Presentation unit',
            'category_id' => null,
            'category_name' => 'Projection Devices',
            'supplier_id' => null,
            'supplier_name' => 'Main Tech Supply',
            'price' => '8000',
            'cost' => '6500',
            'stock_quantity' => 4,
            'defective_quantity' => 0,
            'min_stock_level' => 1,
            'max_stock_level' => 10,
            'unit' => 'pcs',
            'barcode' => null,
            'track_stock' => true,
            'is_borrowable' => true,
            'is_active' => true,
            'notes' => null,
            'location_building' => 'Main Building',
            'location_floor' => '3',
            'location_area' => 'AV Room',
            'ip_address' => null,
            'wifi_ssid' => null,
            'wifi_password' => null,
            'login_username' => null,
            'login_password' => null,
            'images' => [],
        ])
        ->assertRedirect(route('administrators.inventory.items.index'));

    $category = InventoryCategory::query()->where('name', 'Projection Devices')->first();
    $supplier = InventorySupplier::query()->where('name', 'Main Tech Supply')->first();

    expect($category)->not->toBeNull();
    expect($supplier)->not->toBeNull();

    $product = InventoryProduct::query()->where('name', 'Portable Projector')->first();

    expect($product)->not->toBeNull()
        ->and($product?->category_id)->toBe($category?->id)
        ->and($product?->supplier_id)->toBe($supplier?->id);
});
