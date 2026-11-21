<?php

use App\BackendKel3\PrestasiBerita\PrestasiController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware(EnsureFrontendRequestsAreStateful::class)->prefix('prestasi')->name('prestasi.')->group(function () {
    Route::get('/', [PrestasiController::class, 'index'])->name('index');
    Route::get('opsi', [PrestasiController::class, 'opsi'])->name('opsi');
    Route::post('/', [PrestasiController::class, 'store'])->name('store');
    Route::get('{id}', [PrestasiController::class, 'show'])->whereNumber('id')->name('show');
    Route::match(['put', 'patch'], '{id}', [PrestasiController::class, 'update'])->whereNumber('id')->name('update');
    Route::delete('{id}', [PrestasiController::class, 'destroy'])->whereNumber('id')->name('destroy');
});
