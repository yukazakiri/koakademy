<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Models\GeneralSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Modules\LibrarySystem\Models\Book;
use Modules\LibrarySystem\Models\BorrowRecord;
use Modules\LibrarySystem\Models\DigitalEdition;
use Modules\LibrarySystem\Models\LibraryBookmark;
use Modules\LibrarySystem\Models\UserBookState;

use function Pest\Laravel\actingAs;

$seededRoles = false;

beforeEach(function () use (&$seededRoles): void {
    Config::set('inertia.testing.ensure_pages_exist', false);

    if (! $seededRoles) {
        $this->seed(RolesSeeder::class);
        $seededRoles = true;
    }

    GeneralSetting::factory()->create([
        'library_module_enabled' => true,
        'is_setup' => true,
    ]);

    Storage::fake('public');
    Storage::fake('local');
});

it('bulk deletes selected books', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $books = Book::factory()->count(3)->create();
    $ids = $books->pluck('id')->all();

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-destroy'), [
            'book_ids' => $ids,
        ])
        ->assertRedirect(route('administrators.library.books.index'))
        ->assertSessionHas('flash');

    expect(Book::withTrashed()->whereIn('id', $ids)->count())->toBe(3)
        ->and(Book::whereIn('id', $ids)->count())->toBe(0);

    foreach ($books as $book) {
        expect($book->fresh()->deleted_at)->not->toBeNull();
    }
});

it('deletes cover images when bulk deleting', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $file = UploadedFile::fake()->image('cover.jpg');
    $path = $file->storePublicly('library/books/covers', 'public');
    $book = Book::factory()->create(['cover_image_path' => $path]);

    Storage::disk('public')->assertExists($path);

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-destroy'), [
            'book_ids' => [$book->id],
        ]);

    Storage::disk('public')->assertMissing($path);
});

it('requires at least one book id for bulk delete', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-destroy'), [
            'book_ids' => [],
        ])
        ->assertSessionHasErrors(['book_ids']);
});

it('rejects invalid book ids for bulk delete', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-destroy'), [
            'book_ids' => [999999],
        ])
        ->assertSessionHasErrors(['book_ids.0']);
});

it('bulk force deletes selected books and their relationships', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $user = User::factory()->create(['role' => UserRole::Student]);
    $book = Book::factory()->create();
    $ids = [$book->id];

    BorrowRecord::query()->create([
        'book_id' => $book->id,
        'user_id' => $user->id,
        'borrowed_at' => now(),
        'due_date' => now()->addDays(7),
        'status' => 'borrowed',
    ]);

    UserBookState::query()->create([
        'book_id' => $book->id,
        'user_id' => $user->id,
    ]);

    LibraryBookmark::query()->create([
        'book_id' => $book->id,
        'user_id' => $user->id,
        'page' => 10,
    ]);

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-force-destroy'), [
            'book_ids' => $ids,
            'confirm_text' => 'PERMANENTLY DELETE 1 BOOK',
        ])
        ->assertRedirect(route('administrators.library.books.index'))
        ->assertSessionHas('flash');

    expect(Book::withTrashed()->where('id', $book->id)->exists())->toBeFalse()
        ->and(BorrowRecord::query()->where('book_id', $book->id)->exists())->toBeFalse()
        ->and(UserBookState::query()->where('book_id', $book->id)->exists())->toBeFalse()
        ->and(LibraryBookmark::query()->where('book_id', $book->id)->exists())->toBeFalse();
});

it('deletes the digital edition file when bulk force deleting', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $book = Book::factory()->create();
    $pdf = UploadedFile::fake()->create('edition.pdf', 100);
    $path = $pdf->store('library/books/editions', 'local');

    DigitalEdition::query()->create([
        'book_id' => $book->id,
        'disk' => 'local',
        'path' => $path,
        'original_name' => 'edition.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 100,
        'sha256' => hash('sha256', 'test'),
    ]);

    Storage::disk('local')->assertExists($path);

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-force-destroy'), [
            'book_ids' => [$book->id],
            'confirm_text' => 'PERMANENTLY DELETE 1 BOOK',
        ]);

    Storage::disk('local')->assertMissing($path);
    expect(DigitalEdition::query()->where('book_id', $book->id)->exists())->toBeFalse();
});

it('rejects force delete without the correct confirmation text', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $book = Book::factory()->create();

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-force-destroy'), [
            'book_ids' => [$book->id],
            'confirm_text' => 'WRONG TEXT',
        ])
        ->assertSessionHasErrors(['confirm_text']);

    expect(Book::query()->where('id', $book->id)->exists())->toBeTrue();
});

it('requires confirmation text for bulk force delete', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $book = Book::factory()->create();

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-force-destroy'), [
            'book_ids' => [$book->id],
        ])
        ->assertSessionHasErrors(['confirm_text']);
});

it('uses plural confirmation text for multiple books', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $admin->assignRole(UserRole::Admin->value);
    $books = Book::factory()->count(2)->create();

    actingAs($admin)
        ->delete(route('administrators.library.books.bulk-force-destroy'), [
            'book_ids' => $books->pluck('id')->all(),
            'confirm_text' => 'PERMANENTLY DELETE 2 BOOKS',
        ])
        ->assertRedirect(route('administrators.library.books.index'));

    expect(Book::query()->count())->toBe(0);
});

it('forbids non-administrator roles from bulk delete endpoints', function (UserRole $role): void {
    $user = User::factory()->create(['role' => $role]);
    $book = Book::factory()->create();

    actingAs($user)
        ->delete(route('administrators.library.books.bulk-destroy'), [
            'book_ids' => [$book->id],
        ])
        ->assertForbidden();

    actingAs($user)
        ->delete(route('administrators.library.books.bulk-force-destroy'), [
            'book_ids' => [$book->id],
            'confirm_text' => 'PERMANENTLY DELETE 1 BOOK',
        ])
        ->assertForbidden();
})->with([
    UserRole::Student,
    UserRole::Professor,
    UserRole::User,
]);
