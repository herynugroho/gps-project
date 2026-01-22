<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Di sini kita mendaftarkan semua rute untuk aplikasi GPS Tracker.
|
*/

// 1. Dashboard Utama (Peta Besar)
Route::get('/', [DashboardController::class, 'index']);

// 2. API Data Real-time (Digunakan oleh Dashboard Utama)
Route::get('/api/gps-data', [DashboardController::class, 'getApiData']);

// --- RUTE BARU UNTUK FITUR HISTORY ---

// 3. Halaman Detail History (UI)
// Contoh URL: /device/1234567890/history
Route::get('/device/{imei}/history', [DashboardController::class, 'history']);

// 4. API Data History (Digunakan oleh Halaman History untuk gambar garis)
// Contoh URL: /api/history/1234567890
Route::get('/api/history/{imei}', [DashboardController::class, 'getHistoryApi']);

// --- MANAJEMEN PERANGKAT (BARU) ---
// 5. Halaman Form Tambah Device
Route::get('/devices/create', [DashboardController::class, 'create'])->name('devices.create');

// 6. Proses Simpan Data ke Database
Route::post('/devices', [DashboardController::class, 'store'])->name('devices.store');