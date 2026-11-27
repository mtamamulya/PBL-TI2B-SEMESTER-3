<?php

use App\BackendKel1\Autentikasi\PemulihanController;
use App\BackendKel3\Profil\ProfilController;
use Illuminate\Support\Facades\Route;

// Daftarkan endpoint Profil di sini. Prefix /api/v1/dosen berasal dari router Kelompok 3.
Route::get('/profil', [ProfilController::class, 'show'])->name('profil.show');
Route::patch('/profil', [ProfilController::class, 'update'])->name('profil.update');
Route::get('/riwayat-pangkat', [ProfilController::class, 'riwayatPangkat'])->name('profil.riwayat-pangkat');

Route::prefix('profil/ganti-sandi')->name('profil.ganti-sandi.')->group(function () {
    Route::post('/kirim-otp', [PemulihanController::class, 'kirimOtpProfil'])->middleware('throttle:5,1')->name('kirim-otp');
    Route::post('/verifikasi', [PemulihanController::class, 'verifikasiOtpProfil'])->name('verifikasi');
    Route::post('/simpan', [ProfilController::class, 'simpanSandi'])->name('simpan');
});
