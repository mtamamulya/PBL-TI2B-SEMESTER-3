<?php

use App\BackendKel2\Dokumen\DokumenController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('kegiatan/{kegiatan}')->whereNumber('kegiatan')->name('api.backendkel2.kegiatan.')->group(function () {
    Route::get('/dokumen-hasil', [DokumenController::class, 'hasil'])->name('dokumen.hasil');
    Route::get('/dokumen', [DokumenController::class, 'index'])->name('dokumen.index');
    Route::post('/dokumen', [DokumenController::class, 'store'])->name('dokumen.store');
    Route::get('/dokumen/{dokumen}', [DokumenController::class, 'show'])->whereNumber('dokumen')->name('dokumen.show');
    Route::delete('/dokumen/{dokumen}', [DokumenController::class, 'destroy'])->whereNumber('dokumen')->name('dokumen.destroy');
});
