<?php

use App\BackendKel2\Dashboard\DashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->name('api.backendkel2.')->group(function () {
    Route::get('/dosen/dashboard/kegiatan', [DashboardController::class, 'kegiatan'])->name('dosen.dashboard.kegiatan');
    Route::get('/mahasiswa/dashboard', [DashboardController::class, 'mahasiswa'])->name('mahasiswa.dashboard');
});
