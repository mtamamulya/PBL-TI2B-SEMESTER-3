<?php

use App\BackendKel3\Pengabdian\IsianKategoriController;
use App\BackendKel3\Pengabdian\PengabdianController;
use Illuminate\Support\Facades\Route;

// Prefix /api/v1/dosen, auth:sanctum, dan PastikanDosenAktif dipasang router induk.

Route::get('kategori/{id}/isian', [IsianKategoriController::class, 'index'])
    ->whereNumber('id')->name('kategori.isian');

Route::get('pengabdian', [PengabdianController::class, 'index'])->name('pengabdian.index');
Route::post('pengabdian', [PengabdianController::class, 'store'])->name('pengabdian.store');
Route::get('pengabdian/{id}', [PengabdianController::class, 'show'])
    ->whereNumber('id')->name('pengabdian.show');
Route::match(['put', 'patch'], 'pengabdian/{id}', [PengabdianController::class, 'update'])
    ->whereNumber('id')->name('pengabdian.update');
