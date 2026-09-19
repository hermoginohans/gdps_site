<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('throttle:google-oauth')->group(function (): void {
    Route::get('/auth/google/redirect', [\App\Http\Controllers\AuthController::class, 'googleRedirect']);
    Route::get('/auth/google/callback', [\App\Http\Controllers\AuthController::class, 'googleCallback']);
});
