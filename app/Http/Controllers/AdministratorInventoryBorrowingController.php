<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\InventoryBorrowingRequest;
use App\Models\InventoryBorrowing;
use App\Models\InventoryProduct;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

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
                if ($status === 'overdue') {
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
                'borrowed_date' => format_timestamp($record->borrowed_date),
                'expected_return_date' => format_timestamp($record->expected_return_date),
                'actual_return_date' => format_timestamp($record->actual_return_date),
                'status' => $record->status,
                'is_overdue' => $record->isOverdue(),
            ]);

        $stats = [
            'total' => InventoryBorrowing::count(),
            'borrowed' => InventoryBorrowing::query()->where('status', 'borrowed')->count(),
            'returned' => InventoryBorrowing::query()->where('status', 'returned')->count(),
            'overdue' => InventoryBorrowing::query()->overdue()->count(),
            'lost' => InventoryBorrowing::query()->where('status', 'lost')->count(),
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
                    ['value' => 'borrowed', 'label' => 'Borrowed'],
                    ['value' => 'returned', 'label' => 'Returned'],
                    ['value' => 'overdue', 'label' => 'Overdue'],
                    ['value' => 'lost', 'label' => 'Lost'],
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
        $impact = $this->stockImpact(
            $validated['status'],
            (int) $validated['quantity_borrowed'],
            (int) $validated['quantity_returned']
        );

        if ($product->track_stock && $impact < 0 && $product->stock_quantity < abs($impact)) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'Not enough stock available for this item.',
            ]);
        }

        DB::transaction(function () use ($validated, $product, $impact): void {
            InventoryBorrowing::create($validated);

            $this->applyStockDelta($product, $impact);
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
        $originalImpact = $this->stockImpact(
            $inventoryBorrowing->status,
            $inventoryBorrowing->quantity_borrowed,
            $inventoryBorrowing->quantity_returned
        );
        $newImpact = $this->stockImpact(
            $validated['status'],
            (int) $validated['quantity_borrowed'],
            (int) $validated['quantity_returned']
        );

        $newProduct = $inventoryBorrowing->product_id === $validated['product_id']
            ? $originalProduct
            : InventoryProduct::findOrFail($validated['product_id']);

        if ($newProduct && $newProduct->track_stock && $newImpact < 0) {
            $availableStock = $newProduct->stock_quantity;

            if ($originalProduct && $newProduct->is($originalProduct)) {
                $availableStock += abs($originalImpact);
            }

            if ($availableStock < abs($newImpact)) {
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
            $originalImpact,
            $newImpact
        ): void {
            $inventoryBorrowing->update($validated);

            if ($originalProduct && $newProduct && $originalProduct->is($newProduct)) {
                $delta = $newImpact - $originalImpact;
                $this->applyStockDelta($originalProduct, $delta);

                return;
            }

            if ($originalProduct) {
                $this->applyStockDelta($originalProduct, -$originalImpact);
            }

            if ($newProduct) {
                $this->applyStockDelta($newProduct, $newImpact);
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
        $impact = $this->stockImpact(
            $inventoryBorrowing->status,
            $inventoryBorrowing->quantity_borrowed,
            $inventoryBorrowing->quantity_returned
        );

        DB::transaction(function () use ($inventoryBorrowing, $product, $impact): void {
            $inventoryBorrowing->delete();

            if ($product) {
                $this->applyStockDelta($product, -$impact);
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
        $validated['quantity_returned'] = (int) ($validated['quantity_returned'] ?? 0);

        if ($validated['status'] === 'returned') {
            $validated['quantity_returned'] = max(
                $validated['quantity_returned'],
                (int) $validated['quantity_borrowed']
            );
            $validated['actual_return_date'] ??= now()->toDateTimeString();
        } else {
            $validated['actual_return_date'] = null;
        }

        if ($validated['status'] !== 'returned') {
            $validated['returned_to'] = null;
        }

        return $validated;
    }

    private function stockImpact(string $status, int $quantityBorrowed, int $quantityReturned): int
    {
        if ($status === 'returned') {
            return 0;
        }

        $netBorrowed = max(0, $quantityBorrowed - $quantityReturned);

        return -$netBorrowed;
    }

    private function applyStockDelta(InventoryProduct $product, int $delta): void
    {
        if (! $product->track_stock || $delta === 0) {
            return;
        }

        $product->stock_quantity = max(0, $product->stock_quantity + $delta);
        $product->save();
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
                ['value' => 'borrowed', 'label' => 'Borrowed'],
                ['value' => 'returned', 'label' => 'Returned'],
                ['value' => 'overdue', 'label' => 'Overdue'],
                ['value' => 'lost', 'label' => 'Lost'],
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
