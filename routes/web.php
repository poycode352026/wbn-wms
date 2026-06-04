<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DepartmentController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\EmployeePortalController;
use App\Http\Controllers\GoodsIssueController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\ItemController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OperatorController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\StockInputController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\LowStockController;
use App\Http\Controllers\VehicleController;
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

    // ── Notifications ──────────────────────────────────────────────────────────
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post('/notifications/read-all',            [NotificationController::class, 'markAllRead'])->name('notifications.read-all');

    // Impersonate routes (super_admin only — guarded in controller)
    Route::post('/users/{user}/impersonate', [UserController::class, 'impersonate'])->name('users.impersonate');
    Route::post('/users/stop-impersonate', [UserController::class, 'stopImpersonate'])->name('users.stop-impersonate');

    // ── Permissions page — super_admin only (too sensitive to delegate via DB perms) ─────
    Route::middleware('role:super_admin')->group(function () {
        Route::get('/permissions', [PermissionController::class, 'index'])->name('permissions.index');
        Route::post('/permissions/{role}/save', [PermissionController::class, 'save'])->name('permissions.save');
    });

    // ── Users management ───────────────────────────────────────────────────────────────
    Route::middleware('permission:users')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // ── Departments management ─────────────────────────────────────────────────────────
    Route::middleware('permission:departments')->group(function () {
        Route::resource('departments', DepartmentController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('/departments/{department}/assign-admin', [DepartmentController::class, 'assignAdmin'])->name('departments.assignAdmin');
        Route::delete('/departments/{department}/remove-admin', [DepartmentController::class, 'removeAdmin'])->name('departments.removeAdmin');
    });

    // ── Warehouses ─────────────────────────────────────────────────────────────────────
    Route::middleware('permission:warehouses')->group(function () {
        Route::resource('warehouses', WarehouseController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/warehouse/{code}', [WarehouseController::class, 'warehouseView'])->name('warehouses.view');
    });

    // ── Locations / Rack Management ────────────────────────────────────────────────────
    Route::middleware('permission:locations')->group(function () {
        Route::get('/locations/labels-data', [LocationController::class, 'labelsData'])->name('locations.labels-data');
        Route::resource('locations', LocationController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::get('/rack/{code}', [LocationController::class, 'rackView'])->name('locations.rack-view');
    });

    // ── Item Master ────────────────────────────────────────────────────────────────────
    Route::middleware('permission:itemMaster')->group(function () {
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
    });

    // ── Goods Receipt ──────────────────────────────────────────────────────────────────
    Route::middleware('permission:goodsReceipt')->group(function () {
        Route::get('/goods-receipts',                [GoodsReceiptController::class, 'index']  )->name('gr.index');
        Route::get('/goods-receipts/create',         [GoodsReceiptController::class, 'create'] )->name('gr.create');
        Route::post('/goods-receipts',               [GoodsReceiptController::class, 'store']  )->name('gr.store');
        Route::get('/goods-receipts/{gr}',           [GoodsReceiptController::class, 'show']   )->name('gr.show');
        Route::patch('/goods-receipts/{gr}',         [GoodsReceiptController::class, 'update'] )->name('gr.update');
        Route::delete('/goods-receipts/{gr}',        [GoodsReceiptController::class, 'destroy'])->name('gr.destroy');
        Route::post('/goods-receipts/{gr}/submit',   [GoodsReceiptController::class, 'submit'] )->name('gr.submit');
        Route::post('/goods-receipts/{gr}/inspect',  [GoodsReceiptController::class, 'inspect'])->name('gr.inspect');
        Route::post('/goods-receipts/{gr}/approve',  [GoodsReceiptController::class, 'approve'])->name('gr.approve');
    });

    // ── Goods Issue ───────────────────────────────────────────────────────────────────
    Route::middleware('permission:goodsIssue')->group(function () {
        Route::get('/goods-issues',                        [GoodsIssueController::class, 'index']        )->name('gi.index');
        Route::get('/goods-issues/create',                 [GoodsIssueController::class, 'create']       )->name('gi.create');
        Route::post('/goods-issues',                       [GoodsIssueController::class, 'store']        )->name('gi.store');
        Route::get('/goods-issues/{gi}',                   [GoodsIssueController::class, 'show']         )->name('gi.show');
        Route::post('/goods-issues/{gi}/submit',           [GoodsIssueController::class, 'submit']       )->name('gi.submit');
        Route::post('/goods-issues/{gi}/approve',          [GoodsIssueController::class, 'approve']      )->name('gi.approve');
        Route::post('/goods-issues/{gi}/reject',           [GoodsIssueController::class, 'reject']       )->name('gi.reject');
        Route::post('/goods-issues/{gi}/assign',           [GoodsIssueController::class, 'assign']       )->name('gi.assign');
        Route::post('/goods-issues/{gi}/start-picking',    [GoodsIssueController::class, 'startPicking'] )->name('gi.start-picking');
        Route::post('/goods-issues/{gi}/submit-picking',   [GoodsIssueController::class, 'submitPicking'])->name('gi.submit-picking');
        Route::post('/goods-issues/{gi}/pickup',           [GoodsIssueController::class, 'pickup']       )->name('gi.pickup');
        Route::delete('/goods-issues/{gi}',                [GoodsIssueController::class, 'destroy']       )->name('gi.destroy');
        Route::delete('/gi-photos/{photo}',                [GoodsIssueController::class, 'deletePhoto']   )->name('gi.photos.destroy');
    });

    // ── LV (Vehicle) Management ───────────────────────────────────────────────────────
    Route::middleware('permission:vehicles')->group(function () {
        Route::get('/vehicles/import-template', [VehicleController::class, 'importTemplate'])->name('vehicles.importTemplate');
        Route::post('/vehicles/import',         [VehicleController::class, 'import'])->name('vehicles.import');
        Route::resource('vehicles', VehicleController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // ── Employee Management ────────────────────────────────────────────────────────────
    Route::middleware('permission:employees')->group(function () {
        Route::get('/employees/import-template', [EmployeeController::class, 'importTemplate'])->name('employees.importTemplate');
        Route::post('/employees/import',         [EmployeeController::class, 'import'])->name('employees.import');
        Route::post('/employees/{employee}/create-login',  [EmployeeController::class, 'createLogin'])->name('employees.createLogin');
        Route::delete('/employees/{employee}/revoke-login', [EmployeeController::class, 'revokeLogin'])->name('employees.revokeLogin');
        Route::post('/employees/{employee}/assign-lv',     [EmployeeController::class, 'assignLv'])->name('employees.assignLv');
        Route::resource('employees', EmployeeController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    // ── Operator Scan ─────────────────────────────────────────────────────────────────
    Route::middleware('role:operator,wh_admin')->prefix('operator')->name('operator.')->group(function () {
        Route::get('/scan',                        [OperatorController::class, 'scanList']    )->name('scan-list');
        Route::get('/scan/{goodsIssue}',           [OperatorController::class, 'scanDetail']  )->name('scan-detail');
        Route::post('/scan/{goodsIssue}/start',    [OperatorController::class, 'startPicking'])->name('start-picking');
        Route::post('/scan/{goodsIssue}/submit',   [OperatorController::class, 'submitPickup'])->name('submit-pickup');
        Route::post('/scan/{goodsIssue}/confirm',  [OperatorController::class, 'confirmPickup'])->name('confirm-pickup');
        Route::get('/history',                     [OperatorController::class, 'history']     )->name('history');
    });

    // ── Employee Portal ────────────────────────────────────────────────────────────────
    Route::middleware('role:employee')->prefix('portal')->name('portal.')->group(function () {
        Route::get('/',         [EmployeePortalController::class, 'dashboard'])->name('dashboard');
        Route::post('/request', [EmployeePortalController::class, 'submitRequest'])->name('submitRequest');
        Route::get('/history',  [EmployeePortalController::class, 'history'])->name('history');
        Route::get('/profile',  [EmployeePortalController::class, 'profile'])->name('profile');
    });

    // ── Employee Requests (Admin processes) ────────────────────────────────────────────
    Route::middleware('permission:employees')->group(function () {
        Route::patch('/employee-requests/{employeeRequest}/process', [EmployeeController::class, 'processRequest'])->name('employee-requests.process');
    });

    // ── Low Stock Alert ────────────────────────────────────────────────────────────────
    Route::get('/low-stock', [LowStockController::class, 'index'])->name('low-stock.index');

    // ── Stock Input ────────────────────────────────────────────────────────────────────
    Route::middleware('permission:itemMaster')->group(function () {
        Route::get('/stock-input', [StockInputController::class, 'index'])->name('stock-input.index');
        Route::post('/stock-input/{variant}/set', [StockInputController::class, 'upsert'])->name('stock-input.upsert');
        Route::post('/stock-input/bulk', [StockInputController::class, 'bulkUpsert'])->name('stock-input.bulk');
        Route::delete('/stock-input/entries/{stockLedger}', [StockInputController::class, 'destroy'])->name('stock-input.destroy');
        Route::get('/stock-input/export', [StockInputController::class, 'export'])->name('stock-input.export');
        Route::post('/stock-input/import', [StockInputController::class, 'import'])->name('stock-input.import');
    });
});

require __DIR__.'/auth.php';
