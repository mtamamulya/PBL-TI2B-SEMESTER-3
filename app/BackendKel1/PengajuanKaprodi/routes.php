<?php

use App\BackendKel1\PengajuanKaprodi\PengajuanKaprodiController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('kaprodi/pengajuan')->group(function () {
    Route::get('/', [PengajuanKaprodiController::class, 'index'])->name('api.kaprodi.pengajuan.index');
    Route::get('/{id}', [PengajuanKaprodiController::class, 'show'])->whereNumber('id')->name('api.kaprodi.pengajuan.show');
    Route::post('/{id}/keputusan', [PengajuanKaprodiController::class, 'keputusan'])->whereNumber('id')->name('api.kaprodi.pengajuan.keputusan');
});
