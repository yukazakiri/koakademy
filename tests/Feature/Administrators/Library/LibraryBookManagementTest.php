<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;

use function Pest\Laravel\actingAs;

it('stores a book with its library identifiers', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $author = Author::query()->create([
        'name' => 'Test Library Author',
    ]);

    $category = Category::query()->create([
        'name' => 'Test Library Category',
    ]);

    actingAs($admin)
        ->post(route('administrators.library.books.store'), [
            'title' => 'Catalog Regression Test',
            'isbn' => '978-1-4028-9462-6',
            'call_number' => 'QA76.73.P224',
            'accession_number' => 'ACC-2026-0001',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'publisher' => 'KoAcademy Press',
            'publication_year' => 2026,
            'pages' => 320,
            'description' => 'Verifies the administrator add-book request.',
            'total_copies' => 3,
            'available_copies' => 5,
            'location' => 'Main Library A-12',
            'status' => 'available',
        ])
        ->assertRedirect(route('administrators.library.books.index'));

    $book = Book::query()
        ->where('accession_number', 'ACC-2026-0001')
        ->first();

    expect($book)->not->toBeNull()
        ->and($book?->title)->toBe('Catalog Regression Test')
        ->and($book?->call_number)->toBe('QA76.73.P224')
        ->and($book?->accession_number)->toBe('ACC-2026-0001')
        ->and($book?->author_id)->toBe($author->id)
        ->and($book?->category_id)->toBe($category->id)
        ->and($book?->total_copies)->toBe(3)
        ->and($book?->available_copies)->toBe(3);
});

