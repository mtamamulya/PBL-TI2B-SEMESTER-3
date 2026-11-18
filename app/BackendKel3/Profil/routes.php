<?php

use App\BackendKel3\Profil\ProfilController;
use Illuminate\Support\Facades\Route;

// Daftarkan endpoint Profil di sini. Prefix /api/v1/dosen berasal dari router Kelompok 3.
Route::get('/profil', [ProfilController::class, 'show'])->name('profil.show');
Route::patch('/profil', [ProfilController::class, 'update'])->name('profil.update');
Route::get('/riwayat-pangkat', [ProfilController::class, 'riwayatPangkat'])->name('profil.riwayat-pangkat');
