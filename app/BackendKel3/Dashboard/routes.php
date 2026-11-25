<?php

use App\BackendKel3\Dashboard\DashboardApiController;
use App\BackendKel3\Dashboard\NotifikasiController;
use Illuminate\Support\Facades\Route;

// Hanya route API. Dimuat dari BackendKel3/routes.php, di dalam grup
// auth:sanctum + PastikanDosenAktif + prefix "dosen" -> /api/v1/dosen/dashboard dan /api/v1/dosen/notifikasi.
// JANGAN di-require dari routes/web.php (halaman web didaftarkan langsung di sana).
Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('dashboard');
Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
