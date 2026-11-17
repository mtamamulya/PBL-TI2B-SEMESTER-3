<?php

use App\BackendKel1\TahunAkademik\TahunAkademikController;
use Illuminate\Support\Facades\Route;

// Publik / Read-Only
Route::get('/tahun-akademik', [TahunAkademikController::class, 'index']);
Route::get('/tahun-akademik/aktif', [TahunAkademikController::class, 'aktif']);

// Terproteksi Sanctum (Validasi khusus Admin ada di controller)
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/tahun-akademik', [TahunAkademikController::class, 'store']);
});
