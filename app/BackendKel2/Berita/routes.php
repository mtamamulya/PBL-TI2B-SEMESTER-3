<?php

use App\BackendKel2\Berita\AdminBeritaController;
use App\BackendKel2\Berita\BeritaController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->name('api.backendkel2.')->group(function () {
    Route::get('/berita/kategori', [BeritaController::class, 'categories'])->name('berita.kategori.index');

    Route::prefix('dosen')->name('dosen.')->group(function () {
        Route::get('/berita', [BeritaController::class, 'index'])->name('berita.index');
        Route::post('/berita', [BeritaController::class, 'store'])->name('berita.store');
        Route::get('/berita/{berita}', [BeritaController::class, 'show'])->whereNumber('berita')->name('berita.show');
        Route::match(['put', 'patch'], '/berita/{berita}', [BeritaController::class, 'update'])->whereNumber('berita')->name('berita.update');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::get('/berita', [AdminBeritaController::class, 'index'])->name('berita.index');
        Route::patch('/berita/{berita}/review', [AdminBeritaController::class, 'review'])->whereNumber('berita')->name('berita.review');
    });
});
