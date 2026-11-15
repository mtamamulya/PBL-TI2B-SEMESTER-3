<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes - SIKAP Kelompok 3
|--------------------------------------------------------------------------
| Prefix otomatis "/api/v1" (via bootstrap/app.php).
| Contoh: Route::get('/ping') -> GET /api/v1/ping
*/

// Endpoint Health Check (Sesuai API-CONTRACT.md)
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

// Autentikasi API (token Sanctum)

/*
|--------------------------------------------------------------------------
| Integrasi dengan Kelompok 1 (Endpoint Kontrak)
|--------------------------------------------------------------------------
*/
Route::prefix('integrasi')
    ->middleware('auth:sanctum')
    ->group(function () {
        // TODO: Daftarkan endpoint API fitur di sini
    });

require app_path('BackendKel3/routes.php');
require app_path('BackendKel1/routes.php');
