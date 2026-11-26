<?php

use App\BackendKel1\Mahasiswa\MahasiswaController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

Route::prefix('mahasiswa')->group(function () {
    // Publik & lintas kelompok (Kelompok 2 & 3)
    Route::get('/', [MahasiswaController::class, 'index'])->name('api.mahasiswa.index');

    // Khusus Admin
    Route::post('/', [MahasiswaController::class, 'store'])
        ->middleware(['auth:sanctum', AdminOnly::class])
        ->name('api.mahasiswa.store');
    Route::post('/sinkronisasi-kelas', [MahasiswaController::class, 'sinkronisasiKelas'])
        ->middleware(['auth:sanctum', AdminOnly::class])
        ->name('api.mahasiswa.sinkronisasi');

    // Route dengan segment dinamis diletakkan terakhir agar URL statis tidak tertangkap.
    Route::get('/{id}', [MahasiswaController::class, 'show'])->name('api.mahasiswa.show');

    Route::patch('/{id}/status', [MahasiswaController::class, 'toggleStatus'])
        ->middleware(['auth:sanctum', AdminOnly::class])
        ->name('api.mahasiswa.status');

    // Admin atau Mahasiswa pemilik profil
    Route::put('/{id}', [MahasiswaController::class, 'update'])
        ->middleware(['auth:sanctum'])
        ->name('api.mahasiswa.update');
});
