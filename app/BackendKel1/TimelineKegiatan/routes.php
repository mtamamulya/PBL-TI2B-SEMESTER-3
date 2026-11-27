<?php

use App\BackendKel1\TimelineKegiatan\TimelineKegiatanController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/mahasiswa/timeline', [TimelineKegiatanController::class, 'mahasiswa'])->name('api.mahasiswa.timeline');
    Route::get('/dosen/timeline', [TimelineKegiatanController::class, 'dosen'])->name('api.dosen.timeline');
});
