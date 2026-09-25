<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'authenticate']);
});

Route::middleware('auth')->group(function () {
    // Dashboard Utama
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/api/gps-data', [DashboardController::class, 'getApiData'])->name('api.gps-data');

    // Manajemen Armada
    Route::get('/devices', [DashboardController::class, 'listDevices'])->name('devices.index');
    Route::get('/devices/create', [DashboardController::class, 'create'])->name('devices.create');
    Route::post('/devices', [DashboardController::class, 'store'])->name('devices.store');
    Route::delete('/devices/{id}', [DashboardController::class, 'destroy'])->name('devices.destroy');

    // History Perjalanan
    Route::get('/device/{imei}/history', [DashboardController::class, 'history'])->name('devices.history');
    Route::get('/api/history/{imei}', [DashboardController::class, 'getHistoryApi'])->name('api.history');

    // Command Bridge & Command Center (Mendukung GET & POST untuk kompatibilitas penuh)
    Route::match(['get', 'post'], '/api/send-command', [DashboardController::class, 'sendCommand'])->name('api.send-command');
    Route::get('/super-admin', [DashboardController::class, 'super_admin'])->name('command_center');

    // WhatsApp Proxy
    Route::post('/proxy-wa', [DashboardController::class, 'sendProxy'])->name('proxy-wa');

    // Manajemen Verifikasi Titik Parkir
    Route::get('/management/verifikasi', [DashboardController::class, 'indexVerifikasi'])->name('verifikasi.index');
    Route::get('/management/verifikasi/data', [DashboardController::class, 'getDataVerifikasi'])->name('verifikasi.data');
    Route::post('/management/verifikasi/simpan', [DashboardController::class, 'simpanVerifikasi'])->name('verifikasi.simpan');
    Route::get('/management/verifikasi/export', [DashboardController::class, 'exportVerifikasi'])->name('verifikasi.export');

    // Rute Logout
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});

Route::get('/', function () {
    return redirect('/dashboard');
});