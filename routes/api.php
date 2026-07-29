<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StockSupplyController;
use App\Http\Controllers\SuplierController;
use App\Http\Controllers\UnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user',[AuthController::class, 'profile']);
    Route::post('/user', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

//    category
    Route::get('/categories', [CategoryController::class, 'index']);
    Route::post('/categories', [CategoryController::class, 'store']);
    Route::delete('/categories/{category}', [CategoryController::class, 'destroy']);

//    unit
    Route::get('/units', [UnitController::class, 'index']);
    Route::post('/units', [UnitController::class, 'store']);
    Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
//    products
    Route::post('/products/bulk-toggle-active', [ProductController::class, 'bulkToggleActive']);
    Route::apiResource('products', ProductController::class);
    Route::patch("/products/{product}/toggle-active", [ProductController::class, 'toggleActive']);
//    supliers
    Route::post('/supliers/bulk-delete', [SuplierController::class, 'bulkDelete']);
    Route::apiResource('supliers', SuplierController::class);

//    Stock Supply
    Route::get("/stock-supply", [StockSupplyController::class, 'index']);
    Route::post("/stock-supply", [StockSupplyController::class, 'store']);
    Route::get("/stock-supply/{stockSupply}", [StockSupplyController::class, 'show']);
    Route::put("/stock-supply/{stockSupply}", [StockSupplyController::class, 'update']);
    Route::delete("/stock-supply/{stockSupply}", [StockSupplyController::class, 'destroy']);
    Route::delete("/stock-supply-items/{stockSupplyItem}", [\App\Http\Controllers\StockSupplyItemController::class, 'destroy']);

//    Transaction
    Route::post("/checkout", [\App\Http\Controllers\TransactionController::class, 'checkout']);

});
