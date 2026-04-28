<?php

declare(strict_types=1);

namespace Modules\LibrarySystem\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Http\Requests\Administrators\LibraryBookRequest;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;

final class AdministratorLibraryBookController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');
        $status = $request->input('status');

        $books = Book::query()
            ->with(['author', 'category'])
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where(function ($nested) use ($term): void {
                    $nested->where('title', 'ilike', "%{$term}%")
                        ->orWhere('isbn', 'ilike', "%{$term}%")
                        ->orWhereHas('author', fn ($authorQuery) => $authorQuery->where('name', 'ilike', "%{$term}%"))
                        ->orWhereHas('category', fn ($categoryQuery) => $categoryQuery->where('name', 'ilike', "%{$term}%"));
                });
            })
            ->when(is_string($status) && $status !== '' && $status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderBy('title')
            ->limit(50)
            ->get()
            ->map(fn (Book $book): array => [
                'id' => $book->id,
                'title' => $book->title,
                'isbn' => $book->isbn,
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
            ],
            'options' => [
                'statuses' => [
                    ['value' => 'all', 'label' => 'All statuses'],
                    ['value' => 'available', 'label' => 'Available'],
                    ['value' => 'borrowed', 'label' => 'Borrowed'],
                    ['value' => 'maintenance', 'label' => 'Maintenance'],
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
        return Inertia::render('administrators/library/books/edit', [
            'user' => $this->getUserProps(),
            'book' => [
                'id' => $book->id,
                'title' => $book->title,
                'isbn' => $book->isbn,
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
        if (is_string($book->cover_image_path) && $book->cover_image_path !== '') {
            Storage::disk('public')->delete($book->cover_image_path);
        }

        $book->delete();

        return redirect()
            ->route('administrators.library.books.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Book removed from the catalog.',
            ]);
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
                ->orderBy('name')
                ->get()
                ->map(fn (Author $author): array => [
                    'value' => $author->id,
                    'label' => $author->name,
                ])
                ->values()
                ->all(),
            'categories' => Category::query()
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
