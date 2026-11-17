<?php

use Illuminate\Support\Facades\Route;
use App\BackendKel2\PengajuanMahasiswa\PengajuanMahasiswaController;

Route::prefix('pengajuan-mahasiswa')->group(function () {
    Route::get('/', [PengajuanMahasiswaController::class, 'apiIndex']);
    Route::post('/', [PengajuanMahasiswaController::class, 'apiStore']);
    Route::get('/{id}', [PengajuanMahasiswaController::class, 'apiShow']);
    Route::put('/{id}', [PengajuanMahasiswaController::class, 'apiUpdate']);
    Route::post('/{id}', [PengajuanMahasiswaController::class, 'apiUpdate']); // Untuk update yang disertai file lampiran
    Route::delete('/{id}', [PengajuanMahasiswaController::class, 'apiDestroy']);
});