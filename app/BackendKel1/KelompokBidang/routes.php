<?php

use App\BackendKel1\KelompokBidang\KelompokBidangController;
use Illuminate\Support\Facades\Route;

// Baca: semua pengguna yang login (dosen memakainya di form). Tulis: admin dan kaprodi (dicek di controller).
Route::middleware('auth:sanctum')->group(function () {
    Route::get('kelompok-bidang', [KelompokBidangController::class, 'index'])->name('api.kelompok-bidang.index');
    Route::get('kelompok-bidang/opsi', [KelompokBidangController::class, 'opsi'])->name('api.kelompok-bidang.opsi');
    Route::get('kelompok-bidang/{id}', [KelompokBidangController::class, 'show'])->whereNumber('id')->name('api.kelompok-bidang.show');
    Route::post('kelompok-bidang', [KelompokBidangController::class, 'store'])->name('api.kelompok-bidang.store');
    Route::put('kelompok-bidang/{id}', [KelompokBidangController::class, 'update'])->whereNumber('id')->name('api.kelompok-bidang.update');
    Route::delete('kelompok-bidang/{id}', [KelompokBidangController::class, 'destroy'])->whereNumber('id')->name('api.kelompok-bidang.destroy');
});
