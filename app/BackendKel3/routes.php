<?php

use App\Http\Controllers\BackendKel3\DokumenSurat\DokumenSuratController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/dosen/pengajaran/{id}/dokumen', [DokumenSuratController::class, 'index']);
    Route::post('/dosen/pengajaran/{id}/dokumen', [DokumenSuratController::class, 'store']);
    Route::delete('/dosen/pengajaran/{id}/dokumen/{dokumenId}', [DokumenSuratController::class, 'destroy']);
    Route::get('/dosen/pengajaran/{id}/surat', [DokumenSuratController::class, 'showSurat']);
});

Route::get('/pengajaran/opsi', [DokumenSuratController::class, 'opsi']);
Route::get('/pengajuan/dosen/{id}/dokumen', [DokumenSuratController::class, 'index']);
Route::get('/pengajuan/dosen/{id}/surat', [DokumenSuratController::class, 'showSurat']);

Route::get('/dosen/pengajuan/{id}/dokumen', [DokumenSuratController::class, 'index']);
Route::get('/dosen/pengajuan/{id}/surat', [DokumenSuratController::class, 'showSurat']);