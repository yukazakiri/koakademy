<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryProductHistory;

final class AdministratorInventoryLedgerController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $eventType = $request->input('event_type');
        $referenceType = $request->input('reference_type');

        $entries = InventoryProductHistory::query()
            ->with(['product:id,name,sku,unit,is_consumable', 'recordedBy:id,name'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested
                        ->where('event_type', 'ilike', "%{$term}%")
                        ->orWhere('notes', 'ilike', "%{$term}%")
                        ->orWhereHas('product', function ($productQuery) use ($term): void {
                            $productQuery
                                ->where('name', 'ilike', "%{$term}%")
                                ->orWhere('sku', 'ilike', "%{$term}%");
                        });
                });
            })
            ->when(is_string($eventType) && $eventType !== '' && $eventType !== 'all', fn ($query) => $query->where('event_type', $eventType))
            ->when(is_string($referenceType) && $referenceType !== '' && $referenceType !== 'all', fn ($query) => $query->where('reference_type', $referenceType))
            ->latest('recorded_at')
            ->limit(120)
            ->get()
            ->map(function (InventoryProductHistory $entry): array {
                $before = is_array($entry->before) ? $entry->before : [];
                $after = is_array($entry->after) ? $entry->after : [];

                return [
                    'id' => $entry->id,
                    'event_type' => $entry->event_type,
                    'reference_type' => $entry->reference_type,
                    'reference_id' => $entry->reference_id,
                    'notes' => $entry->notes,
                    'recorded_at' => format_timestamp($entry->recorded_at),
                    'recorded_by' => $entry->recordedBy?->name,
                    'product' => [
                        'id' => $entry->product?->id,
                        'name' => $entry->product?->name,
                        'sku' => $entry->product?->sku,
                        'unit' => $entry->product?->unit,
                        'is_consumable' => $entry->product?->is_consumable ?? false,
                    ],
                    'movement' => [
                        'good_before' => $before['good_quantity'] ?? null,
                        'good_after' => $after['good_quantity'] ?? null,
                        'good_delta' => $after['good_delta'] ?? null,
                        'defective_before' => $before['defective_quantity'] ?? null,
                        'defective_after' => $after['defective_quantity'] ?? null,
                        'defective_delta' => $after['defective_delta'] ?? null,
                        'location_from' => $before['location_label'] ?? null,
                        'location_to' => $after['location_label'] ?? null,
                        'consumable_before' => $before['is_consumable'] ?? null,
                        'consumable_after' => $after['is_consumable'] ?? null,
                    ],
                ];
            })
            ->values()
            ->all();

        $eventTypes = InventoryProductHistory::query()
            ->select('event_type')
            ->distinct()
            ->orderBy('event_type')
            ->pluck('event_type')
            ->values()
            ->all();

        $referenceTypes = InventoryProductHistory::query()
            ->whereNotNull('reference_type')
            ->select('reference_type')
            ->distinct()
            ->orderBy('reference_type')
            ->pluck('reference_type')
            ->values()
            ->all();

        $stats = [
            'total_entries' => InventoryProductHistory::query()->count(),
            'today_entries' => InventoryProductHistory::query()->whereDate('recorded_at', now()->toDateString())->count(),
            'products_with_logs' => InventoryProduct::query()->whereHas('histories')->count(),
            'consumable_products' => InventoryProduct::query()->where('is_consumable', true)->count(),
        ];

        return Inertia::render('administrators/inventory/ledger/index', [
            'user' => $this->getUserProps(),
            'entries' => $entries,
            'stats' => $stats,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'event_type' => is_string($eventType) ? $eventType : null,
                'reference_type' => is_string($referenceType) ? $referenceType : null,
            ],
            'options' => [
                'event_types' => array_merge([['value' => 'all', 'label' => 'All events']], array_map(
                    fn (string $type): array => ['value' => $type, 'label' => str_replace('_', ' ', $type)],
                    $eventTypes
                )),
                'reference_types' => array_merge([['value' => 'all', 'label' => 'All references']], array_map(
                    fn (string $type): array => ['value' => $type, 'label' => class_basename($type)],
                    $referenceTypes
                )),
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
}
