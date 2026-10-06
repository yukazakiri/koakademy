<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\User;
use Filament\Facades\Filament;
use Modules\LibrarySystem\Filament\Resources\Books\BookResource;
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
