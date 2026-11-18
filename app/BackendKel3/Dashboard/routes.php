<?php

use App\BackendKel3\Dashboard\DosenDashboardController;
use App\Http\Middleware\PastikanDosenAktif;
use Illuminate\Support\Facades\Route;

Route::prefix('dosen')
    ->name('dosen.')
    // Aktifkan middleware berikut saat autentikasi sesi penuh diberlakukan:
    // ->middleware(['auth', PastikanDosenAktif::class])
    ->group(function () {
        Route::get('/dashboard', [DosenDashboardController::class, 'index'])
            ->name('dashboard');
    });