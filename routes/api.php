<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\UnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user',[AuthController::class, 'profile']);
    Route::post('/user', [AuthController::class, 'updateProfile']);
    Route::post('/logout', [AuthController::class, 'logout']);

//    category
    Route::get('/categories', [\App\Http\Controllers\CategoryController::class, 'index']);
    Route::post('/categories', [\App\Http\Controllers\CategoryController::class, 'store']);
    Route::delete('/categories/{category}', [\App\Http\Controllers\CategoryController::class, 'destroy']);

//    unit
    Route::get('/units', [UnitController::class, 'index']);
    Route::post('/units', [UnitController::class, 'store']);
    Route::delete('/units/{unit}', [UnitController::class, 'destroy']);
//    products
    Route::apiResource('products', \App\Http\Controllers\ProductController::class);
});
