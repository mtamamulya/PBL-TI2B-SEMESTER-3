<?php

use App\Http\Controllers\Api\PrestasiController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', function () {
    return response()->json([
        'success' => true,
        'message' => 'Service Kelompok 3 Aktif',
        'data' => [
            'status' => 'ok',
            'service' => 'sikap-kelompok3',
            'time' => now()->toIso8601String(),
        ],
    ], 200);
});

Route::prefix('integrasi')
    ->middleware('auth:sanctum')
    ->group(function () {
    });

require app_path('BackendKel3/routes.php');
require app_path('BackendKel1/routes.php');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/prestasi', [PrestasiController::class, 'index']);
    Route::post('/prestasi', [PrestasiController::class, 'store']);
    Route::get('/prestasi/{id}', [PrestasiController::class, 'show']);
    Route::put('/prestasi/{id}', [PrestasiController::class, 'update']);
    Route::delete('/prestasi/{id}', [PrestasiController::class, 'destroy']);
});