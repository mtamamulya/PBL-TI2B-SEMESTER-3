<?php

use App\BackendKel3\Pengajuan\PengajuanController;
use Illuminate\Support\Facades\Route;

// Wajib menggunakan middleware auth agar $request->user() tidak error
// Sesuaikan 'auth:sanctum' dengan sistem autentikasi yang Anda gunakan
Route::middleware(['auth:sanctum'])->prefix('dosen/pengajuan')->group(function () {
    Route::get('/', [PengajuanController::class, 'index']);
    Route::get('/{id}/timeline', [PengajuanController::class, 'timeline']);
    Route::post('/{id}/ajukan', [PengajuanController::class, 'ajukan']);
    Route::post('/{id}/ajukan-ulang', [PengajuanController::class, 'ajukanUlang']);
});
