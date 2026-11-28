<?php

use App\BackendKel2\Berita\AdminBeritaController;
use App\BackendKel2\Berita\BeritaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->name('api.backendkel2.')->group(function () {
    Route::get('/berita/kategori', [BeritaController::class, 'categories'])->name('berita.kategori.index');

    // API /dosen/berita dimiliki BackendKel3/PrestasiBerita/routes.php.
    // Jangan daftarkan ulang di sini karena akan menimpa validasi dan middleware dosen.

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/berita', [AdminBeritaController::class, 'index'])->name('berita.index');
        Route::patch('/berita/{berita}/review', [AdminBeritaController::class, 'review'])->whereNumber('berita')->name('berita.review');
    });
});
