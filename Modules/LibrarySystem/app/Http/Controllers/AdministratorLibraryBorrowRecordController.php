<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Http\Requests\Administrators\LibraryBorrowRecordRequest;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Services\LibraryBorrowStockService;

final class AdministratorLibraryBorrowRecordController extends Controller
{
    public function __construct(
        private readonly LibraryBorrowStockService $stockService,
    ) {}

    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $records = BorrowRecord::query()
            ->with(['book', 'user'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested->whereHas('book', fn ($bookQuery) => $bookQuery->where('title', 'ilike', "%{$term}%"))
                        ->orWhereHas('user', fn ($userQuery) => $userQuery
                            ->where('name', 'ilike', "%{$term}%")
                            ->orWhere('email', 'ilike', "%{$term}%"));
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', function ($query) use ($status): void {
                if ($status === 'overdue') {
                    $query->where('status', 'borrowed')->where('due_date', '<', now());

                    return;
                }

                $query->where('status', $status);
            })
            ->orderByDesc('borrowed_at')
            ->limit(50)
            ->get()
            ->map(fn (BorrowRecord $record): array => [
                'id' => $record->id,
                'book' => [
                    'id' => $record->book?->id,
                    'title' => $record->book?->title,
                ],
                'borrower' => [
                    'name' => $record->user?->name,
                    'email' => $record->user?->email,
                ],
                'borrowed_at' => format_timestamp($record->borrowed_at),
                'due_date' => format_timestamp($record->due_date),
                'returned_at' => format_timestamp($record->returned_at),
                'status' => $record->status,
                'fine_amount' => $record->fine_amount,
                'notes' => $record->notes,
                'is_overdue' => $record->isOverdue(),
                'days_overdue' => $record->days_overdue,
            ]);

        $stats = [
            'total' => BorrowRecord::count(),
            'borrowed' => BorrowRecord::query()->where('status', 'borrowed')->count(),
            'returned' => BorrowRecord::query()->where('status', 'returned')->count(),
            'lost' => BorrowRecord::query()->where('status', 'lost')->count(),
            'overdue' => BorrowRecord::query()
                ->where('status', 'borrowed')
                ->where('due_date', '<', now())
                ->count(),
        ];

        return Inertia::render('administrators/library/borrow-records/index', [
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
                    ['value' => 'lost', 'label' => 'Lost'],
                    ['value' => 'overdue', 'label' => 'Overdue'],
                ],
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/library/borrow-records/edit', [
            'user' => $this->getUserProps(),
            'record' => null,
            'options' => $this->getBorrowOptions(),
        ]);
    }

    public function store(LibraryBorrowRecordRequest $request): RedirectResponse
    {
        $validated = $this->normalizeBorrowRecordData($request->validated());
        $book = Book::findOrFail($validated['book_id']);

        if (! $this->stockService->canBorrow($book, $validated['status'])) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'This book has no available copies left.',
            ]);
        }

        DB::transaction(function () use ($validated): void {
            $record = BorrowRecord::create($validated);
            $this->stockService->recordCreated($record);
        });

        return redirect()
            ->route('administrators.library.borrow-records.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Borrow record created successfully.',
            ]);
    }

    public function edit(BorrowRecord $borrowRecord): Response
    {
        return Inertia::render('administrators/library/borrow-records/edit', [
            'user' => $this->getUserProps(),
            'record' => [
                'id' => $borrowRecord->id,
                'book_id' => $borrowRecord->book_id,
                'user_id' => $borrowRecord->user_id,
                'borrowed_at' => $borrowRecord->borrowed_at?->toDateTimeString(),
                'due_date' => $borrowRecord->due_date?->toDateTimeString(),
                'returned_at' => $borrowRecord->returned_at?->toDateTimeString(),
                'status' => $borrowRecord->status,
                'fine_amount' => $borrowRecord->fine_amount,
                'notes' => $borrowRecord->notes,
            ],
            'options' => $this->getBorrowOptions(),
        ]);
    }

    public function update(LibraryBorrowRecordRequest $request, BorrowRecord $borrowRecord): RedirectResponse
    {
        $validated = $this->normalizeBorrowRecordData($request->validated());
        $originalBookId = (int) $borrowRecord->book_id;
        $originalStatus = (string) $borrowRecord->status;

        $newBook = $borrowRecord->book_id === $validated['book_id']
            ? $borrowRecord->book
            : Book::findOrFail($validated['book_id']);

        if (! $newBook || ! $this->stockService->canUpdateBorrow($borrowRecord, $newBook, $validated['status'])) {
            return back()->with('flash', [
                'type' => 'error',
                'message' => 'This book has no available copies left.',
            ]);
        }

        DB::transaction(function () use ($borrowRecord, $validated, $originalBookId, $originalStatus): void {
            $borrowRecord->update($validated);
            $this->stockService->recordUpdated($borrowRecord, $originalBookId, $originalStatus);
        });

        return redirect()
            ->route('administrators.library.borrow-records.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Borrow record updated.',
            ]);
    }

    public function destroy(BorrowRecord $borrowRecord): RedirectResponse
    {
        DB::transaction(function () use ($borrowRecord): void {
            $borrowRecord->delete();
            $this->stockService->recordDeleted($borrowRecord);
        });

        return redirect()
            ->route('administrators.library.borrow-records.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Borrow record deleted.',
            ]);
    }

    private function normalizeBorrowRecordData(array $validated): array
    {
        if ($validated['status'] === 'returned') {
            $validated['returned_at'] ??= now()->toDateTimeString();
        } else {
            $validated['returned_at'] = null;
        }

        return $validated;
    }

    private function getBorrowOptions(): array
    {
        return [
            'books' => Book::query()
                ->orderBy('title')
                ->get()
                ->map(fn (Book $book): array => [
                    'value' => $book->id,
                    'label' => $book->title,
                    'available_copies' => $book->available_copies,
                ])
                ->values()
                ->all(),
            'users' => User::query()
                ->orderBy('name')
                ->limit(100)
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
