<?php

declare(strict_types=1);

namespace Modules\Inventory\Services;

use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryProductHistory;

final class InventoryLedgerService
{
    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public function record(
        InventoryProduct $product,
        string $eventType,
        ?array $before,
        array $after,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void {
        InventoryProductHistory::query()->create([
            'product_id' => $product->id,
            'event_type' => $eventType,
            'before' => $before,
            'after' => $after,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'recorded_by' => request()->user()?->id,
            'recorded_at' => now(),
        ]);
    }

    public function applyStockDelta(
        InventoryProduct $product,
        int $goodDelta,
        int $defectiveDelta,
        string $eventType,
        ?string $notes = null,
        ?string $referenceType = null,
        ?int $referenceId = null
    ): void {
        if (! $product->track_stock || ($goodDelta === 0 && $defectiveDelta === 0)) {
            return;
        }

        $before = $this->snapshot($product);

        $product->stock_quantity = max(0, $product->stock_quantity + $goodDelta);
        $product->defective_quantity = max(0, $product->defective_quantity + $defectiveDelta);
        $product->save();

        $after = $this->snapshot($product);
        $after['good_delta'] = $goodDelta;
        $after['defective_delta'] = $defectiveDelta;

        $this->record(
            product: $product,
            eventType: $eventType,
            before: $before,
            after: $after,
            notes: $notes,
            referenceType: $referenceType,
            referenceId: $referenceId,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(InventoryProduct $product): array
    {
        return [
            'name' => $product->name,
            'sku' => $product->sku,
            'good_quantity' => $product->stock_quantity,
            'defective_quantity' => $product->defective_quantity,
            'is_consumable' => $product->is_consumable,
            'location_building' => $product->location_building,
            'location_floor' => $product->location_floor,
            'location_area' => $product->location_area,
            'location_label' => $product->locationLabel(),
        ];
    }
}
