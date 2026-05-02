<?php

declare(strict_types=1);

namespace Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Inventory\Enums\InventoryBorrowingStatus;
use Modules\Inventory\Enums\InventoryHistoryEventType;
use Modules\Inventory\Http\Requests\Administrators\InventoryBorrowingRequest;
use Modules\Inventory\Models\InventoryBorrowing;
use Modules\Inventory\Models\InventoryProduct;
use Modules\Inventory\Models\InventoryProductHistory;

final class AdministratorInventoryBorrowingController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $records = InventoryBorrowing::query()
            ->with('product')
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested->whereHas('product', fn ($productQuery) => $productQuery->where('name', 'ilike', "%{$term}%"))
                        ->orWhere('borrower_name', 'ilike', "%{$term}%")
                        ->orWhere('borrower_email', 'ilike', "%{$term}%");
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($query) use ($status): void {
                if ($status === InventoryBorrowingStatus::Overdue->value) {
                    $query->overdue();

                    return;
                }

                $query->where('status', $status);
            })
            ->orderByDesc('borrowed_date')
            ->limit(50)
            ->get()
            ->map(fn (InventoryBorrowing $record): array => [
                'id' => $record->id,
                'product' => [
                    'id' => $record->product?->id,
                    'name' => $record->product?->name,
                ],
                'borrower' => [
                    'name' => $record->borrower_name,
                    'email' => $record->borrower_email,
                    'department' => $record->department,
                ],
                'quantity_borrowed' => $record->quantity_borrowed,
                'quantity_returned' => $record->quantity_returned,
                'quantity_returned_good' => $record->quantity_returned_good,
                'quantity_returned_defective' => $record->quantity_returned_defective,
                'borrowed_date' => format_timestamp($record->borrowed_date),
                'expected_return_date' => format_timestamp($record->expected_return_date),
                'actual_return_date' => format_timestamp($record->actual_return_date),
                'status' => $record->status,
                'is_overdue' => $record->isOverdue(),
            ]);

        $stats = [
            'total' => InventoryBorrowing::count(),
            'borrowed' => InventoryBorrowing::query()->where('status', InventoryBorrowingStatus::Borrowed->value)->count(),
            'returned' => InventoryBorrowing::query()->where('status', InventoryBorrowingStatus::Returned->value)->count(),
            'overdue' => InventoryBorrowing::query()->overdue()->count(),
            'lost' => InventoryBorrowing::query()->where('status', InventoryBorrowingStatus::Lost->value)->count(),
        ];

        return Inertia::render('administrators/inventory/borrowings/index', [
            'user' => $this->getUserProps(),
            'records' => $records,
            'stats' => $stats,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'status' => is_string($status) ? $status : null,
            ],
            'options' => [
                'statuses' => [
                    ['value' => 'all', 'label' => 'All records'],
                    ['value' => InventoryBorrowingStatus::Borrowed->value, 'label' => 'Borrowed'],
                    ['value' => InventoryBorrowingStatus::Returned->value, 'label' => 'Returned'],
                    ['value' => InventoryBorrowingStatus::Overdue->value, 'label' => 'Overdue'],
                    ['value' => InventoryBorrowingStatus::Lost->value, 'label' => 'Lost'],
                ],
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/inventory/borrowings/edit', [
            'user' => $this->getUserProps(),
            'record' => null,
            'options' => $this->getBorrowOptions(),
        ]);
    }

    public function store(InventoryBorrowingRequest $request): RedirectResponse
    {
        $validated = $this->normalizeBorrowingData($request->validated());
        $product = InventoryProduct::findOrFail($validated['product_id']);
        [$goodImpact, $defectiveImpact] = $this->stockImpact(
            $validated['status'],
            (int) $validated['quantity_borrowed'],
            (int) $validated['quantity_returned_good'],
            (int) $validated['quantity_returned_defective']
        );

        if ($product->track_stock && $goodImpact < 0 && $product->stock_quantity < abs($goodImpact)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Not enough stock available for this item.',
            ]);
        }

        DB::transaction(function () use ($validated, $product, $goodImpact, $defectiveImpact): void {
            $borrowing = InventoryBorrowing::create($validated);

            $this->applyStockDelta(
                $product,
                $goodImpact,
                $defectiveImpact,
                InventoryHistoryEventType::BorrowingCreated->value,
                'Borrowing recorded',
                InventoryBorrowing::class,
                $borrowing->id
            );
        });

        return redirect()
            ->route('administrators.inventory.borrowings.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Borrow record created successfully.',
            ]);
    }

    public function edit(InventoryBorrowing $inventoryBorrowing): Response
    {
        return Inertia::render('administrators/inventory/borrowings/edit', [
            'user' => $this->getUserProps(),
            'record' => [
                'id' => $inventoryBorrowing->id,
                'product_id' => $inventoryBorrowing->product_id,
                'quantity_borrowed' => $inventoryBorrowing->quantity_borrowed,
                'borrower_name' => $inventoryBorrowing->borrower_name,
                'borrower_email' => $inventoryBorrowing->borrower_email,
                'borrower_phone' => $inventoryBorrowing->borrower_phone,
                'department' => $inventoryBorrowing->department,
                'purpose' => $inventoryBorrowing->purpose,
                'status' => $inventoryBorrowing->status,
                'borrowed_date' => $inventoryBorrowing->borrowed_date?->toDateTimeString(),
                'expected_return_date' => $inventoryBorrowing->expected_return_date?->toDateTimeString(),
                'actual_return_date' => $inventoryBorrowing->actual_return_date?->toDateTimeString(),
                'quantity_returned' => $inventoryBorrowing->quantity_returned,
                'quantity_returned_good' => $inventoryBorrowing->quantity_returned_good,
                'quantity_returned_defective' => $inventoryBorrowing->quantity_returned_defective,
                'return_notes' => $inventoryBorrowing->return_notes,
                'issued_by' => $inventoryBorrowing->issued_by,
                'returned_to' => $inventoryBorrowing->returned_to,
            ],
            'options' => $this->getBorrowOptions(),
        ]);
    }

    public function update(
        InventoryBorrowingRequest $request,
        InventoryBorrowing $inventoryBorrowing
    ): RedirectResponse {
        $validated = $this->normalizeBorrowingData($request->validated());
        $originalProduct = $inventoryBorrowing->product;
        [$originalGoodImpact, $originalDefectiveImpact] = $this->stockImpact(
            $inventoryBorrowing->status,
            $inventoryBorrowing->quantity_borrowed,
            $inventoryBorrowing->quantity_returned_good,
            $inventoryBorrowing->quantity_returned_defective
        );
        [$newGoodImpact, $newDefectiveImpact] = $this->stockImpact(
            $validated['status'],
            (int) $validated['quantity_borrowed'],
            (int) $validated['quantity_returned_good'],
            (int) $validated['quantity_returned_defective']
        );

        $newProduct = $inventoryBorrowing->product_id === $validated['product_id']
            ? $originalProduct
            : InventoryProduct::findOrFail($validated['product_id']);

        if ($newProduct && $newProduct->track_stock && $newGoodImpact < 0) {
            $availableStock = $newProduct->stock_quantity;

            if ($originalProduct && $newProduct->is($originalProduct)) {
                $availableStock += abs($originalGoodImpact);
            }

            if ($availableStock < abs($newGoodImpact)) {
                return back()->with('flash', [
                    'type' => 'error',
                    'message' => 'Not enough stock available for this update.',
                ]);
            }
        }

        DB::transaction(function () use (
            $inventoryBorrowing,
            $validated,
            $originalProduct,
            $newProduct,
            $originalGoodImpact,
            $originalDefectiveImpact,
            $newGoodImpact,
            $newDefectiveImpact
        ): void {
            $inventoryBorrowing->update($validated);

            if ($originalProduct && $newProduct && $originalProduct->is($newProduct)) {
                $this->applyStockDelta(
                    $originalProduct,
                    $newGoodImpact - $originalGoodImpact,
                    $newDefectiveImpact - $originalDefectiveImpact,
                    InventoryHistoryEventType::BorrowingUpdated->value,
                    'Borrowing record updated',
                    InventoryBorrowing::class,
                    $inventoryBorrowing->id
                );

                return;
            }

            if ($originalProduct) {
                $this->applyStockDelta(
                    $originalProduct,
                    -$originalGoodImpact,
                    -$originalDefectiveImpact,
                    InventoryHistoryEventType::BorrowingReverted->value,
                    'Borrowing impact reverted due to reassignment',
                    InventoryBorrowing::class,
                    $inventoryBorrowing->id
                );
            }

            if ($newProduct) {
                $this->applyStockDelta(
                    $newProduct,
                    $newGoodImpact,
                    $newDefectiveImpact,
                    InventoryHistoryEventType::BorrowingReassigned->value,
                    'Borrowing reassigned to a different item',
                    InventoryBorrowing::class,
                    $inventoryBorrowing->id
                );
            }
        });

        return redirect()
            ->route('administrators.inventory.borrowings.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Borrow record updated.',
            ]);
    }

    public function destroy(InventoryBorrowing $inventoryBorrowing): RedirectResponse
    {
        $product = $inventoryBorrowing->product;
        [$goodImpact, $defectiveImpact] = $this->stockImpact(
            $inventoryBorrowing->status,
            $inventoryBorrowing->quantity_borrowed,
            $inventoryBorrowing->quantity_returned_good,
            $inventoryBorrowing->quantity_returned_defective
        );

        DB::transaction(function () use ($inventoryBorrowing, $product, $goodImpact, $defectiveImpact): void {
            $inventoryBorrowing->delete();

            if ($product) {
                $this->applyStockDelta(
                    $product,
                    -$goodImpact,
                    -$defectiveImpact,
                    InventoryHistoryEventType::BorrowingDeleted->value,
                    'Borrowing record deleted',
                    InventoryBorrowing::class,
                    $inventoryBorrowing->id
                );
            }
        });

        return redirect()
            ->route('administrators.inventory.borrowings.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Borrow record removed.',
            ]);
    }

    private function normalizeBorrowingData(array $validated): array
    {
        $validated['quantity_returned_good'] = (int) ($validated['quantity_returned_good'] ?? $validated['quantity_returned'] ?? 0);
        $validated['quantity_returned_defective'] = (int) ($validated['quantity_returned_defective'] ?? 0);
        $validated['quantity_returned'] = $validated['quantity_returned_good'] + $validated['quantity_returned_defective'];

        if ($validated['status'] === InventoryBorrowingStatus::Returned->value) {
            if ($validated['quantity_returned'] === 0) {
                $validated['quantity_returned_good'] = (int) $validated['quantity_borrowed'];
                $validated['quantity_returned'] = (int) $validated['quantity_borrowed'];
            }

            $validated['quantity_returned'] = min((int) $validated['quantity_borrowed'], $validated['quantity_returned']);
            $validated['actual_return_date'] ??= now()->toDateTimeString();
        } else {
            $validated['actual_return_date'] = null;
            $validated['quantity_returned_good'] = 0;
            $validated['quantity_returned_defective'] = 0;
            $validated['quantity_returned'] = 0;
        }

        if ($validated['status'] !== InventoryBorrowingStatus::Returned->value) {
            $validated['returned_to'] = null;
        }

        return $validated;
    }

    /**
     * @return array{0:int,1:int}
     */
    private function stockImpact(
        string $status,
        int $quantityBorrowed,
        int $quantityReturnedGood,
        int $quantityReturnedDefective
    ): array {
        if ($status !== InventoryBorrowingStatus::Returned->value) {
            return [-$quantityBorrowed, 0];
        }

        return [$quantityReturnedGood - $quantityBorrowed, $quantityReturnedDefective];
    }

    private function applyStockDelta(
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

        $before = [
            'good_quantity' => $product->stock_quantity,
            'defective_quantity' => $product->defective_quantity,
            'location' => $product->locationLabel(),
        ];

        $product->stock_quantity = max(0, $product->stock_quantity + $goodDelta);
        $product->defective_quantity = max(0, $product->defective_quantity + $defectiveDelta);
        $product->save();

        InventoryProductHistory::query()->create([
            'product_id' => $product->id,
            'event_type' => $eventType,
            'before' => $before,
            'after' => [
                'good_quantity' => $product->stock_quantity,
                'defective_quantity' => $product->defective_quantity,
                'location' => $product->locationLabel(),
            ],
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'recorded_by' => request()->user()?->id,
            'recorded_at' => now(),
        ]);
    }

    private function getBorrowOptions(): array
    {
        return [
            'products' => InventoryProduct::query()
                ->borrowable()
                ->where('is_active', true)
                ->orderBy('name')
                ->get()
                ->map(fn (InventoryProduct $product): array => [
                    'value' => $product->id,
                    'label' => $product->name,
                    'available' => $product->track_stock ? $product->stock_quantity : null,
                    'unit' => $product->unit,
                ])
                ->values()
                ->all(),
            'staff' => User::query()
                ->orderBy('name')
                ->limit(150)
                ->get()
                ->map(fn (User $user): array => [
                    'value' => $user->id,
                    'label' => $user->name,
                    'email' => $user->email,
                ])
                ->values()
                ->all(),
            'statuses' => [
                ['value' => InventoryBorrowingStatus::Borrowed->value, 'label' => 'Borrowed'],
                ['value' => InventoryBorrowingStatus::Returned->value, 'label' => 'Returned'],
                ['value' => InventoryBorrowingStatus::Overdue->value, 'label' => 'Overdue'],
                ['value' => InventoryBorrowingStatus::Lost->value, 'label' => 'Lost'],
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
}
