<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\StockInputController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [App\Http\Controllers\DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Impersonate routes (super_admin only — guarded in controller)
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/stop-impersonate', [UserController::class, 'stopImpersonate'])->name('users.stop-impersonate');

    // Super admin only
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions/{role}/save', [PermissionController::class, 'save'])->name('permissions.save');
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/departments/{department}/assign-admin', [DepartmentController::class, 'assignAdmin'])->name('departments.assignAdmin');
        Route::delete('/departments/{department}/remove-admin', [DepartmentController::class, 'removeAdmin'])->name('departments.removeAdmin');
    });

    // Warehouse management: super_admin, warehouse_manager, supervisor
    Route::middleware('role:super_admin,warehouse_manager,supervisor')->group(function () {
        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/warehouse/{code}', [WarehouseController::class, 'warehouseView'])->name('warehouses.view');
        Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/locations/labels-data', [LocationController::class, 'labelsData'])->name('locations.labels-data');
        Route::get('/rack/{code}', [LocationController::class, 'rackView'])->name('locations.rack-view');
    });

    // Inventory / items: super_admin, warehouse_manager, supervisor
    Route::middleware('role:super_admin,warehouse_manager,supervisor')->group(function () {
        Route::get('/items/export', [ItemController::class, 'export'])->name('items.export');
        Route::get('/items/import-template', [ItemController::class, 'importTemplate'])->name('items.importTemplate');
        Route::post('/items/import', [ItemController::class, 'import'])->name('items.import');
        Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/items/{item}/variants', [ItemController::class, 'storeVariant'])->name('items.storeVariant');
        Route::post('/items/{item}/variants/{itemVariant}', [ItemController::class, 'updateVariant'])->name('items.updateVariant');
        Route::delete('/items/{item}/variants/{itemVariant}', [ItemController::class, 'destroyVariant'])->name('items.destroyVariant');
        Route::post('/item-categories', [ItemController::class, 'storeCategory'])->name('item-categories.store');
        Route::patch('/item-categories/{itemCategory}', [ItemController::class, 'updateCategory'])->name('item-categories.update');
        Route::delete('/item-categories/{itemCategory}', [ItemController::class, 'destroyCategory'])->name('item-categories.destroy');

        // Stock Input
        Route::get('/stock-input', [StockInputController::class, 'index'])->name('stock-input.index');
        Route::post('/stock-input/{variant}/set', [StockInputController::class, 'upsert'])->name('stock-input.upsert');
        Route::delete('/stock-input/entries/{stockLedger}', [StockInputController::class, 'destroy'])->name('stock-input.destroy');
        Route::get('/stock-input/export', [StockInputController::class, 'export'])->name('stock-input.export');
        Route::post('/stock-input/import', [StockInputController::class, 'import'])->name('stock-input.import');
    });
});

require __DIR__.'/auth.php';