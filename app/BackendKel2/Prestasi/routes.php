<?php

use App\BackendKel2\Prestasi\PrestasiController;
use Illuminate\Support\Facades\Route;

// Di dalam middleware auth/mahasiswa jika ada
Route::prefix('mahasiswa/prestasi')->name('mahasiswa.prestasi')->group(function () {
    Route::get('/', [PrestasiController::class, 'index']); // route: mahasiswa.prestasi
    Route::get('/tambah', [PrestasiController::class, 'tambah'])->name('.tambah'); // route: mahasiswa.prestasi.tambah
    Route::post('/simpan', [PrestasiController::class, 'simpan'])->name('.simpan'); // route: mahasiswa.prestasi.simpan
    Route::get('/{id}/edit', [PrestasiController::class, 'edit'])->name('.edit'); // route: mahasiswa.prestasi.edit
    Route::put('/{id}', [PrestasiController::class, 'update'])->name('.update');
    Route::delete('/{id}', [PrestasiController::class, 'hapus'])->name('.hapus');
});