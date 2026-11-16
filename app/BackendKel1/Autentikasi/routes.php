<?php

use App\BackendKel1\Autentikasi\AuthController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('api.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('/me', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Data profil user berhasil diambil',
            'data' => $request->user(),
        ], 200);
    });
});
