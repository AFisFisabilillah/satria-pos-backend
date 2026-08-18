<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockSupplyController;
use App\Http\Controllers\SuplierController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\VoucherController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    // Both roles
    Route::middleware('role:super_admin,cashier')->group(function () {
        Route::get('/user', [AuthController::class, 'profile']);
        Route::post('/user', [AuthController::class, 'updateProfile']);
        Route::post('/logout', [AuthController::class, 'logout']);

        // Products (Read-only)
        Route::get('/products', [ProductController::class, 'index']);
        Route::get('/products/{product}', [ProductController::class, 'show']);

        // Categories & Units (Read-only for POS filtering)
        Route::get('/categories', [CategoryController::class, 'index']);
        Route::get('/units', [UnitController::class, 'index']);

        // Transactions
        Route::post('/checkout', [\App\Http\Controllers\TransactionController::class, 'checkout']);
        Route::get('/transactions', [\App\Http\Controllers\TransactionController::class, 'index']);
        Route::get('/transactions/{transaction}', [\App\Http\Controllers\TransactionController::class, 'show']);

        // Voucher (Read-only)
        Route::get('/voucher', [VoucherController::class, 'index']);
        Route::get('/voucher/{voucher}', [VoucherController::class, 'show']);

        // Members (Cashier can manage at POS)
        Route::patch('/members/{member}/toggle-active', [\App\Http\Controllers\MemberController::class, 'toggleActive']);
        Route::patch('/members/bulk-toggle-active', [\App\Http\Controllers\MemberController::class, 'bulkToggleMember']);
        Route::apiResource('members', \App\Http\Controllers\MemberController::class);
    });

    // Super Admin Only
    Route::middleware('role:super_admin')->group(function () {
        // Users (Super Admin only CRUD)
        Route::apiResource('users', UserController::class);

        // Categories (Write)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);
        Route::patch('/categories/{category}', [CategoryController::class, 'update']);
        // Units (Write)
        Route::post('/units', [UnitController::class, 'store']);
        Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
        Route::patch('/units/{unit}', [UnitController::class, 'update']);

        // Products (Write)
        Route::post('/products/bulk-toggle-active', [ProductController::class, 'bulkToggleActive']);
        Route::post('/products', [ProductController::class, 'store']);
        Route::post('/products/{product}', [ProductController::class, 'update']);
        Route::delete('/products/{product}', [ProductController::class, 'destroy']);
        Route::patch('/products/{product}/toggle-active', [ProductController::class, 'toggleActive']);

        // Supliers (All)
        Route::apiResource('supliers', SuplierController::class);
        Route::post('/supliers/bulk-delete', [SuplierController::class, 'bulkDelete']);

        // Stock Supply (All)
        Route::get('/stock-supply', [StockSupplyController::class, 'index']);
        Route::post('/stock-supply', [StockSupplyController::class, 'store']);
        Route::get('/stock-supply/{stockSupply}', [StockSupplyController::class, 'show']);
        Route::put('/stock-supply/{stockSupply}', [StockSupplyController::class, 'update']);
        Route::delete('/stock-supply/{stockSupply}', [StockSupplyController::class, 'destroy']);
        Route::delete('/stock-supply-items/{stockSupplyItem}', [\App\Http\Controllers\StockSupplyItemController::class, 'destroy']);

        // Voucher (Write)
        Route::post('/voucher', [VoucherController::class, 'store']);
        Route::put('/voucher/{voucher}', [VoucherController::class, 'update']);
        Route::delete('/voucher/{voucher}', [VoucherController::class, 'destroy']);
        Route::patch('/voucher/{voucher}/toggle-active', [VoucherController::class, 'toggleActive']);
        Route::patch('/voucher/bulk-toggle-active', [VoucherController::class, 'bulkToggleActive']);
        // Supplier Returns (Write & Read)
        Route::get('/supplier-returns', [\App\Http\Controllers\SupplierReturnController::class, 'index']);
        Route::post('/supplier-returns', [\App\Http\Controllers\SupplierReturnController::class, 'store']);
        Route::get('/supplier-returns/{supplierReturn}', [\App\Http\Controllers\SupplierReturnController::class, 'show']);
    });
});
