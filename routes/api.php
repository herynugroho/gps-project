<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController; // Sesuaikan dengan nama controller Anda

Route::post('/proxy-wa', [DashboardController::class, 'sendProxy'])->middleware('throttle:60,1');

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');
