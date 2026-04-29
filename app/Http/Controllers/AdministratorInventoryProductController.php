<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InventoryHistoryEventType;
use App\Enums\InventoryItemType;
use App\Http\Requests\Administrators\InventoryItemLocationUpdateRequest;
use App\Http\Requests\Administrators\InventoryProductRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventoryProductHistory;
use App\Models\InventorySupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

final class AdministratorInventoryProductController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $itemType = $request->input('item_type');
        $borrowable = $request->input('borrowable');
        $status = $request->input('status');

        $query = InventoryProduct::query()
            ->with(['category', 'supplier']);

        if (is_string($search) && mb_trim($search) !== '') {
            $term = mb_trim($search);
            $query->where(function ($nested) use ($term): void {
                $nested->where('name', 'ilike', "%{$term}%")
                    ->orWhere('sku', 'ilike', "%{$term}%")
                    ->orWhere('location_building', 'ilike', "%{$term}%")
                    ->orWhere('location_floor', 'ilike', "%{$term}%")
                    ->orWhere('location_area', 'ilike', "%{$term}%")
                    ->orWhere('ip_address', 'ilike', "%{$term}%")
                    ->orWhere('wifi_ssid', 'ilike', "%{$term}%")
                    ->orWhere('login_username', 'ilike', "%{$term}%");
            });
        }

        $itemTypes = $this->resolveItemTypeFilter(is_string($itemType) ? $itemType : null);
        if ($itemTypes !== []) {
            $query->whereIn('item_type', $itemTypes);
        }

        if (is_string($borrowable) && $borrowable !== '' && $borrowable !== 'all') {
            $query->where('is_borrowable', $borrowable === 'yes');
        }

        if (is_string($status) && $status !== '' && $status !== 'all') {
            $query->where('is_active', $status === 'active');
        }

        $items = $query
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (InventoryProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'item_type' => $product->item_type instanceof InventoryItemType
                    ? $product->item_type->value
                    : (string) $product->item_type,
                'category' => $product->category?->name,
                'supplier' => $product->supplier?->name,
                'stock_quantity' => $product->stock_quantity,
                'defective_quantity' => $product->defective_quantity,
                'total_quantity' => $product->stock_quantity + $product->defective_quantity,
                'unit' => $product->unit,
                'track_stock' => $product->track_stock,
                'is_borrowable' => $product->is_borrowable,
                'is_active' => $product->is_active,
                'location' => $product->locationLabel(),
                'ip_address' => $product->ip_address,
                'wifi_ssid' => $product->wifi_ssid,
                'image_urls' => $this->resolveImageUrls($product->images),
                'updated_at' => format_timestamp($product->updated_at),
            ]);

        $networkTypes = InventoryItemType::networkValues();
        $stats = [
            'total_items' => InventoryProduct::count(),
            'tools' => InventoryProduct::query()
                ->where('item_type', InventoryItemType::Tool->value)
                ->count(),
            'network_devices' => InventoryProduct::query()
                ->whereIn('item_type', $networkTypes)
                ->count(),
            'borrowable_items' => InventoryProduct::query()
                ->where('is_borrowable', true)
                ->count(),
            'low_stock' => InventoryProduct::query()->lowStock()->count(),
            'defective_units' => InventoryProduct::query()->sum('defective_quantity'),
        ];

        return Inertia::render('administrators/inventory/items/index', [
            'user' => $this->getUserProps(),
            'items' => $items,
            'stats' => $stats,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'item_type' => is_string($itemType) ? $itemType : null,
                'borrowable' => is_string($borrowable) ? $borrowable : null,
                'status' => is_string($status) ? $status : null,
            ],
            'options' => [
                'item_types' => [
                    ['value' => 'all', 'label' => 'All items'],
                    ['value' => 'tool', 'label' => 'Tools & equipment'],
                    ['value' => 'network', 'label' => 'Network devices'],
                    ['value' => 'router', 'label' => 'Routers'],
                    ['value' => 'nvr', 'label' => 'NVR'],
                    ['value' => 'cctv', 'label' => 'CCTV'],
                ],
                'borrowable' => [
                    ['value' => 'all', 'label' => 'All items'],
                    ['value' => 'yes', 'label' => 'Borrowable'],
                    ['value' => 'no', 'label' => 'Not borrowable'],
                ],
                'statuses' => [
                    ['value' => 'all', 'label' => 'All statuses'],
                    ['value' => 'active', 'label' => 'Active'],
                    ['value' => 'inactive', 'label' => 'Inactive'],
                ],
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(Request $request): Response
    {
        $defaultType = $this->resolveCreateItemType($request->input('item_type'));

        return Inertia::render('administrators/inventory/items/edit', [
            'user' => $this->getUserProps(),
            'product' => null,
            'defaults' => [
                'item_type' => $defaultType,
            ],
            'options' => $this->getItemOptions(),
        ]);
    }

    public function store(InventoryProductRequest $request): RedirectResponse
    {
        $validated = $this->normalizeProductData($request->validated());

        $product = InventoryProduct::create($validated);
        $this->recordProductHistory($product, InventoryHistoryEventType::ItemCreated->value, null, $this->snapshotProductState($product), 'Item created');

        return redirect()
            ->route('administrators.inventory.items.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Inventory item created successfully.',
            ]);
    }

    public function edit(InventoryProduct $inventoryProduct): Response
    {
        return Inertia::render('administrators/inventory/items/edit', [
            'user' => $this->getUserProps(),
            'product' => [
                'id' => $inventoryProduct->id,
                'name' => $inventoryProduct->name,
                'sku' => $inventoryProduct->sku,
                'item_type' => $inventoryProduct->item_type instanceof InventoryItemType
                    ? $inventoryProduct->item_type->value
                    : (string) $inventoryProduct->item_type,
                'description' => $inventoryProduct->description,
                'category_id' => $inventoryProduct->category_id,
                'supplier_id' => $inventoryProduct->supplier_id,
                'price' => $inventoryProduct->price,
                'cost' => $inventoryProduct->cost,
                'stock_quantity' => $inventoryProduct->stock_quantity,
                'defective_quantity' => $inventoryProduct->defective_quantity,
                'min_stock_level' => $inventoryProduct->min_stock_level,
                'max_stock_level' => $inventoryProduct->max_stock_level,
                'unit' => $inventoryProduct->unit,
                'barcode' => $inventoryProduct->barcode,
                'track_stock' => $inventoryProduct->track_stock,
                'is_borrowable' => $inventoryProduct->is_borrowable,
                'is_active' => $inventoryProduct->is_active,
                'notes' => $inventoryProduct->notes,
                'location_building' => $inventoryProduct->location_building,
                'location_floor' => $inventoryProduct->location_floor,
                'location_area' => $inventoryProduct->location_area,
                'ip_address' => $inventoryProduct->ip_address,
                'wifi_ssid' => $inventoryProduct->wifi_ssid,
                'wifi_password' => $inventoryProduct->wifi_password,
                'login_username' => $inventoryProduct->login_username,
                'login_password' => $inventoryProduct->login_password,
                'image_urls' => $this->resolveImageUrls($inventoryProduct->images),
                'history' => $inventoryProduct->histories()
                    ->latest('recorded_at')
                    ->limit(25)
                    ->get()
                    ->map(fn (InventoryProductHistory $history): array => [
                        'id' => $history->id,
                        'event_type' => $history->event_type,
                        'before' => $history->before,
                        'after' => $history->after,
                        'notes' => $history->notes,
                        'recorded_at' => format_timestamp($history->recorded_at),
                    ])
                    ->all(),
                'recent_borrowings' => $inventoryProduct->borrowings()
                    ->latest('borrowed_date')
                    ->limit(10)
                    ->get()
                    ->map(fn ($borrowing): array => [
                        'id' => $borrowing->id,
                        'borrower_name' => $borrowing->borrower_name,
                        'status' => $borrowing->status,
                        'quantity_borrowed' => $borrowing->quantity_borrowed,
                        'quantity_returned' => $borrowing->quantity_returned,
                        'borrowed_date' => format_timestamp($borrowing->borrowed_date),
                    ])
                    ->all(),
                'location_history' => $inventoryProduct->histories()
                    ->where('event_type', InventoryHistoryEventType::LocationMoved->value)
                    ->latest('recorded_at')
                    ->limit(25)
                    ->get()
                    ->map(fn (InventoryProductHistory $history): array => [
                        'id' => $history->id,
                        'from_location' => is_array($history->before)
                            ? (string) ($history->before['location_label'] ?? 'Unknown')
                            : 'Unknown',
                        'to_location' => is_array($history->after)
                            ? (string) ($history->after['location_label'] ?? 'Unknown')
                            : 'Unknown',
                        'notes' => $history->notes,
                        'recorded_at' => format_timestamp($history->recorded_at),
                    ])
                    ->all(),
            ],
            'defaults' => null,
            'options' => $this->getItemOptions(),
        ]);
    }

    public function updateLocation(InventoryItemLocationUpdateRequest $request, InventoryProduct $inventoryProduct): RedirectResponse
    {
        $beforeState = $this->snapshotProductState($inventoryProduct);
        $validated = $request->validated();

        $inventoryProduct->update([
            'location_building' => $validated['location_building'],
            'location_floor' => $validated['location_floor'] ?? null,
            'location_area' => $validated['location_area'] ?? null,
        ]);

        $afterState = $this->snapshotProductState($inventoryProduct->fresh());

        $this->recordProductHistory(
            $inventoryProduct,
            InventoryHistoryEventType::LocationMoved->value,
            $beforeState,
            $afterState,
            (string) ($validated['notes'] ?? 'Location updated')
        );

        return redirect()
            ->route('administrators.inventory.items.edit', $inventoryProduct)
            ->with('flash', [
                'type' => 'success',
                'message' => 'Current location updated and recorded in timeline.',
            ]);
    }

    public function update(InventoryProductRequest $request, InventoryProduct $inventoryProduct): RedirectResponse
    {
        $beforeState = $this->snapshotProductState($inventoryProduct);
        $validated = $this->normalizeProductData($request->validated(), $inventoryProduct);

        $inventoryProduct->update($validated);

        $afterState = $this->snapshotProductState($inventoryProduct->fresh());
        $eventType = $this->resolveProductUpdateEventType($beforeState, $afterState);
        $this->recordProductHistory($inventoryProduct, $eventType, $beforeState, $afterState, 'Item details updated');

        return redirect()
            ->route('administrators.inventory.items.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Inventory item updated.',
            ]);
    }

    public function destroy(InventoryProduct $inventoryProduct): RedirectResponse
    {
        $inventoryProduct->delete();

        return redirect()
            ->route('administrators.inventory.items.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Inventory item removed.',
            ]);
    }

    /**
     * @return list<string>
     */
    private function resolveItemTypeFilter(?string $itemType): array
    {
        if (! is_string($itemType) || $itemType === '' || $itemType === 'all') {
            return [];
        }

        $normalized = mb_strtolower($itemType);

        if ($normalized === 'network') {
            return InventoryItemType::networkValues();
        }

        foreach (InventoryItemType::cases() as $type) {
            if (mb_strtolower($type->value) === $normalized) {
                return [$type->value];
            }
        }

        return [];
    }

    private function resolveCreateItemType(mixed $itemType): string
    {
        if (! is_string($itemType) || $itemType === '') {
            return InventoryItemType::Tool->value;
        }

        $normalized = mb_strtolower($itemType);

        if ($normalized === 'network') {
            return InventoryItemType::Router->value;
        }

        foreach (InventoryItemType::cases() as $type) {
            if (mb_strtolower($type->value) === $normalized) {
                return $type->value;
            }
        }

        return InventoryItemType::Tool->value;
    }

    private function normalizeProductData(array $validated, ?InventoryProduct $inventoryProduct = null): array
    {
        $itemType = InventoryItemType::from($validated['item_type']);
        $validated['item_type'] = $itemType->value;
        $validated['sku'] = $this->resolveSku(
            sku: $validated['sku'] ?? null,
            itemType: $itemType->value,
            locationBuilding: (string) ($validated['location_building'] ?? ''),
            ignoreProductId: $inventoryProduct?->id
        );

        if ($itemType !== InventoryItemType::Tool) {
            $validated['is_borrowable'] = false;
        }

        if (! in_array($itemType->value, InventoryItemType::networkValues(), true)) {
            $validated['ip_address'] = null;
            $validated['wifi_ssid'] = null;
            $validated['wifi_password'] = null;
            $validated['login_username'] = null;
            $validated['login_password'] = null;
        }

        if (isset($validated['category_name']) && is_string($validated['category_name']) && mb_trim($validated['category_name']) !== '') {
            $category = InventoryCategory::query()->firstOrCreate(
                ['name' => mb_trim($validated['category_name'])],
                [
                    'slug' => Str::slug($validated['category_name']),
                    'is_active' => true,
                    'sort_order' => 0,
                ]
            );
            $validated['category_id'] = $category->id;
        }

        if (isset($validated['supplier_name']) && is_string($validated['supplier_name']) && mb_trim($validated['supplier_name']) !== '') {
            $supplier = InventorySupplier::query()->firstOrCreate(
                ['name' => mb_trim($validated['supplier_name'])],
                ['is_active' => true]
            );
            $validated['supplier_id'] = $supplier->id;
        }

        unset($validated['category_name'], $validated['supplier_name']);

        $validated['images'] = $this->storeUploadedImages($validated['images'] ?? null, $inventoryProduct?->images);

        return $validated;
    }

    private function resolveSku(?string $sku, string $itemType, string $locationBuilding, ?int $ignoreProductId = null): string
    {
        if (is_string($sku) && mb_trim($sku) !== '') {
            return mb_strtoupper(mb_trim($sku));
        }

        $typeCode = mb_substr(mb_strtoupper($itemType), 0, 3);
        $buildingCode = $this->resolveBuildingCode($locationBuilding);
        $datePart = now()->format('Ymd');
        $skuPrefix = sprintf('%s-%s-%s', $typeCode, $buildingCode, $datePart);

        $highestSequence = InventoryProduct::query()
            ->when($ignoreProductId, fn ($query) => $query->where('id', '!=', $ignoreProductId))
            ->where('sku', 'like', $skuPrefix.'-%')
            ->pluck('sku')
            ->map(function (string $existingSku) use ($skuPrefix): int {
                $suffix = mb_substr($existingSku, mb_strlen($skuPrefix) + 1);

                return is_numeric($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0;

        return sprintf('%s-%03d', $skuPrefix, $highestSequence + 1);
    }

    private function resolveBuildingCode(string $building): string
    {
        $normalized = mb_strtoupper(Str::slug($building, ''));

        if ($normalized === '') {
            return 'GEN';
        }

        return mb_substr($normalized, 0, 4);
    }

    /**
     * @return list<string>
     */
    private function storeUploadedImages(mixed $images, mixed $currentImages = null): array
    {
        if (! is_array($images)) {
            return is_array($currentImages) ? $currentImages : [];
        }

        if ($images === []) {
            return is_array($currentImages) ? $currentImages : [];
        }

        return collect($images)
            ->filter(fn (mixed $image): bool => $image instanceof UploadedFile)
            ->map(fn (UploadedFile $image): string => $image->storePublicly('inventory-products', 'public'))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function resolveImageUrls(mixed $images): array
    {
        if (! is_array($images)) {
            return [];
        }

        return collect($images)
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->map(fn (string $path): string => Storage::disk('public')->url($path))
            ->values()
            ->all();
    }

    private function getItemOptions(): array
    {
        $categoryOptions = [];

        if (Schema::hasTable('inventory_categories')) {
            $categoryOptions = InventoryCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryCategory $category): array => [
                    'value' => $category->id,
                    'label' => $category->name,
                ])
                ->values()
                ->all();
        }

        $supplierOptions = [];

        if (Schema::hasTable('inventory_suppliers')) {
            $supplierOptions = InventorySupplier::query()
                ->orderBy('name')
                ->get()
                ->map(fn (InventorySupplier $supplier): array => [
                    'value' => $supplier->id,
                    'label' => $supplier->name,
                ])
                ->values()
                ->all();
        }

        $locationOptions = InventoryProduct::query()
            ->select(['location_building', 'location_floor', 'location_area'])
            ->get();

        $buildings = $locationOptions
            ->pluck('location_building')
            ->filter(fn (mixed $building): bool => is_string($building) && mb_trim($building) !== '')
            ->map(fn (string $building): string => mb_trim($building))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $floors = $locationOptions
            ->pluck('location_floor')
            ->filter(fn (mixed $floor): bool => is_string($floor) && mb_trim($floor) !== '')
            ->map(fn (string $floor): string => mb_trim($floor))
            ->unique()
            ->sort()
            ->values()
            ->all();

        $areas = $locationOptions
            ->pluck('location_area')
            ->filter(fn (mixed $area): bool => is_string($area) && mb_trim($area) !== '')
            ->map(fn (string $area): string => mb_trim($area))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return [
            'item_types' => array_map(
                fn (InventoryItemType $type): array => [
                    'value' => $type->value,
                    'label' => $type->value,
                ],
                InventoryItemType::cases()
            ),
            'categories' => $categoryOptions,
            'suppliers' => $supplierOptions,
            'locations' => [
                'buildings' => $buildings,
                'floors' => $floors,
                'areas' => $areas,
            ],
        ];
    }

    private function getUserProps(): array
    {
        $user = request()->user();

        if (! $user) {
            return [];
        }

        return [
            'name' => $user->name,
            'email' => $user->email,
            'avatar' => $user->avatar_url ?? null,
            'role' => $user->role?->getLabel() ?? 'Administrator',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshotProductState(InventoryProduct $product): array
    {
        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'good_quantity' => $product->stock_quantity,
            'defective_quantity' => $product->defective_quantity,
            'location_building' => $product->location_building,
            'location_floor' => $product->location_floor,
            'location_area' => $product->location_area,
            'location_label' => $product->locationLabel(),
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function resolveProductUpdateEventType(array $before, array $after): string
    {
        if (
            $before['location_building'] !== $after['location_building']
            || $before['location_floor'] !== $after['location_floor']
            || $before['location_area'] !== $after['location_area']
        ) {
            return InventoryHistoryEventType::LocationMoved->value;
        }

        if (
            $before['good_quantity'] !== $after['good_quantity']
            || $before['defective_quantity'] !== $after['defective_quantity']
        ) {
            return InventoryHistoryEventType::StockRebalanced->value;
        }

        return InventoryHistoryEventType::ItemUpdated->value;
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function recordProductHistory(
        InventoryProduct $product,
        string $eventType,
        ?array $before,
        array $after,
        string $notes
    ): void {
        InventoryProductHistory::query()->create([
            'product_id' => $product->id,
            'event_type' => $eventType,
            'before' => $before,
            'after' => $after,
            'notes' => $notes,
            'recorded_by' => request()->user()?->id,
            'recorded_at' => now(),
        ]);
    }
}
