<?php

use App\BackendKel2\Prestasi\PrestasiController;
use App\Http\Middleware\MahasiswaOnly;
use Illuminate\Support\Facades\Route;

Route::middleware(MahasiswaOnly::class)->group(function () {
    Route::post('/mahasiswa/prestasi', [PrestasiController::class, 'simpan'])
        ->name('api.mahasiswa.prestasi.simpan');
});
