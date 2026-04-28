<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryAuthorController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryBookController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryBorrowRecordController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryCategoryController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryController;
use Modules\LibrarySystem\Http\Controllers\AdministratorLibraryResearchPaperController;

Route::middleware(['auth', 'administrators.only'])
    ->prefix('administrators')
    ->name('administrators.')
    ->group(function (): void {
        Route::get('/library', [AdministratorLibraryController::class, 'index'])->name('library.index');

        Route::get('/library/books', [AdministratorLibraryBookController::class, 'index'])->name('library.books.index');
        Route::get('/library/books/create', [AdministratorLibraryBookController::class, 'create'])->name('library.books.create');
        Route::post('/library/books', [AdministratorLibraryBookController::class, 'store'])->name('library.books.store');
        Route::get('/library/books/{book}/edit', [AdministratorLibraryBookController::class, 'edit'])->name('library.books.edit');
        Route::put('/library/books/{book}', [AdministratorLibraryBookController::class, 'update'])->name('library.books.update');
        Route::delete('/library/books/{book}', [AdministratorLibraryBookController::class, 'destroy'])->name('library.books.destroy');

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
