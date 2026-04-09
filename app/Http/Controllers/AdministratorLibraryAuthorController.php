<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\LibraryAuthorRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Models\Author;

final class AdministratorLibraryAuthorController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $authors = Author::query()
            ->withCount('books')
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where('name', 'ilike', "%{$term}%");
            })
            ->orderBy('name')
            ->limit(50)
            ->get()
            ->map(fn (Author $author): array => [
                'id' => $author->id,
                'name' => $author->name,
                'nationality' => $author->nationality,
                'birth_date' => $author->birth_date?->toDateString(),
                'books_count' => $author->books_count,
            ]);

        return Inertia::render('administrators/library/authors/index', [
            'user' => $this->getUserProps(),
            'authors' => $authors,
            'filters' => [
                'search' => is_string($search) ? $search : null,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/library/authors/edit', [
            'user' => $this->getUserProps(),
            'author' => null,
        ]);
    }

    public function store(LibraryAuthorRequest $request): RedirectResponse
    {
        $author = Author::create($request->validated());

        return redirect()
            ->route('administrators.library.authors.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Author {$author->name} added.",
            ]);
    }

    public function edit(Author $author): Response
    {
        return Inertia::render('administrators/library/authors/edit', [
            'user' => $this->getUserProps(),
            'author' => [
                'id' => $author->id,
                'name' => $author->name,
                'biography' => $author->biography,
                'birth_date' => $author->birth_date?->toDateString(),
                'nationality' => $author->nationality,
            ],
        ]);
    }

    public function update(LibraryAuthorRequest $request, Author $author): RedirectResponse
    {
        $author->update($request->validated());

        return redirect()
            ->route('administrators.library.authors.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Author details updated.',
            ]);
    }

    public function destroy(Author $author): RedirectResponse
    {
        $author->delete();

        return redirect()
            ->route('administrators.library.authors.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Author removed from the library system.',
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
