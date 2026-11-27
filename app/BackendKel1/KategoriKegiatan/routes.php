<?php

use App\BackendKel1\KategoriKegiatan\KategoriKegiatanController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

// Baca: semua pengguna yang login (form pengaju memakai isian kategori). Tulis: khusus admin (dicek di controller).
Route::middleware([EnsureFrontendRequestsAreStateful::class, 'auth:sanctum'])->group(function () {
    Route::get('jenis-kegiatan', [KategoriKegiatanController::class, 'jenis'])->name('api.jenis-kegiatan.index');
    Route::get('tipe-isian', [KategoriKegiatanController::class, 'tipeIsian'])->name('api.tipe-isian.index');

    Route::get('kategori-kegiatan', [KategoriKegiatanController::class, 'index'])->name('api.kategori-kegiatan.index');
    Route::get('kategori-kegiatan/{id}', [KategoriKegiatanController::class, 'show'])->whereNumber('id')->name('api.kategori-kegiatan.show');
    Route::post('kategori-kegiatan', [KategoriKegiatanController::class, 'store'])->name('api.kategori-kegiatan.store');
    Route::put('kategori-kegiatan/{id}', [KategoriKegiatanController::class, 'update'])->whereNumber('id')->name('api.kategori-kegiatan.update');
    Route::delete('kategori-kegiatan/{id}', [KategoriKegiatanController::class, 'destroy'])->whereNumber('id')->name('api.kategori-kegiatan.destroy');
});
