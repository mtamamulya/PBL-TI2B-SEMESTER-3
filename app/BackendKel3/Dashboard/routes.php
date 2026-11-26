<?php

use App\BackendKel3\Dashboard\DashboardApiController;
use App\BackendKel3\Dashboard\NotifikasiController;
use Illuminate\Support\Facades\Route;

// Hanya route API. Dimuat dari BackendKel3/routes.php, di dalam grup
// auth:sanctum + PastikanDosenAktif + prefix "dosen" -> /api/v1/dosen/...
// JANGAN di-require dari routes/web.php (halaman web didaftarkan langsung di sana).
Route::get('/dashboard', [DashboardApiController::class, 'index'])->name('dashboard');

Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi');
Route::post('/notifikasi/baca-semua', [NotifikasiController::class, 'tandaiSemuaDibaca'])->name('notifikasi.baca-semua');
Route::post('/notifikasi/{idTimeline}/baca', [NotifikasiController::class, 'tandaiDibaca'])
    ->whereNumber('idTimeline')
    ->name('notifikasi.baca');
