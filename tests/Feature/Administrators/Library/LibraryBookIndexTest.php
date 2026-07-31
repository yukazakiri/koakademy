<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Inertia\Testing\AssertableInertia;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    Config::set('inertia.testing.ensure_pages_exist', false);

    GeneralSetting::factory()->create([
        'library_module_enabled' => true,
        'is_setup' => true,
    ]);
});

it('renders the admin books index for an administrator', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $book = Book::factory()->create([
        'title' => 'Index Test Book',
        'status' => 'available',
        'total_copies' => 1,
        'available_copies' => 1,
    ]);

    actingAs($admin)
        ->get(route('administrators.library.books.index'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/library/books/index', false)
            ->has('books.data', 1)
            ->where('books.data.0.title', 'Index Test Book')
            ->where('books.data.0.author.name', $book->author->name)
            ->where('books.data.0.category.name', $book->category->name)
            ->where('stats.total_books', 1)
            ->where('stats.available_copies', 1)
            ->where('stats.borrowed_books', 0)
            ->where('filters.search', null)
            ->where('filters.status', null)
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
            ->where('filters.per_page', 20)
            ->has('options.statuses', 4)
            ->has('options.per_page', 4)
            ->has('user.name')
        );
});

it('filters books by search term', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $author = Author::factory()->create(['name' => 'Searchable Author']);
    $category = Category::factory()->create(['name' => 'Searchable Category']);

    Book::factory()->create(['title' => 'Modern Physics', 'author_id' => $author->id, 'category_id' => $category->id]);
    Book::factory()->create(['title' => 'World Literature', 'author_id' => $author->id, 'category_id' => $category->id]);
    Book::factory()->create(['title' => 'Physics Lab Manual', 'author_id' => $author->id, 'category_id' => $category->id]);

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['search' => 'Physics']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('administrators/library/books/index', false)
            ->has('books.data', 2)
            ->where('filters.search', 'Physics')
        );

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['search' => 'Searchable Author']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('books.data', 3)
            ->where('filters.search', 'Searchable Author')
        );

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['search' => 'Searchable Category']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('books.data', 3)
        );
});

it('filters books by status', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Book::factory()->create(['status' => 'available']);
    Book::factory()->create(['status' => 'available']);
    Book::factory()->create(['status' => 'borrowed']);

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['status' => 'available']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('books.data', 2)
            ->where('filters.status', 'available')
        );

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['status' => 'borrowed']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('books.data', 1)
            ->where('filters.status', 'borrowed')
        );
});

it('sorts books by publication year', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Book::factory()->create(['title' => 'Old Book', 'publication_year' => 1990]);
    Book::factory()->create(['title' => 'New Book', 'publication_year' => 2020]);

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['sort' => 'publication_year', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('books.data.0.title', 'New Book')
            ->where('books.data.1.title', 'Old Book')
            ->where('filters.sort', 'publication_year')
            ->where('filters.direction', 'desc')
        );

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['sort' => 'publication_year', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('books.data.0.title', 'Old Book')
            ->where('books.data.1.title', 'New Book')
        );
});

it('sorts books by recently added', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $oldBook = Book::factory()->create(['title' => 'First Book']);
    $newBook = Book::factory()->create(['title' => 'Second Book']);

    $oldBook->update(['created_at' => now()->subDays(7)]);
    $newBook->update(['created_at' => now()->subDay()]);

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['sort' => 'created_at', 'direction' => 'desc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('books.data.0.title', 'Second Book')
            ->where('books.data.1.title', 'First Book')
        );

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['sort' => 'created_at', 'direction' => 'asc']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('books.data.0.title', 'First Book')
            ->where('books.data.1.title', 'Second Book')
        );
});

it('falls back to default sort for invalid values', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Book::factory()->create();

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['sort' => 'invalid_column', 'direction' => 'sideways']))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.sort', 'created_at')
            ->where('filters.direction', 'desc')
        );
});

it('paginates books', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    Book::factory()->count(25)->create();

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['per_page' => 10]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('books.data', 10)
            ->where('books.per_page', 10)
            ->where('books.last_page', 3)
        );

    actingAs($admin)
        ->get(route('administrators.library.books.index', ['per_page' => 1000]))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('books.per_page', 20)
        );
});

it('forbids non-administrator roles from the books index', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);

    actingAs($user)
        ->get(route('administrators.library.books.index'))
        ->assertForbidden();
})->with([
    UserRole::Student,
    UserRole::Professor,
    UserRole::User,
]);
