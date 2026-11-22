<?php

use Illuminate\Support\Facades\Route;
use App\BackendKel3\DokumenSurat\DokumenSuratController;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dosen/pengajuan/{id}/dokumen', [DokumenSuratController::class, 'index']);
    Route::post('/dosen/pengajuan/{id}/dokumen', [DokumenSuratController::class, 'store']);
    Route::delete('/dosen/pengajuan/{id}/dokumen/{dokumenId}', [DokumenSuratController::class, 'destroy']);
    Route::get('/dosen/pengajuan/{id}/surat', [DokumenSuratController::class, 'showSurat']);
});