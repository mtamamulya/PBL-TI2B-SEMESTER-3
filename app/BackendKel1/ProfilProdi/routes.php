<?php

use App\BackendKel1\Autentikasi\PeranApi;
use App\BackendKel1\ProfilProdi\ProfilProdiController;
use Illuminate\Support\Facades\Route;

// Baca: PUBLIK (dipakai landing page tanpa login). Tulis: admin (1) atau kaprodi (2).
Route::get('prodi/profil', [ProfilProdiController::class, 'show'])->name('api.prodi.profil.show');

Route::middleware(['auth:sanctum', PeranApi::class.':1,2'])->group(function () {
    Route::put('prodi/profil', [ProfilProdiController::class, 'update'])->name('api.prodi.profil.update');
    Route::post('prodi/profil/logo', [ProfilProdiController::class, 'unggahLogo'])->name('api.prodi.profil.logo.store');
    Route::delete('prodi/profil/logo/{jenis}', [ProfilProdiController::class, 'hapusLogo'])
        ->where('jenis', 'utama|footer')->name('api.prodi.profil.logo.destroy');
});
