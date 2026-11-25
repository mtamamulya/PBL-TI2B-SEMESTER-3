<?php

use App\BackendKel1\Kelas\KelasController;
use Illuminate\Support\Facades\Route;

// Publik & lintas kelompok (dibutuhkan Kelompok 3 untuk modul Pengajaran)
Route::prefix('kelas')->group(function () {
    Route::get('/', [KelasController::class, 'index'])->name('api.kelas.index');
    Route::get('/{id}', [KelasController::class, 'show'])->name('api.kelas.show');
});
