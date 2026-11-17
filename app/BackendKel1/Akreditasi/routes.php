<?php

use App\BackendKel1\Akreditasi\AkreditasiController;
use App\BackendKel1\Autentikasi\PeranApi;
use Illuminate\Support\Facades\Route;

Route::get('akreditasi', [AkreditasiController::class, 'index'])->name('api.akreditasi.index');
Route::get('akreditasi/aktif', [AkreditasiController::class, 'aktif'])->name('api.akreditasi.aktif');
Route::get('akreditasi/{id}', [AkreditasiController::class, 'show'])->whereNumber('id')->name('api.akreditasi.show');

// Tulis: token Sanctum dan khusus peran admin (1).
Route::middleware(['auth:sanctum', PeranApi::class.':1'])->group(function () {
    Route::post('akreditasi', [AkreditasiController::class, 'store'])->name('api.akreditasi.store');
    Route::put('akreditasi/{id}', [AkreditasiController::class, 'update'])->whereNumber('id')->name('api.akreditasi.update');
    Route::delete('akreditasi/{id}', [AkreditasiController::class, 'destroy'])->whereNumber('id')->name('api.akreditasi.destroy');
    Route::post('akreditasi/{id}/aktifkan', [AkreditasiController::class, 'aktifkan'])->whereNumber('id')->name('api.akreditasi.aktifkan');
});
