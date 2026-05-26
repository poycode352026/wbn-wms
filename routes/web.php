<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WarehouseController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard/Index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
    Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/departments/{department}/assign-admin', [DepartmentController::class, 'assignAdmin'])->name('departments.assignAdmin');
    Route::delete('/departments/{department}/remove-admin', [DepartmentController::class, 'removeAdmin'])->name('departments.removeAdmin');
    Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('items', ItemController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/items/{item}/variants', [ItemController::class, 'storeVariant'])->name('items.storeVariant');
    Route::patch('/items/{item}/variants/{itemVariant}', [ItemController::class, 'updateVariant'])->name('items.updateVariant');
    Route::delete('/items/{item}/variants/{itemVariant}', [ItemController::class, 'destroyVariant'])->name('items.destroyVariant');
    Route::post('/item-categories', [ItemController::class, 'storeCategory'])->name('item-categories.store');
    Route::patch('/item-categories/{itemCategory}', [ItemController::class, 'updateCategory'])->name('item-categories.update');
    Route::delete('/item-categories/{itemCategory}', [ItemController::class, 'destroyCategory'])->name('item-categories.destroy');
});

require __DIR__.'/auth.php';