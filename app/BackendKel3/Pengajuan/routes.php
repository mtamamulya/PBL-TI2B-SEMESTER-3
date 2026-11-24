<?php

use App\BackendKel3\Pengajuan\PengajuanController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum'])->prefix('pengajuan')->group(function () {
    Route::get('/', [PengajuanController::class, 'index']);
    Route::get('/{id}/timeline', [PengajuanController::class, 'timeline']);
    Route::post('/{id}/ajukan', [PengajuanController::class, 'ajukan']);
    Route::post('/{id}/ajukan-ulang', [PengajuanController::class, 'ajukanUlang']);
});
