<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

// Dashboard Utama
Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
Route::get('/api/gps-data', [DashboardController::class, 'getApiData']);

// Manajemen Armada (Public)
Route::get('/devices', [DashboardController::class, 'listDevices'])->name('devices.index');
Route::get('/devices/create', [DashboardController::class, 'create'])->name('devices.create');
Route::post('/devices', [DashboardController::class, 'store'])->name('devices.store');
Route::delete('/devices/{id}', [DashboardController::class, 'destroy'])->name('devices.destroy');

// History
Route::get('/device/{imei}/history', [DashboardController::class, 'history'])->name('devices.history');