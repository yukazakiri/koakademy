<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Modules\Inventory\Http\Controllers\AdministratorInventoryBorrowingController;
use Modules\Inventory\Http\Controllers\AdministratorInventoryController;
use Modules\Inventory\Http\Controllers\AdministratorInventoryProductController;

Route::middleware(['auth', 'administrators.only'])
    ->prefix('administrators')
    ->name('administrators.')
    ->group(function (): void {
        Route::get('/inventory', [AdministratorInventoryController::class, 'index'])->name('inventory.index');

        Route::get('/inventory/items', [AdministratorInventoryProductController::class, 'index'])->name('inventory.items.index');
        Route::get('/inventory/items/create', [AdministratorInventoryProductController::class, 'create'])->name('inventory.items.create');
        Route::post('/inventory/items', [AdministratorInventoryProductController::class, 'store'])->name('inventory.items.store');
        Route::get('/inventory/items/{inventoryProduct}/edit', [AdministratorInventoryProductController::class, 'edit'])->name('inventory.items.edit');
        Route::put('/inventory/items/{inventoryProduct}', [AdministratorInventoryProductController::class, 'update'])->name('inventory.items.update');
        Route::post('/inventory/items/{inventoryProduct}/location', [AdministratorInventoryProductController::class, 'updateLocation'])->name('inventory.items.update-location');
        Route::delete('/inventory/items/{inventoryProduct}', [AdministratorInventoryProductController::class, 'destroy'])->name('inventory.items.destroy');

        Route::get('/inventory/borrowings', [AdministratorInventoryBorrowingController::class, 'index'])->name('inventory.borrowings.index');
        Route::get('/inventory/borrowings/create', [AdministratorInventoryBorrowingController::class, 'create'])->name('inventory.borrowings.create');
        Route::post('/inventory/borrowings', [AdministratorInventoryBorrowingController::class, 'store'])->name('inventory.borrowings.store');
        Route::get('/inventory/borrowings/{inventoryBorrowing}/edit', [AdministratorInventoryBorrowingController::class, 'edit'])->name('inventory.borrowings.edit');
        Route::put('/inventory/borrowings/{inventoryBorrowing}', [AdministratorInventoryBorrowingController::class, 'update'])->name('inventory.borrowings.update');
        Route::delete('/inventory/borrowings/{inventoryBorrowing}', [AdministratorInventoryBorrowingController::class, 'destroy'])->name('inventory.borrowings.destroy');
    });
