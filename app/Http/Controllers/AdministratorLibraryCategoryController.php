<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Administrators\LibraryCategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Modules\LibrarySystem\Models\Category;

final class AdministratorLibraryCategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $search = $request->input('search');

        $categories = Category::query()
            ->withCount('books')
            ->when(is_string($search) && mb_trim($search) !== '', function ($query) use ($search): void {
                $term = mb_trim($search);
                $query->where('name', 'ilike', "%{$term}%");
            })
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'color' => $category->color,
                'books_count' => $category->books_count,
            ]);

        return Inertia::render('administrators/library/categories/index', [
            'user' => $this->getUserProps(),
            'categories' => $categories,
            'filters' => [
                'search' => is_string($search) ? $search : null,
            ],
            'flash' => session('flash'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('administrators/library/categories/edit', [
            'user' => $this->getUserProps(),
            'category' => null,
        ]);
    }

    public function store(LibraryCategoryRequest $request): RedirectResponse
    {
        $category = Category::create($this->normalizeCategoryData($request->validated()));

        return redirect()
            ->route('administrators.library.categories.index')
            ->with('flash', [
                'type' => 'success',
                'message' => "Category {$category->name} created.",
            ]);
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('administrators/library/categories/edit', [
            'user' => $this->getUserProps(),
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'description' => $category->description,
                'color' => $category->color,
            ],
        ]);
    }

    public function update(LibraryCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($this->normalizeCategoryData($request->validated()));

        return redirect()
            ->route('administrators.library.categories.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Category updated.',
            ]);
    }

    public function destroy(Category $category): RedirectResponse
    {
        $category->delete();

        return redirect()
            ->route('administrators.library.categories.index')
            ->with('flash', [
                'type' => 'success',
                'message' => 'Category removed from the library system.',
            ]);
    }

    private function normalizeCategoryData(array $validated): array
    {
        $validated['color'] = $validated['color'] ?: '#6366f1';

        return $validated;
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
