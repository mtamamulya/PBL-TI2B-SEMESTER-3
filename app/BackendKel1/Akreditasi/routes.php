<?php

use App\BackendKel1\Akreditasi\AkreditasiController;
use Illuminate\Support\Facades\Route;

Route::get('akreditasi', [AkreditasiController::class, 'index'])->name('api.akreditasi.index');
Route::get('akreditasi/aktif', [AkreditasiController::class, 'aktif'])->name('api.akreditasi.aktif');
Route::get('akreditasi/{id}', [AkreditasiController::class, 'show'])->whereNumber('id')->name('api.akreditasi.show');
