<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LibrarySystem\Http\Controllers\AdministratorDigitalEditionController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryAuthorController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryBookController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryBorrowRecordController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryCategoryController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryResearchPaperController;
use Modules\LibrarySystem\Http\Controllers\DigitalLibraryController;
use Modules\LibrarySystem\Http\Controllers\DigitalLibraryStateController;
use Modules\LibrarySystem\Http\Middleware\EnsureLibraryModuleEnabled;

Route::middleware(['auth', EnsureLibraryModuleEnabled::class])
    ->name('library.')
    ->prefix('library')
    ->group(function (): void {
        Route::get('/', [DigitalLibraryController::class, 'index'])->name('index');
        Route::get('/books/{book}', [DigitalLibraryController::class, 'show'])->name('books.show');
        Route::get('/books/{book}/read', [DigitalLibraryController::class, 'read'])->name('books.read');
        Route::get('/books/{book}/content', [DigitalLibraryController::class, 'content'])
            ->middleware('throttle:120,1')
            ->name('books.content');
        Route::get('/books/{book}/download', [DigitalLibraryController::class, 'download'])
            ->middleware('throttle:30,1')
            ->name('books.download');

        Route::post('/books/{book}/favorite', [DigitalLibraryStateController::class, 'favorite'])
            ->name('books.favorite');
        Route::delete('/books/{book}/favorite', [DigitalLibraryStateController::class, 'unfavorite'])
            ->name('books.unfavorite');
        Route::put('/books/{book}/progress', [DigitalLibraryStateController::class, 'progress'])
            ->middleware('throttle:120,1')
            ->name('books.progress');
        Route::post('/books/{book}/bookmarks', [DigitalLibraryStateController::class, 'bookmark'])
            ->middleware('throttle:60,1')
            ->name('books.bookmarks.store');
        Route::delete('/books/{book}/bookmarks/{bookmark}', [DigitalLibraryStateController::class, 'removeBookmark'])
            ->middleware('throttle:60,1')
            ->name('books.bookmarks.destroy');
    });

Route::middleware(['auth', 'administrators.only'])
    ->prefix('administrators')
    ->name('administrators.')
    ->group(function (): void {
        Route::get('/library', [AdministratorLibraryController::class, 'index'])->name('library.index');

        Route::get('/library/books', [AdministratorLibraryBookController::class, 'index'])->name('library.books.index');
        Route::get('/library/books/field-values', [AdministratorLibraryBookController::class, 'fieldValues'])
            ->name('library.books.field-values');
        Route::get('/library/books/create', [AdministratorLibraryBookController::class, 'create'])->name('library.books.create');
        Route::post('/library/books', [AdministratorLibraryBookController::class, 'store'])->name('library.books.store');
        Route::delete('/library/books/bulk', [AdministratorLibraryBookController::class, 'bulkDestroy'])
            ->name('library.books.bulk-destroy');
        Route::delete('/library/books/bulk/force', [AdministratorLibraryBookController::class, 'bulkForceDestroy'])
            ->name('library.books.bulk-force-destroy');
        Route::get('/library/books/{book}/edit', [AdministratorLibraryBookController::class, 'edit'])->name('library.books.edit');
        Route::put('/library/books/{book}', [AdministratorLibraryBookController::class, 'update'])->name('library.books.update');
        Route::delete('/library/books/{book}', [AdministratorLibraryBookController::class, 'destroy'])->name('library.books.destroy');
        Route::post('/library/books/{book}/digital-edition', [AdministratorDigitalEditionController::class, 'store'])
            ->name('library.books.digital-edition.store');
        Route::put('/library/books/{book}/digital-edition', [AdministratorDigitalEditionController::class, 'update'])
            ->name('library.books.digital-edition.update');
        Route::delete('/library/books/{book}/digital-edition', [AdministratorDigitalEditionController::class, 'destroy'])
            ->name('library.books.digital-edition.destroy');

        Route::get('/library/authors', [AdministratorLibraryAuthorController::class, 'index'])->name('library.authors.index');
        Route::get('/library/authors/create', [AdministratorLibraryAuthorController::class, 'create'])->name('library.authors.create');
        Route::post('/library/authors', [AdministratorLibraryAuthorController::class, 'store'])->name('library.authors.store');
        Route::get('/library/authors/{author}/edit', [AdministratorLibraryAuthorController::class, 'edit'])->name('library.authors.edit');
        Route::put('/library/authors/{author}', [AdministratorLibraryAuthorController::class, 'update'])->name('library.authors.update');
        Route::delete('/library/authors/{author}', [AdministratorLibraryAuthorController::class, 'destroy'])->name('library.authors.destroy');

        Route::get('/library/categories', [AdministratorLibraryCategoryController::class, 'index'])->name('library.categories.index');
        Route::get('/library/categories/create', [AdministratorLibraryCategoryController::class, 'create'])->name('library.categories.create');
        Route::post('/library/categories', [AdministratorLibraryCategoryController::class, 'store'])->name('library.categories.store');
        Route::get('/library/categories/{category}/edit', [AdministratorLibraryCategoryController::class, 'edit'])->name('library.categories.edit');
        Route::put('/library/categories/{category}', [AdministratorLibraryCategoryController::class, 'update'])->name('library.categories.update');
        Route::delete('/library/categories/{category}', [AdministratorLibraryCategoryController::class, 'destroy'])->name('library.categories.destroy');

        Route::get('/library/borrow-records', [AdministratorLibraryBorrowRecordController::class, 'index'])->name('library.borrow-records.index');
        Route::get('/library/borrow-records/create', [AdministratorLibraryBorrowRecordController::class, 'create'])->name('library.borrow-records.create');
        Route::post('/library/borrow-records', [AdministratorLibraryBorrowRecordController::class, 'store'])->name('library.borrow-records.store');
        Route::get('/library/borrow-records/{borrowRecord}/edit', [AdministratorLibraryBorrowRecordController::class, 'edit'])->name('library.borrow-records.edit');
        Route::put('/library/borrow-records/{borrowRecord}', [AdministratorLibraryBorrowRecordController::class, 'update'])->name('library.borrow-records.update');
        Route::delete('/library/borrow-records/{borrowRecord}', [AdministratorLibraryBorrowRecordController::class, 'destroy'])->name('library.borrow-records.destroy');

        Route::get('/library/research-papers', [AdministratorLibraryResearchPaperController::class, 'index'])->name('library.research-papers.index');
        Route::get('/library/research-papers/create', [AdministratorLibraryResearchPaperController::class, 'create'])->name('library.research-papers.create');
        Route::post('/library/research-papers', [AdministratorLibraryResearchPaperController::class, 'store'])->name('library.research-papers.store');
        Route::get('/library/research-papers/{researchPaper}/edit', [AdministratorLibraryResearchPaperController::class, 'edit'])->name('library.research-papers.edit');
        Route::put('/library/research-papers/{researchPaper}', [AdministratorLibraryResearchPaperController::class, 'update'])->name('library.research-papers.update');
        Route::delete('/library/research-papers/{researchPaper}', [AdministratorLibraryResearchPaperController::class, 'destroy'])->name('library.research-papers.destroy');
    });
