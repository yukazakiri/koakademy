<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Enums\DigitalRightsBasis;
use Modules\LibrarySystem\Http\Requests\Administrators\BulkDeleteBookRequest;
use Modules\LibrarySystem\Http\Requests\Administrators\BulkForceDeleteBookRequest;
use Modules\LibrarySystem\Http\Requests\Administrators\LibraryBookIdentifierSuggestionsRequest;
use Modules\LibrarySystem\Http\Requests\Administrators\LibraryBookRequest;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;
use Throwable;

final class AdministratorLibraryBookController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');
        $sort = $request->string('sort', 'created_at')->toString();
        $direction = $request->string('direction', 'desc')->toString();
        $perPage = $request->integer('per_page', 20);

        $allowedSorts = ['title', 'publication_year', 'created_at'];
        $sort = in_array($sort, $allowedSorts, true) ? $sort : 'created_at';
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        $perPage = in_array($perPage, [10, 20, 50, 100], true) ? $perPage : 20;

        $booksQuery = Book::query()
            ->with(['author', 'category'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_strtolower(mb_trim($search));
                $pattern = "%{$term}%";
                $query->where(function ($nested) use ($pattern): void {
                    $nested->whereRaw('LOWER(title) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(isbn) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(call_number) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(accession_number) LIKE ?', [$pattern])
                        ->orWhereHas('author', fn ($authorQuery) => $authorQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]))
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$pattern]));
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status));

        $booksQuery->orderBy($sort, $direction)
            ->orderBy('id', $direction);

        /** @var \Illuminate\Contracts\Pagination\LengthAwarePaginator $books */
        $books = $booksQuery->paginate($perPage)
            ->withQueryString()
            ->through(fn (Book $book): array => [
                'id' => $book->id,
                'title' => $book->title,
                'isbn' => $book->isbn,
                'call_number' => $book->call_number,
                'accession_number' => $book->accession_number,
                'author' => [
                    'id' => $book->author?->id,
                    'name' => $book->author?->name,
                ],
                'category' => [
                    'id' => $book->category?->id,
                    'name' => $book->category?->name,
                    'color' => $book->category?->color,
                ],
                'status' => $book->status,
                'available_copies' => $book->available_copies,
                'total_copies' => $book->total_copies,
                'publication_year' => $book->publication_year,
                'location' => $book->location,
                'cover_image_url' => $this->resolveCoverImageUrl($book),
                'updated_at' => format_timestamp($book->updated_at),
                'created_at' => format_timestamp($book->created_at),
            ]);

        $stats = [
            'total_books' => Book::count(),
            'available_copies' => (int) Book::sum('available_copies'),
            'borrowed_books' => Book::query()->where('status', 'borrowed')->count(),
        ];

        return Inertia::render('administrators/library/books/index', [
            'user' => $this->getUserProps(),
            'books' => $books,
            'stats' => $stats,
            'filters' => [
                'search' => is_string($search) ? $search : null,
                'status' => is_string($status) ? $status : null,
                'sort' => $sort,
                'direction' => $direction,
                'per_page' => $perPage,
            ],
            'options' => [
                'statuses' => [
                    ['value' => 'all', 'label' => 'All statuses'],
                    ['value' => 'available', 'label' => 'Available'],
                    ['value' => 'borrowed', 'label' => 'Borrowed'],
                    ['value' => 'maintenance', 'label' => 'Maintenance'],
                ],
                'per_page' => [
                    ['value' => 10, 'label' => '10'],
                    ['value' => 20, 'label' => '20'],
                    ['value' => 50, 'label' => '50'],
                    ['value' => 100, 'label' => '100'],
                ],
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/library/books/edit', [
            'user' => $this->getUserProps(),
            'book' => null,
            'options' => $this->getBookOptions(),
        ]);
    }

    public function fieldValues(LibraryBookIdentifierSuggestionsRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $field = match ($validated['field']) {
            'isbn' => 'isbn',
            'call_number' => 'call_number',
        };
        $search = mb_trim((string) ($validated['search'] ?? ''));

        $values = Book::query()
            ->select($field)
            ->whereNotNull($field)
            ->where($field, '!=', '')
            ->when($search !== '', fn ($query) => $query->whereLike($field, "%{$search}%"))
            ->distinct()
            ->orderBy($field)
            ->limit(25)
            ->pluck($field)
            ->values();

        return response()->json([
            'values' => $values,
        ]);
    }

    public function store(LibraryBookRequest $request): RedirectResponse
    {
        $validated = $this->normalizeBookData($request->validated());

        $coverImage = $request->file('cover_image_upload');
        if ($coverImage instanceof UploadedFile) {
            $validated['cover_image_path'] = $this->storeCoverImage($coverImage);
        }

        unset($validated['cover_image_upload']);

        Book::create($validated);

        return redirect()
            ->route('administrators.library.books.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Book added to the catalog.',
            ]);
    }

    public function edit(Book $book): Response
    {
        $book->load('digitalEdition');
        $edition = $book->digitalEdition;

        return Inertia::render('administrators/library/books/edit', [
            'user' => $this->getUserProps(),
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'isbn' => $book->isbn,
                'call_number' => $book->call_number,
                'accession_number' => $book->accession_number,
                'author_id' => $book->author_id,
                'category_id' => $book->category_id,
                'publisher' => $book->publisher,
                'publication_year' => $book->publication_year,
                'pages' => $book->pages,
                'description' => $book->description,
                'cover_image' => $book->cover_image,
                'cover_image_path' => $book->cover_image_path,
                'cover_image_url' => $this->resolveCoverImageUrl($book),
                'total_copies' => $book->total_copies,
                'available_copies' => $book->available_copies,
                'location' => $book->location,
                'status' => $book->status,
                'digital_edition' => $edition ? [
                    'id' => $edition->id,
                    'original_name' => $edition->original_name,
                    'mime_type' => $edition->mime_type,
                    'size_bytes' => $edition->size_bytes,
                    'status' => $edition->status->value,
                    'downloads_allowed' => $edition->downloads_allowed,
                    'rights_basis' => $edition->rights_basis?->value,
                    'rights_holder' => $edition->rights_holder,
                    'license_url' => $edition->license_url,
                    'rights_notes' => $edition->rights_notes,
                    'rights_expires_at' => $edition->rights_expires_at?->format('Y-m-d'),
                    'uploaded_at' => $edition->uploaded_at?->toIso8601String(),
                    'published_at' => $edition->published_at?->toIso8601String(),
                ] : null,
                'can_manage_digital_edition' => request()->user()?->can('manageDigitalEdition', $book) ?? false,
            ],
            'options' => $this->getBookOptions(),
        ]);
    }

    public function update(LibraryBookRequest $request, Book $book): RedirectResponse
    {
        $validated = $this->normalizeBookData($request->validated());

        $coverImage = $request->file('cover_image_upload');
        if ($coverImage instanceof UploadedFile) {
            $validated['cover_image_path'] = $this->storeCoverImage($coverImage, $book->cover_image_path);
        }

        unset($validated['cover_image_upload']);

        $book->update($validated);

        return redirect()
            ->route('administrators.library.books.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Book details updated.',
            ]);
    }

    public function destroy(Book $book): RedirectResponse
    {
        $this->deleteCoverImage($book);

        $book->delete();

        return redirect()
            ->route('administrators.library.books.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Book removed from the catalog.',
            ]);
    }

    public function bulkDestroy(BulkDeleteBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ids = $validated['book_ids'];

        $books = Book::query()->whereIn('id', $ids)->get();

        foreach ($books as $book) {
            Gate::authorize('delete', $book);
            $this->deleteCoverImage($book);
            $book->delete();
        }

        return redirect()
            ->route('administrators.library.books.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "{$books->count()} book(s) moved to trash.",
            ]);
    }

    public function bulkForceDestroy(BulkForceDeleteBookRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $ids = $validated['book_ids'];
        $confirmText = (string) ($validated['confirm_text'] ?? '');
        $count = count($ids);
        $expected = $count === 1
            ? 'PERMANENTLY DELETE 1 BOOK'
            : "PERMANENTLY DELETE {$count} BOOKS";

        if (! hash_equals($expected, $confirmText)) {
            return back()->withErrors([
                'confirm_text' => 'The confirmation text does not match.',
            ]);
        }

        Gate::authorize('forceDeleteAny', Book::class);

        $books = Book::withTrashed()->whereIn('id', $ids)->get();
        $failures = [];
        $deletedCount = 0;

        foreach ($books as $book) {
            Gate::authorize('forceDelete', $book);

            try {
                DB::transaction(function () use ($book): void {
                    $this->deleteCoverImage($book);

                    $edition = $book->digitalEdition;
                    if ($edition !== null) {
                        try {
                            Storage::disk($edition->disk)->delete($edition->path);
                        } catch (Throwable $throwable) {
                            report($throwable);
                        }
                    }

                    $book->forceDelete();
                });

                $deletedCount++;
            } catch (Throwable $throwable) {
                report($throwable);
                $failures[] = $book->title;
            }
        }

        if ($failures !== []) {
            return redirect()
                ->route('administrators.library.books.index')
                ->with('flash', [
                    'type' => 'error',
                    'message' => "Deleted {$deletedCount} book(s). Failed to delete: ".implode(', ', $failures).'.',
                ]);
        }

        return redirect()
            ->route('administrators.library.books.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "{$deletedCount} book(s) permanently deleted.",
            ]);
    }

    private function deleteCoverImage(Book $book): void
    {
        if (is_string($book->cover_image_path) && $book->cover_image_path !== '') {
            Storage::disk('public')->delete($book->cover_image_path);
        }
    }

    private function normalizeBookData(array $validated): array
    {
        $totalCopies = $validated['total_copies'];
        $availableCopies = $validated['available_copies'] ?? $totalCopies;

        $validated['available_copies'] = min($availableCopies, $totalCopies);

        return $validated;
    }

    private function storeCoverImage(UploadedFile $file, ?string $currentPath = null): string
    {
        if ($currentPath) {
            Storage::disk('public')->delete($currentPath);
        }

        return $file->storePublicly('library/books/covers', 'public');
    }

    private function resolveCoverImageUrl(Book $book): ?string
    {
        if (is_string($book->cover_image_path) && $book->cover_image_path !== '') {
            return Storage::disk('public')->url($book->cover_image_path);
        }

        if (is_string($book->cover_image) && $book->cover_image !== '') {
            return $book->cover_image;
        }

        return null;
    }

    private function getBookOptions(): array
    {
        return [
            'authors' => Author::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Author $author): array => [
                    'value' => $author->id,
                    'label' => $author->name,
                ])
                ->values()
                ->all(),
            'categories' => Category::query()
                ->select(['id', 'name'])
                ->orderBy('name')
                ->get()
                ->map(fn (Category $category): array => [
                    'value' => $category->id,
                    'label' => $category->name,
                ])
                ->values()
                ->all(),
            'statuses' => [
                ['value' => 'available', 'label' => 'Available'],
                ['value' => 'borrowed', 'label' => 'Borrowed'],
                ['value' => 'maintenance', 'label' => 'Maintenance'],
            ],
            'digital_rights_bases' => collect(DigitalRightsBasis::cases())
                ->map(fn (DigitalRightsBasis $basis): array => [
                    'value' => $basis->value,
                    'label' => $basis->label(),
                ])
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
