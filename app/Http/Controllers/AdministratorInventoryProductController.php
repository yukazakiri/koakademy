<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\InventoryItemType;
use App\Http\Requests\Administrators\InventoryProductRequest;
use App\Models\InventoryCategory;
use App\Models\InventoryProduct;
use App\Models\InventorySupplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'unit' => $product->unit,
                'track_stock' => $product->track_stock,
                'is_borrowable' => $product->is_borrowable,
                'is_active' => $product->is_active,
                'location' => $product->locationLabel(),
                'ip_address' => $product->ip_address,
                'wifi_ssid' => $product->wifi_ssid,
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

        InventoryProduct::create($validated);

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
            ],
            'defaults' => null,
            'options' => $this->getItemOptions(),
        ]);
    }

    public function update(InventoryProductRequest $request, InventoryProduct $inventoryProduct): RedirectResponse
    {
        $validated = $this->normalizeProductData($request->validated());

        $inventoryProduct->update($validated);

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

    private function normalizeProductData(array $validated): array
    {
        $itemType = InventoryItemType::from($validated['item_type']);
        $validated['item_type'] = $itemType->value;

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

        return $validated;
    }

    private function getItemOptions(): array
    {
        return [
            'item_types' => array_map(
                fn (InventoryItemType $type): array => [
                    'value' => $type->value,
                    'label' => $type->value,
                ],
                InventoryItemType::cases()
            ),
            'categories' => InventoryCategory::query()
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryCategory $category): array => [
                    'value' => $category->id,
                    'label' => $category->name,
                ])
                ->values()
                ->all(),
            'suppliers' => InventorySupplier::query()
                ->orderBy('name')
                ->get()
                ->map(fn (InventorySupplier $supplier): array => [
                    'value' => $supplier->id,
                    'label' => $supplier->name,
                ])
                ->values()
                ->all(),
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
}