it('stores multiple books with the same ISBN', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $author = Author::query()->create([
        'name' => 'Duplicate ISBN Author',
    ]);

    $category = Category::query()->create([
        'name' => 'Duplicate ISBN Category',
    ]);

    $isbn = '978-1-4028-9462-6';
    $payload = [
        'isbn' => $isbn,
        'author_id' => $author->id,
        'category_id' => $category->id,
        'publisher' => 'KoAcademy Press',
        'publication_year' => 2026,
        'pages' => 320,
        'total_copies' => 1,
        'available_copies' => 1,
        'location' => 'Main Library A-12',
        'status' => 'available',
    ];

    actingAs($admin)
        ->post(route('administrators.library.books.store'), [
            ...$payload,
            'title' => 'First Catalog Record',
            'accession_number' => 'ACC-2026-0002',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('administrators.library.books.index'));

    actingAs($admin)
        ->post(route('administrators.library.books.store'), [
            ...$payload,
            'title' => 'Second Catalog Record',
            'accession_number' => 'ACC-2026-0003',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('administrators.library.books.index'));

    expect(Book::query()->where('isbn', $isbn)->count())->toBe(2);
});

it('quick creates and returns selectable authors and categories', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $authorResponse = actingAs($admin)
        ->postJson(route('administrators.library.authors.store'), [
            'name' => 'Ursula K. Le Guin',
        ])
        ->assertCreated()
        ->assertJsonPath('author.name', 'Ursula K. Le Guin')
        ->assertJsonStructure([
            'author' => ['id', 'name'],
        ]);

    $author = Author::query()->findOrFail($authorResponse->json('author.id'));
    $this->assertModelExists($author);

    $categoryResponse = actingAs($admin)
        ->postJson(route('administrators.library.categories.store'), [
            'name' => 'Speculative Fiction',
        ])
        ->assertCreated()
        ->assertJsonPath('category.name', 'Speculative Fiction')
        ->assertJsonPath('category.color', '#6366f1')
        ->assertJsonStructure([
            'category' => ['id', 'name', 'color'],
        ]);

    $category = Category::query()->findOrFail($categoryResponse->json('category.id'));
    $this->assertModelExists($category);

    actingAs($admin)
        ->postJson(route('administrators.library.categories.store'), [
            'name' => 'Speculative Fiction',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

it('returns distinct filtered identifier suggestions with a bounded result set', function (): void {
    $admin = User::factory()->create([
        'role' => UserRole::Admin,
    ]);

    $author = Author::query()->create([
        'name' => 'Identifier Suggestion Author',
    ]);

    $category = Category::query()->create([
        'name' => 'Identifier Suggestion Category',
    ]);

    foreach (range(1, 27) as $number) {
        Book::query()->create([
            'title' => "Identifier Suggestion Book {$number}",
            'isbn' => sprintf('978-TEST-%02d', $number),
            'call_number' => sprintf('QA76.%02d', $number),
            'author_id' => $author->id,
            'category_id' => $category->id,
        ]);
    }

    Book::query()->create([
        'title' => 'Duplicate Identifier Suggestion Book',
        'isbn' => '978-TEST-01',
        'call_number' => 'QA76.01',
        'author_id' => $author->id,
        'category_id' => $category->id,
    ]);

    actingAs($admin)
        ->getJson(route('administrators.library.books.field-values', [
            'field' => 'isbn',
            'search' => '978-TEST',
        ]))
        ->assertSuccessful()
        ->assertJsonCount(25, 'values')
        ->assertJsonPath('values.0', '978-TEST-01')
        ->assertJsonPath('values.24', '978-TEST-25');

    actingAs($admin)
        ->getJson(route('administrators.library.books.field-values', [
            'field' => 'call_number',
            'search' => 'QA76.27',
        ]))
        ->assertSuccessful()
        ->assertExactJson([
            'values' => ['QA76.27'],
        ]);

    actingAs($admin)
        ->getJson(route('administrators.library.books.field-values', [
            'field' => 'title',
            'search' => 'Identifier',
        ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('field');
});

it('allows librarian to view create page and store a book', function (): void {
    $this->withoutVite();

    $librarian = User::factory()->create([
        'role' => UserRole::Librarian,
    ]);

    $author = Author::query()->create(['name' => 'Librarian Author']);
    $category = Category::query()->create(['name' => 'Librarian Category']);

    actingAs($librarian)
        ->get(route('administrators.library.books.create'))
        ->assertSuccessful();

    actingAs($librarian)
        ->post(route('administrators.library.books.store'), [
            'title' => 'Librarian Created Book',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'total_copies' => 5,
            'status' => 'available',
        ])
        ->assertRedirect(route('administrators.library.books.index'));

    $book = Book::query()->where('title', 'Librarian Created Book')->first();
    expect($book)->not->toBeNull()
        ->and($book?->total_copies)->toBe(5)
        ->and($book?->available_copies)->toBe(5)
        ->and($book?->status)->toBe('available');
});

it('stores a book with cover image upload', function (): void {
    Illuminate\Support\Facades\Storage::fake('public');

    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $author = Author::query()->create(['name' => 'Cover Image Author']);
    $category = Category::query()->create(['name' => 'Cover Image Category']);

    $file = Illuminate\Http\UploadedFile::fake()->image('cover.jpg', 600, 800);

    actingAs($admin)
        ->post(route('administrators.library.books.store'), [
            'title' => 'Book with Uploaded Cover',
            'author_id' => $author->id,
            'category_id' => $category->id,
            'total_copies' => 2,
            'status' => 'available',
            'cover_image_upload' => $file,
        ])
        ->assertRedirect(route('administrators.library.books.index'));

    $book = Book::query()->where('title', 'Book with Uploaded Cover')->first();
    expect($book)->not->toBeNull()
        ->and($book?->cover_image_path)->not->toBeNull();

    Illuminate\Support\Facades\Storage::disk('public')->assertExists($book?->cover_image_path);
});

it('validates required fields on book creation', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);

    actingAs($admin)
        ->post(route('administrators.library.books.store'), [])
        ->assertSessionHasErrors([
            'title',
            'author_id',
            'category_id',
            'total_copies',
            'status',
        ]);
});
