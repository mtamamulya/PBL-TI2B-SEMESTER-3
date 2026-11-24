<?php

use App\BackendKel2\Prestasi\PrestasiController;
use App\Http\Middleware\MahasiswaOnly;
use Illuminate\Support\Facades\Route;

Route::middleware(MahasiswaOnly::class)->group(function () {
    Route::post('/mahasiswa/prestasi', [PrestasiController::class, 'simpan'])
        ->name('api.mahasiswa.prestasi.simpan');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/mahasiswa/prestasi/bukti/{id}/file', [PrestasiController::class, 'lihatBukti'])
        ->name('api.mahasiswa.prestasi.bukti.file');
});
