<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;
use Modules\LibrarySystem\Filament\Resources\Books\Pages\CreateBook;
use Modules\LibrarySystem\Models\Author;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\Category;

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
});

it('authorizes administrative and librarian roles to create books via policy', function (UserRole $role, bool $canCreate): void {
    $user = User::factory()->create(['role' => $role]);

    $this->actingAs($user);

    expect($user->can('create', Book::class))->toBe($canCreate)
        ->and(BookResource::canCreate())->toBe($canCreate)
        ->and(BookResource::canViewAny())->toBe($canCreate);
})->with([
    'super admin' => [UserRole::SuperAdmin, true],
    'developer' => [UserRole::Developer, true],
    'admin' => [UserRole::Admin, true],
    'librarian' => [UserRole::Librarian, true],
    'student' => [UserRole::Student, false],
    'faculty instructor' => [UserRole::Instructor, false],
]);

it('creates a book record through the create book page logic', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $this->actingAs($admin);

    $author = Author::query()->create(['name' => 'Filament Author']);
    $category = Category::query()->create(['name' => 'Filament Category']);

    $page = new CreateBook();
    $reflection = new ReflectionClass($page);
    $mutateMethod = $reflection->getMethod('mutateFormDataBeforeCreate');

    $mutatedData = $mutateMethod->invoke($page, [
        'title' => 'Filament Crafted Book',
        'isbn' => '978-0-12345-678-9',
        'call_number' => 'Z674.4',
        'accession_number' => 'ACC-FIL-001',
        'author_id' => $author->id,
        'category_id' => $category->id,
        'publisher' => 'Filament Press',
        'publication_year' => 2026,
        'total_copies' => 4,
        'status' => 'available',
    ]);

    expect($mutatedData['total_copies'])->toBe(4)
        ->and($mutatedData['available_copies'])->toBe(4)
        ->and($mutatedData['status'])->toBe('available');

    $book = Book::create($mutatedData);

    expect($book->exists)->toBeTrue()
        ->and($book->title)->toBe('Filament Crafted Book')
        ->and($book->publication_year)->toBe(2026)
        ->and($book->total_copies)->toBe(4)
        ->and($book->available_copies)->toBe(4);
});

it('clamps available copies to total copies during creation', function (): void {
    $page = new CreateBook();
    $reflection = new ReflectionClass($page);
    $mutateMethod = $reflection->getMethod('mutateFormDataBeforeCreate');

    $mutatedData = $mutateMethod->invoke($page, [
        'title' => 'Copy Test Book',
        'total_copies' => 3,
        'available_copies' => 10,
    ]);

    expect($mutatedData['total_copies'])->toBe(3)
        ->and($mutatedData['available_copies'])->toBe(3);
});

it('derives initial availability from total copies when available_copies is omitted or empty', function (): void {
    $page = new CreateBook();
    $reflection = new ReflectionClass($page);
    $mutateMethod = $reflection->getMethod('mutateFormDataBeforeCreate');

    $mutatedData = $mutateMethod->invoke($page, [
        'title' => 'Unset Available Copies Book',
        'total_copies' => 5,
    ]);

    expect($mutatedData['total_copies'])->toBe(5)
        ->and($mutatedData['available_copies'])->toBe(5);

    $mutatedEmptyData = $mutateMethod->invoke($page, [
        'title' => 'Empty Available Copies Book',
        'total_copies' => 7,
        'available_copies' => '',
    ]);

    expect($mutatedEmptyData['total_copies'])->toBe(7)
        ->and($mutatedEmptyData['available_copies'])->toBe(7);
});

it('routes borrow record creation through stock accounting', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $borrower = User::factory()->create(['role' => UserRole::Student]);

    $author = Author::query()->create(['name' => 'Borrow Author']);
    $category = Category::query()->create(['name' => 'Borrow Category']);

    $book = Book::query()->create([
        'title' => 'Stock Test Book',
        'author_id' => $author->id,
        'category_id' => $category->id,
        'total_copies' => 2,
        'available_copies' => 2,
        'status' => 'available',
    ]);

    $stockService = app(Modules\LibrarySystem\Services\LibraryBorrowStockService::class);

    $record = Modules\LibrarySystem\Models\BorrowRecord::query()->create([
        'book_id' => $book->id,
        'user_id' => $borrower->id,
        'borrowed_at' => now(),
        'due_date' => now()->addDays(7),
        'status' => 'borrowed',
    ]);
    $stockService->recordCreated($record);

    expect($book->fresh()->available_copies)->toBe(1)
        ->and($book->fresh()->status)->toBe('available');

    // Borrow second copy -> available copies should drop to 0 and status becomes borrowed
    $record2 = Modules\LibrarySystem\Models\BorrowRecord::query()->create([
        'book_id' => $book->id,
        'user_id' => $borrower->id,
        'borrowed_at' => now(),
        'due_date' => now()->addDays(7),
        'status' => 'borrowed',
    ]);
    $stockService->recordCreated($record2);

    expect($book->fresh()->available_copies)->toBe(0)
        ->and($book->fresh()->status)->toBe('borrowed');

    // Cannot borrow when 0 available copies
    expect($stockService->canBorrow($book->fresh(), 'borrowed'))->toBeFalse();

    // Return record -> available copies increases back to 1 and status returns to available
    $record2->update(['status' => 'returned', 'returned_at' => now()]);
    $stockService->recordUpdated($record2, $book->id, 'borrowed');

    expect($book->fresh()->available_copies)->toBe(1)
        ->and($book->fresh()->status)->toBe('available');

    // Delete record 1 -> available copies increases back to 2
    $record->delete();
    $stockService->recordDeleted($record);

    expect($book->fresh()->available_copies)->toBe(2);
});

it('authorizes authors and categories for administrative and librarian roles', function (): void {
    $librarian = User::factory()->create(['role' => UserRole::Librarian]);
    $student = User::factory()->create(['role' => UserRole::Student]);

    expect($librarian->can('create', Author::class))->toBeTrue()
        ->and($librarian->can('create', Category::class))->toBeTrue()
        ->and($student->can('create', Author::class))->toBeFalse()
        ->and($student->can('create', Category::class))->toBeFalse();
});

it('configures book form schema matching database columns without invalid fields', function (): void {
    $reflection = new ReflectionClass(Modules\LibrarySystem\Filament\Resources\Books\Schemas\BookForm::class);
    $basicInfoMethod = $reflection->getMethod('getBasicInfoSection');
    $detailsMethod = $reflection->getMethod('getBookDetailsSection');

    $basicComponents = $basicInfoMethod->invoke(null);
    $detailsComponents = $detailsMethod->invoke(null);

    $basicNames = array_map(fn ($component): string => $component->getName(), $basicComponents);
    $detailsNames = array_map(fn ($component): string => $component->getName(), $detailsComponents);

    expect($basicNames)->toContain('title', 'isbn', 'call_number', 'accession_number', 'author_id', 'category_id', 'publisher', 'publication_year', 'status', 'total_copies', 'available_copies')
        ->and($basicNames)->not->toContain('published_date', 'edition', 'language', 'price')
        ->and($detailsNames)->toContain('pages', 'location', 'cover_image', 'cover_image_path', 'description')
        ->and($detailsNames)->not->toContain('language', 'price');
});
