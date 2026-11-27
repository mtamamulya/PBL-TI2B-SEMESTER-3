<?php

use App\BackendKel2\Prestasi\PrestasiController;
use App\Http\Middleware\MahasiswaOnly;
use Illuminate\Support\Facades\Route;

Route::middleware(MahasiswaOnly::class)->group(function () {
    Route::get('/mahasiswa/prestasi', [PrestasiController::class, 'index'])
        ->name('api.mahasiswa.prestasi.index');
    Route::post('/mahasiswa/prestasi', [PrestasiController::class, 'simpan'])
        ->name('api.mahasiswa.prestasi.simpan');
});
