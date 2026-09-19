<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\StreamerCodeController;
use Illuminate\Support\Facades\Route;

Route::middleware('throttle:register')->post('/register', [AuthController::class, 'register']);
Route::middleware('throttle:login')->post('/login', [AuthController::class, 'login']);

Route::middleware('auth:sanctum')->group(function (): void {
    Route::get('/admin/streamer-codes', [StreamerCodeController::class, 'index']);
    Route::post('/admin/streamer-codes', [StreamerCodeController::class, 'save']);
    Route::put('/admin/streamer-codes/{id}', [StreamerCodeController::class, 'save'])->whereNumber('id');
    Route::get('/account', [StoreController::class, 'account']);
    Route::get('/admin', [StoreController::class, 'admin']);
    Route::get('/admin/report', [StoreController::class, 'report']);
    Route::post('/admin/products', [StoreController::class, 'save']);
    Route::put('/admin/products/{id}', [StoreController::class, 'save'])->whereNumber('id');
    Route::put('/admin/users/{id}', [StoreController::class, 'updateUser'])->whereNumber('id');
    Route::post('/admin/news', [StoreController::class, 'saveNews']);
    Route::put('/admin/news/{id}', [StoreController::class, 'saveNews'])->whereNumber('id');
    Route::delete('/admin/news/{id}', [StoreController::class, 'deleteNews'])->whereNumber('id');
    Route::put('/admin/support/{id}', [StoreController::class, 'updateSupport'])->whereNumber('id');
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);
});
Route::get('/products', [StoreController::class, 'products']);
Route::post('/support', [StoreController::class, 'submitSupport'])->middleware('throttle:10,1');
Route::post('/streamer-codes/quote', [StreamerCodeController::class, 'quote'])->middleware('throttle:60,1');
Route::get('/news', [StoreController::class, 'news']);
Route::get('/news/{slug}', [StoreController::class, 'newsPost']);
