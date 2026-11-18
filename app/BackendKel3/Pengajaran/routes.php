<?php

use App\BackendKel3\Pengajaran\DosenPengajaranController;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::middleware(EnsureFrontendRequestsAreStateful::class)->group(function () {
    Route::get('pengajaran/opsi', [DosenPengajaranController::class, 'opsiFormApi'])->name('api.pengajaran.opsi');
    Route::apiResource('pengajaran', DosenPengajaranController::class)
        ->only(['index', 'store', 'show', 'update'])
        ->names('api.pengajaran');
});
