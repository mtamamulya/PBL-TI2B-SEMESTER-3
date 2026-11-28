<?php

use App\BackendKel1\Berita\BeritaController;
use Illuminate\Support\Facades\Route;

// Baca publik (landing page memakai tanpa token; pengguna login mendapat semua status sesuai peran)
Route::get('berita', [BeritaController::class, 'index'])->name('api.berita.index');
Route::get('berita/{id}', [BeritaController::class, 'show'])->whereNumber('id')->name('api.berita.show');

// Endpoint terautentikasi (dropdown form, upload gambar konten, tambah, ubah, tinjau, hapus)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('berita/opsi', [BeritaController::class, 'opsi'])->name('api.berita.opsi');
    Route::post('berita/gambar-konten', [BeritaController::class, 'uploadGambarKonten'])->name('api.berita.upload-gambar');
    Route::post('berita', [BeritaController::class, 'store'])->name('api.berita.store');
    Route::put('berita/{id}', [BeritaController::class, 'update'])->whereNumber('id')->name('api.berita.update');
    Route::patch('berita/{id}/tinjau', [BeritaController::class, 'tinjau'])->whereNumber('id')->name('api.berita.tinjau');
    Route::delete('berita/{id}', [BeritaController::class, 'destroy'])->whereNumber('id')->name('api.berita.destroy');
});
