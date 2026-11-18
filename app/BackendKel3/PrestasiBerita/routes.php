<?php

use App\BackendKel3\PrestasiBerita\PrestasiController;
use App\BackendKel3\PrestasiBerita\BeritaController;
use Illuminate\Support\Facades\Route;

Route::prefix('prestasi')->name('prestasi.')->group(function () {
    Route::get('/', [PrestasiController::class, 'index'])->name('index');
    Route::get('opsi', [PrestasiController::class, 'opsi'])->name('opsi');
    Route::post('/', [PrestasiController::class, 'store'])->name('store');
    Route::get('{id}', [PrestasiController::class, 'show'])->whereNumber('id')->name('show');
    Route::match(['put', 'patch'], '{id}', [PrestasiController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('{id}', [PrestasiController::class, 'destroy'])->whereNumber('id')->name('destroy');
});

Route::prefix('berita')->name('berita.')->group(function () {
    Route::get('/', [BeritaController::class, 'index'])->name('index');
    Route::get('opsi', [BeritaController::class, 'opsi'])->name('opsi');
    Route::post('/', [BeritaController::class, 'store'])->name('store');
    Route::get('{id}', [BeritaController::class, 'show'])->whereNumber('id')->name('show');
    Route::match(['put', 'patch'], '{id}', [BeritaController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('{id}', [BeritaController::class, 'destroy'])->whereNumber('id')->name('destroy');
});