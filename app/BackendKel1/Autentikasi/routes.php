<?php

use App\BackendKel1\Autentikasi\AuthController;
use App\BackendKel1\Autentikasi\PemulihanController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1')->name('api.login');

// Lupa sandi (publik, tanpa token)
Route::prefix('lupa-sandi')->group(function () {
    Route::post('kirim-otp', [PemulihanController::class, 'kirimOtp'])->middleware('throttle:5,1')->name('api.lupa-sandi.kirim-otp');
    Route::post('verifikasi', [PemulihanController::class, 'verifikasi'])->name('api.lupa-sandi.verifikasi');
    Route::post('simpan', [PemulihanController::class, 'simpan'])->name('api.lupa-sandi.simpan');
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('api.logout');

    Route::get('me', function (Request $request) {
        return response()->json([
            'success' => true,
            'message' => 'Data profil user berhasil diambil',
            'data' => $request->user(),
        ], 200);
    })->name('api.me');

    // Ganti sandi dari halaman profil
    Route::post('profil/ganti-sandi', [PemulihanController::class, 'gantiSandiProfil'])->name('api.profil.ganti-sandi');
    Route::post('profil/ganti-sandi/kirim-otp', [PemulihanController::class, 'kirimOtpProfil'])->middleware('throttle:5,1')->name('api.profil.ganti-sandi.kirim-otp');
    Route::post('profil/ganti-sandi/verifikasi', [PemulihanController::class, 'verifikasiOtpProfil'])->name('api.profil.ganti-sandi.verifikasi');
    Route::post('profil/ganti-sandi/simpan', [PemulihanController::class, 'simpanSandiProfilOtp'])->name('api.profil.ganti-sandi.simpan');
});