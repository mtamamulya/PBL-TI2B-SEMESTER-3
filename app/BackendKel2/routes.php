<?php

use App\BackendKel2\Berita\BeritaController as BeritaBackend;
use App\BackendKel2\Laporan\LaporanController as LaporanBackend;
use Illuminate\Support\Facades\Route;

// Integrasi API Backend Kelompok 2
Route::prefix('api/v2')->middleware(['web', 'auth'])->group(function () {
    // Berita
    Route::get('/berita/kategori', [BeritaBackend::class, 'categories']);
    Route::get('/berita', [BeritaBackend::class, 'index']);
    Route::post('/berita', [BeritaBackend::class, 'store']);
    Route::get('/berita/{berita}', [BeritaBackend::class, 'show']);
    Route::post('/berita/{berita}', [BeritaBackend::class, 'update']); // Menggunakan POST untuk override PUT/PATCH di beberapa client

    // Laporan
    Route::get('/laporan', [LaporanBackend::class, 'index']);
    Route::get('/laporan/export', [LaporanBackend::class, 'export']);
});

require __DIR__.'/Berita/routes.php';
require __DIR__.'/Dashboard/routes.php';
require __DIR__.'/Laporan/routes.php';
require __DIR__.'/MataKuliah/routes.php';
require __DIR__.'/PengajuanMahasiswa/routes.php';
require __DIR__.'/Prestasi/routes.php';
require __DIR__.'/Dokumen/routes.php';
