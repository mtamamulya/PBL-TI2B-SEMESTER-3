<?php

use App\BackendKel2\MataKuliah\MataKuliahController;
use Illuminate\Support\Facades\Route;

// Endpoint khusus modul Mata Kuliah Mahasiswa
Route::prefix('mahasiswa')->group(function () {
    Route::get('/katalog-mk', [MataKuliahController::class, 'katalogMataKuliah']);
    Route::get('/semester-berjalan', [MataKuliahController::class, 'semesterBerjalan']);
    Route::get('/riwayat', [MataKuliahController::class, 'riwayatAkademik']);
});
