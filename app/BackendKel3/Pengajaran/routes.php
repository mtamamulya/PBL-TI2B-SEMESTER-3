<?php

use App\BackendKel3\Pengajaran\DosenPengajaranController;
use Illuminate\Support\Facades\Route;

Route::get('pengajaran/opsi', [DosenPengajaranController::class, 'opsiFormApi'])->name('api.pengajaran.opsi');
Route::apiResource('pengajaran', DosenPengajaranController::class)
    ->only(['index', 'store', 'show', 'update'])
    ->names('api.pengajaran');
