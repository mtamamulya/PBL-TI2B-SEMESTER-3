<?php

use App\BackendKel1\Dosen\DosenController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

Route::prefix('dosen')->group(function () {
    // Bisa diakses publik / lintas kelompok (Kelompok 2 & 3)
    Route::get('/', [DosenController::class, 'index'])->name('api.dosen.index');
    Route::get('/{id}', [DosenController::class, 'show'])->name('api.dosen.show');

    // Hanya Admin yang bisa menambah dosen baru
    Route::post('/', [DosenController::class, 'store'])
        ->middleware(['auth:sanctum', AdminOnly::class])
        ->name('api.dosen.store');

    // Admin atau dosen yang bersangkutan bisa mengedit
    Route::put('/{id}', [DosenController::class, 'update'])
        ->middleware(['auth:sanctum'])
        ->name('api.dosen.update');
});
