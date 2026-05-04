<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\InventoryItemType;
use Modules\Inventory\Models\InventoryBorrowing;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryProductHistory;

final class AdministratorInventoryController extends Controller
{
    public function index(): Response
    {
        $specializedTypes = InventoryItemType::networkValues();
        $goodUnits = InventoryProduct::query()->sum('stock_quantity');
        $defectiveUnits = InventoryProduct::query()->sum('defective_quantity');

        $stats = [
            'total_items' => InventoryProduct::count(),
            'good_units' => $goodUnits,
            'defective_units' => $defectiveUnits,
            'total_units' => $goodUnits + $defectiveUnits,
            'general_equipment' => InventoryProduct::query()->where('item_type', InventoryItemType::Tool->value)->count(),
            'specialized_assets' => InventoryProduct::query()->whereIn('item_type', $specializedTypes)->count(),
            'consumables' => InventoryProduct::query()->where('is_consumable', true)->count(),
            'borrowable_items' => InventoryProduct::query()
                ->where('is_borrowable', true)
                ->count(),
            'active_borrowings' => InventoryBorrowing::query()->active()->count(),
            'overdue_borrowings' => InventoryBorrowing::query()->overdue()->count(),
            'ledger_entries' => InventoryProductHistory::query()->count(),
            'ledger_today' => InventoryProductHistory::query()->whereDate('recorded_at', now()->toDateString())->count(),
        ];

        $recentItems = InventoryProduct::query()
            ->orderByDesc('updated_at')
            ->take(5)
            ->get()
            ->map(fn (InventoryProduct $product): array => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'item_type' => $product->item_type instanceof InventoryItemType
                    ? $product->item_type->value
                    : (string) $product->item_type,
                'stock_quantity' => $product->stock_quantity,
                'defective_quantity' => $product->defective_quantity,
                'total_quantity' => $product->stock_quantity + $product->defective_quantity,
                'unit' => $product->unit,
                'location' => $product->locationLabel(),
                'image_url' => $this->resolvePrimaryImageUrl($product),
                'updated_at' => format_timestamp($product->updated_at),
            ]);

        $recentBorrowings = InventoryBorrowing::query()
            ->with('product')
            ->orderByDesc('borrowed_date')
            ->take(5)
            ->get()
            ->map(fn (InventoryBorrowing $record): array => [
                'id' => $record->id,
                'product' => [
                    'id' => $record->product?->id,
                    'name' => $record->product?->name,
                ],
                'borrower' => [
                    'name' => $record->borrower_name,
                    'department' => $record->department,
                ],
                'status' => $record->status,
                'quantity_borrowed' => $record->quantity_borrowed,
                'quantity_returned' => $record->quantity_returned,
                'borrowed_date' => format_timestamp($record->borrowed_date),
                'expected_return_date' => format_timestamp($record->expected_return_date),
                'is_overdue' => $record->isOverdue(),
            ]);

        $recentTransactions = InventoryProductHistory::query()
            ->with(['product:id,name,sku,unit,is_consumable'])
            ->latest('recorded_at')
            ->take(8)
            ->get()
            ->map(fn (InventoryProductHistory $entry): array => [
                'id' => $entry->id,
                'event_type' => $entry->event_type,
                'notes' => $entry->notes,
                'recorded_at' => format_timestamp($entry->recorded_at),
                'reference_type' => $entry->reference_type,
                'product' => [
                    'id' => $entry->product?->id,
                    'name' => $entry->product?->name,
                    'sku' => $entry->product?->sku,
                    'unit' => $entry->product?->unit,
                    'is_consumable' => $entry->product?->is_consumable ?? false,
                ],
                'movement' => [
                    'good_delta' => is_array($entry->after) ? ($entry->after['good_delta'] ?? null) : null,
                    'defective_delta' => is_array($entry->after) ? ($entry->after['defective_delta'] ?? null) : null,
                ],
            ]);

        return Inertia::render('administrators/inventory/index', [
            'user' => $this->getUserProps(),
            'stats' => $stats,
            'recent' => [
                'items' => $recentItems,
                'borrowings' => $recentBorrowings,
                'transactions' => $recentTransactions,
            ],
            'flash' => session('flash'),
        ]);
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

    private function resolvePrimaryImageUrl(InventoryProduct $product): ?string
    {
        if (! is_array($product->images) || $product->images === []) {
            return null;
        }

        $firstPath = collect($product->images)
            ->first(fn (mixed $path): bool => is_string($path) && $path !== '');

        if (! is_string($firstPath) || $firstPath === '') {
            return null;
        }

        return Storage::disk('public')->url($firstPath);
    }
}
